<?php

use App\Models\Nameserver;
use App\Support\Idn;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nameserver')] class extends Component {
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
            throw ValidationException::withMessages(['name' => __('Invalid host name.')]);
        }

        $name = Idn::toAscii($this->name);

        if (Nameserver::query()->where('name', $name)->when($this->nameserver, fn ($q) => $q->whereKeyNot($this->nameserver->id))->exists()) {
            throw ValidationException::withMessages(['name' => __('This nameserver already exists.')]);
        }

        $ips = array_values(array_unique(preg_split('/[\s,]+/', $this->addresses, -1, PREG_SPLIT_NO_EMPTY)));

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                throw ValidationException::withMessages(['addresses' => __('Invalid IP address: :ip', ['ip' => $ip])]);
            }
        }

        DB::transaction(function () use ($name, $ips) {
            $nameserver = $this->nameserver ?? new Nameserver;
            $nameserver->fill(['name' => $name])->save();

            $nameserver->addresses()->delete();
            $nameserver->addresses()->createMany(array_map(fn ($ip) => ['ip' => inet_ntop(inet_pton($ip))], $ips));
        });

        Flux::toast(variant: 'success', text: __('Nameserver saved.'));

        $this->redirectRoute('nameservers.index', navigate: true);
    }

    public function delete(): void
    {
        $this->authorize('admin');

        if ($this->nameserver->domains()->exists()) {
            Flux::toast(variant: 'danger', text: __('The nameserver is still delegated by some domains.'));

            return;
        }

        $this->nameserver->delete();

        $this->redirectRoute('nameservers.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ $nameserver ? $nameserver->displayName() : __('New nameserver') }}</flux:heading>

        @if ($nameserver)
            <div class="flex gap-2">
                <flux:button icon="code-bracket" :href="route('rdap.nameserver', $nameserver->name)" target="_blank">RDAP</flux:button>
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Delete this nameserver?') }}">{{ __('Delete') }}</flux:button>
            </div>
        @endif
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="name" :label="__('Host name')" required />

        <flux:textarea wire:model="addresses" :label="__('IP addresses')" :description="__('IPv4/IPv6 glue, one per line. Only needed for hosts inside a TLD served by this registry.')" rows="3" class="font-mono" />

        <div class="flex gap-2">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            <flux:button :href="route('nameservers.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
