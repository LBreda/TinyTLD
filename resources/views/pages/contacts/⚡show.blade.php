<?php

use App\Models\Contact;
use App\Models\Domain;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Contact')] class extends Component {
    public Contact $contact;

    public function mount(Contact $contact): void
    {
        $this->authorize('view', $contact);

        $this->contact = $contact;
    }

    /**
     * Domains linked to this contact that the current user may see.
     */
    #[Computed]
    public function domains(): Collection
    {
        return Domain::query()
            ->visibleTo(Auth::user())
            ->where(fn ($q) => $q
                ->where('registrant_id', $this->contact->id)
                ->orWhereHas('contacts', fn ($c) => $c->whereKey($this->contact->id)))
            ->orderBy('name')
            ->get();
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->contact);

        if ($this->contact->registeredDomains()->exists() || $this->contact->domains()->exists()) {
            Flux::toast(variant: 'danger', text: __('The contact is still linked to domains.'));

            return;
        }

        $this->contact->delete();

        Flux::toast(variant: 'success', text: __('Contact deleted.'));

        $this->redirectRoute('contacts.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $contact->handle }}</flux:heading>
            <flux:text>{{ $contact->organization ?: $contact->name }}</flux:text>
        </div>

        <div class="flex gap-2">
            <flux:button icon="code-bracket" :href="route('rdap.entity', $contact->handle)" target="_blank">RDAP</flux:button>
            @can('update', $contact)
                <flux:button variant="primary" icon="pencil" :href="route('contacts.edit', $contact)" wire:navigate>{{ __('Edit') }}</flux:button>
            @endcan
            @can('delete', $contact)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Delete this contact?') }}">{{ __('Delete') }}</flux:button>
            @endcan
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <flux:card class="space-y-2">
            <flux:heading>{{ __('Details') }}</flux:heading>
            <flux:text>{{ __('Type') }}: {{ $contact->kind->value }}</flux:text>
            <flux:text>{{ __('Name') }}: {{ $contact->name }}</flux:text>
            <flux:text>{{ __('Organization') }}: {{ $contact->organization ?? '—' }}</flux:text>
            <flux:text>{{ __('Address') }}: {{ collect([$contact->street, $contact->postal_code, $contact->city, $contact->state_province, $contact->country_code])->filter()->join(', ') }}</flux:text>
            <flux:text>{{ __('Email') }}: {{ $contact->email }}</flux:text>
            <flux:text>{{ __('Phone') }}: {{ $contact->phone ?? '—' }}</flux:text>
            <flux:text>{{ __('Fax') }}: {{ $contact->fax ?? '—' }}</flux:text>
            <div class="flex flex-wrap gap-1">
                <flux:text>{{ __('Published via RDAP') }}:</flux:text>
                @forelse ($contact->disclose ?? [] as $field)
                    <flux:badge size="sm" color="amber">{{ $field }}</flux:badge>
                @empty
                    <flux:badge size="sm">{{ __('nothing (fully redacted)') }}</flux:badge>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:heading>{{ __('Domains') }}</flux:heading>
            @forelse ($this->domains as $domain)
                <flux:text>
                    <flux:link :href="route('domains.show', $domain)" wire:navigate>{{ $domain->displayName() }}</flux:link>
                    @if ($domain->registrant_id === $contact->id)
                        <flux:badge size="sm">{{ __('registrant') }}</flux:badge>
                    @endif
                </flux:text>
            @empty
                <flux:text>{{ __('No domains.') }}</flux:text>
            @endforelse
        </flux:card>
    </div>
</section>
