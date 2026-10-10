<?php

use App\Models\Tld;
use App\Support\Idn;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('tlds.singular')] class extends Component {
    public ?Tld $tld = null;

    public string $name = '';
    public int $default_ttl = 3600;
    public string $soa_mname = '';
    public string $soa_rname = '';
    public int $soa_refresh = 7200;
    public int $soa_retry = 3600;
    public int $soa_expire = 1209600;
    public int $soa_minimum = 3600;
    public string $apex_nameservers = '';

    public function mount(?Tld $tld = null): void
    {
        if ($tld?->exists) {
            $this->tld = $tld;
            $this->name = $tld->displayName();
            $this->fill($tld->only(['default_ttl', 'soa_mname', 'soa_rname', 'soa_refresh', 'soa_retry', 'soa_expire', 'soa_minimum']));
            $this->apex_nameservers = implode("\n", $tld->apex_nameservers ?? []);
        }
    }

    public function save(): void
    {
        $this->authorize('admin');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:63'],
            'default_ttl' => ['required', 'integer', 'min:60'],
            'soa_mname' => ['required', 'string', 'max:255'],
            'soa_rname' => ['required', 'string', 'max:255'],
            'soa_refresh' => ['required', 'integer', 'min:1'],
            'soa_retry' => ['required', 'integer', 'min:1'],
            'soa_expire' => ['required', 'integer', 'min:1'],
            'soa_minimum' => ['required', 'integer', 'min:1'],
        ]);

        if (! Idn::isValidHostName($this->name) || str_contains(Idn::toAscii($this->name), '.')) {
            throw ValidationException::withMessages(['name' => __('tlds.errors.invalid')]);
        }

        if (Tld::query()->where('name', Idn::toAscii($this->name))->when($this->tld, fn ($q) => $q->whereKeyNot($this->tld->id))->exists()) {
            throw ValidationException::withMessages(['name' => __('tlds.errors.already_exists')]);
        }

        $hosts = preg_split('/[\s,]+/', $this->apex_nameservers, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($hosts as $host) {
            if (! Idn::isValidHostName($host)) {
                throw ValidationException::withMessages(['apex_nameservers' => __('common.errors.invalid_host', ['host' => $host])]);
            }
        }

        $validated['apex_nameservers'] = array_map(Idn::toAscii(...), $hosts);

        $tld = $this->tld ?? new Tld;
        $tld->fill($validated)->save();

        Flux::toast(variant: 'success', text: __('tlds.saved'));

        $this->redirectRoute('tlds.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <flux:heading size="xl" level="1">{{ $tld ? '.'.$tld->displayName() : __('tlds.new') }}</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="name" :label="__('common.fields.name')" :description="__('tlds.fields.name_help')" required />
            <flux:input wire:model="default_ttl" type="number" :label="__('tlds.fields.default_ttl')" required />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="soa_mname" :label="__('tlds.fields.soa_mname')" required />
            <flux:input wire:model="soa_rname" :label="__('tlds.fields.soa_rname')" placeholder="hostmaster.nic.example" required />
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <flux:input wire:model="soa_refresh" type="number" :label="__('tlds.fields.refresh')" required />
            <flux:input wire:model="soa_retry" type="number" :label="__('tlds.fields.retry')" required />
            <flux:input wire:model="soa_expire" type="number" :label="__('tlds.fields.expire')" required />
            <flux:input wire:model="soa_minimum" type="number" :label="__('tlds.fields.negative_ttl')" required />
        </div>

        <flux:textarea wire:model="apex_nameservers" :label="__('tlds.fields.nameservers')" :description="__('tlds.fields.nameservers_help')" rows="3" />

        <div class="flex gap-2">
            <flux:button variant="primary" type="submit">{{ __('common.actions.save') }}</flux:button>
            <flux:button :href="route('tlds.index')" wire:navigate>{{ __('common.actions.cancel') }}</flux:button>
        </div>
    </form>
</section>
