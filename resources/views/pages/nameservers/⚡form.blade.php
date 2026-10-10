<?php

use App\Models\Nameserver;
use App\Support\Idn;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('nameservers.singular')] class extends Component {
    public ?Nameserver $nameserver = null;

    public string $name = '';
    public string $addresses = '';

    public function mount(?Nameserver $nameserver = null): void
    {
        if ($nameserver?->exists) {
            $this->nameserver = $nameserver;
            $this->name = $nameserver->displayName();
            $this->addresses = $nameserver->addresses()->pluck('ip')->join("\n");
        }
    }

    public function save(): void
    {
        $this->authorize('admin');

        $this->validate(['name' => ['required', 'string', 'max:253']]);

        if (! Idn::isValidHostName($this->name) || ! str_contains($this->name, '.')) {
            throw ValidationException::withMessages(['name' => __('nameservers.errors.invalid_host_name')]);
        }

        $name = Idn::toAscii($this->name);

        if (Nameserver::query()->where('name', $name)->when($this->nameserver, fn ($q) => $q->whereKeyNot($this->nameserver->id))->exists()) {
            throw ValidationException::withMessages(['name' => __('nameservers.errors.already_exists')]);
        }

        $ips = array_values(array_unique(preg_split('/[\s,]+/', $this->addresses, -1, PREG_SPLIT_NO_EMPTY)));

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                throw ValidationException::withMessages(['addresses' => __('nameservers.errors.invalid_ip', ['ip' => $ip])]);
            }
        }

        DB::transaction(function () use ($name, $ips) {
            $nameserver = $this->nameserver ?? new Nameserver;
            $nameserver->fill(['name' => $name])->save();

            $nameserver->addresses()->delete();
            $nameserver->addresses()->createMany(array_map(fn ($ip) => ['ip' => inet_ntop(inet_pton($ip))], $ips));
        });

        Flux::toast(variant: 'success', text: __('nameservers.saved'));

        $this->redirectRoute('nameservers.index', navigate: true);
    }

    public function delete(): void
    {
        $this->authorize('admin');

        if ($this->nameserver->domains()->exists()) {
            Flux::toast(variant: 'danger', text: __('nameservers.still_delegated'));

            return;
        }

        $this->nameserver->delete();

        $this->redirectRoute('nameservers.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ $nameserver ? $nameserver->displayName() : __('nameservers.new') }}</flux:heading>

        @if ($nameserver)
            <div class="flex gap-2">
                <flux:button icon="code-bracket" :href="route('rdap.nameserver', $nameserver->name)" target="_blank">{{ __('common.rdap') }}</flux:button>
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('nameservers.delete_confirm') }}">{{ __('common.actions.delete') }}</flux:button>
            </div>
        @endif
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="name" :label="__('nameservers.fields.host_name')" required />

        <flux:textarea wire:model="addresses" :label="__('nameservers.fields.ip_addresses')" :description="__('nameservers.fields.ip_addresses_help')" rows="3" class="font-mono" />

        <div class="flex gap-2">
            <flux:button variant="primary" type="submit">{{ __('common.actions.save') }}</flux:button>
            <flux:button :href="route('nameservers.index')" wire:navigate>{{ __('common.actions.cancel') }}</flux:button>
        </div>
    </form>
</section>
