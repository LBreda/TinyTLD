<?php

use App\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Contacts')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Contact::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function contacts(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Contact::query()
            ->visibleTo(Auth::user())
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('handle', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('organization', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('handle')
            ->paginate(25);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('Contacts') }}</flux:heading>

        @can('create', App\Models\Contact::class)
            <flux:button variant="primary" icon="plus" :href="route('contacts.create')" wire:navigate>
                {{ __('New contact') }}
            </flux:button>
        @endcan
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search contacts...')" />

    <flux:table :paginate="$this->contacts">
        <flux:table.columns>
            <flux:table.column>{{ __('Handle') }}</flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Organization') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Country') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->contacts as $contact)
                <flux:table.row :key="$contact->id">
                    <flux:table.cell>
                        <flux:link :href="route('contacts.show', $contact)" wire:navigate>{{ $contact->handle }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $contact->name }}</flux:table.cell>
                    <flux:table.cell>{{ $contact->organization ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $contact->email }}</flux:table.cell>
                    <flux:table.cell>{{ $contact->country_code }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No contacts found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
