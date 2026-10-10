<?php

use App\Models\Contact;
use App\Models\Domain;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('contacts.singular')] class extends Component {
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
            Flux::toast(variant: 'danger', text: __('contacts.still_linked'));

            return;
        }

        $this->contact->delete();

        Flux::toast(variant: 'success', text: __('contacts.deleted'));

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
            <flux:button icon="code-bracket" :href="route('rdap.entity', $contact->handle)" target="_blank">{{ __('common.rdap') }}</flux:button>
            @can('update', $contact)
                <flux:button variant="primary" icon="pencil" :href="route('contacts.edit', $contact)" wire:navigate>{{ __('common.actions.edit') }}</flux:button>
            @endcan
            @can('delete', $contact)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('contacts.delete_confirm') }}">{{ __('common.actions.delete') }}</flux:button>
            @endcan
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <flux:card class="space-y-2">
            <flux:heading>{{ __('common.details') }}</flux:heading>
            <flux:text>{{ __('common.fields.type') }}: {{ $contact->kind->value }}</flux:text>
            <flux:text>{{ __('common.fields.name') }}: {{ $contact->name }}</flux:text>
            <flux:text>{{ __('common.fields.organization') }}: {{ $contact->organization ?? '—' }}</flux:text>
            <flux:text>{{ __('common.fields.address') }}: {{ collect([$contact->street, $contact->postal_code, $contact->city, $contact->state_province, $contact->country_code])->filter()->join(', ') }}</flux:text>
            <flux:text>{{ __('common.fields.email') }}: {{ $contact->email }}</flux:text>
            <flux:text>{{ __('common.fields.phone') }}: {{ $contact->phone ?? '—' }}</flux:text>
            <flux:text>{{ __('common.fields.fax') }}: {{ $contact->fax ?? '—' }}</flux:text>
            <div class="flex flex-wrap gap-1">
                <flux:text>{{ __('contacts.disclose.published') }}:</flux:text>
                @forelse ($contact->disclose ?? [] as $field)
                    <flux:badge size="sm" color="amber">{{ __('common.fields.'.$field) }}</flux:badge>
                @empty
                    <flux:badge size="sm">{{ __('contacts.disclose.nothing') }}</flux:badge>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:heading>{{ __('domains.plural') }}</flux:heading>
            @forelse ($this->domains as $domain)
                <flux:text>
                    <flux:link :href="route('domains.show', $domain)" wire:navigate>{{ $domain->displayName() }}</flux:link>
                    @if ($domain->registrant_id === $contact->id)
                        <flux:badge size="sm">{{ __('contacts.registrant_badge') }}</flux:badge>
                    @endif
                </flux:text>
            @empty
                <flux:text>{{ __('domains.none') }}</flux:text>
            @endforelse
        </flux:card>
    </div>
</section>
