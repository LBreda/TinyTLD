<?php

use App\Models\Domain;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Domain')] class extends Component {
    public Domain $domain;

    public function mount(Domain $domain): void
    {
        $this->authorize('view', $domain);

        $this->domain = $domain->load(['tld', 'registrant', 'registrar', 'contacts', 'nameservers.addresses', 'dsRecords']);
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->domain);

        $this->domain->delete();

        Flux::toast(variant: 'success', text: __('Domain deleted.'));

        $this->redirectRoute('domains.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $domain->displayName() }}</flux:heading>
            @if ($domain->unicode_name !== $domain->name)
                <flux:text>{{ $domain->name }}</flux:text>
            @endif
        </div>

        <div class="flex gap-2">
            <flux:button icon="code-bracket" :href="route('rdap.domain', $domain->name)" target="_blank">RDAP</flux:button>
            @can('update', $domain)
                <flux:button variant="primary" icon="pencil" :href="route('domains.edit', $domain)" wire:navigate>{{ __('Edit') }}</flux:button>
            @endcan
            @can('delete', $domain)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Delete this domain?') }}">{{ __('Delete') }}</flux:button>
            @endcan
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <flux:card class="space-y-2">
            <flux:heading>{{ __('Registration') }}</flux:heading>
            <flux:text>{{ __('TLD') }}: .{{ $domain->tld->displayName() }}</flux:text>
            <flux:text>{{ __('Registrar') }}: {{ $domain->registrar?->name ?? __('None (managed by the registry)') }}</flux:text>
            <flux:text>{{ __('Registered') }}: {{ $domain->registered_at?->toDateString() ?? '—' }}</flux:text>
            <flux:text>{{ __('Expires') }}: {{ $domain->expires_at?->toDateString() ?? '—' }}</flux:text>
            <flux:text>{{ __('Last update') }}: {{ $domain->updated_at?->toDateTimeString() }}</flux:text>
            <div class="flex flex-wrap gap-1">
                @forelse ($domain->statuses ?? [] as $status)
                    <flux:badge size="sm">{{ $status->value }}</flux:badge>
                @empty
                    <flux:badge size="sm" color="green">ok</flux:badge>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:heading>{{ __('Contacts') }}</flux:heading>
            <flux:text>
                {{ __('Registrant') }}:
                <flux:link :href="route('contacts.show', $domain->registrant)" wire:navigate>{{ $domain->registrant->handle }}</flux:link>
                — {{ $domain->registrant->organization ?: $domain->registrant->name }}
            </flux:text>
            @foreach ($domain->contacts as $contact)
                <flux:text>
                    {{ ucfirst($contact->pivot->role) }}:
                    <flux:link :href="route('contacts.show', $contact)" wire:navigate>{{ $contact->handle }}</flux:link>
                    — {{ $contact->name }}
                </flux:text>
            @endforeach
        </flux:card>

        <flux:card class="space-y-2">
            <flux:heading>{{ __('Nameservers') }}</flux:heading>
            @forelse ($domain->nameservers as $nameserver)
                <flux:text>
                    {{ $nameserver->displayName() }}
                    @if ($nameserver->addresses->isNotEmpty())
                        ({{ $nameserver->addresses->pluck('ip')->join(', ') }})
                    @endif
                </flux:text>
            @empty
                <flux:text>{{ __('No nameservers.') }}</flux:text>
            @endforelse
        </flux:card>

        <flux:card class="space-y-2">
            <flux:heading>{{ __('DNSSEC') }}</flux:heading>
            @forelse ($domain->dsRecords as $ds)
                <flux:text class="font-mono break-all">{{ $ds->key_tag }} {{ $ds->algorithm }} {{ $ds->digest_type }} {{ $ds->digest }}</flux:text>
            @empty
                <flux:text>{{ __('Unsigned delegation.') }}</flux:text>
            @endforelse
        </flux:card>
    </div>
</section>
