<?php

use App\Models\Domain;
use App\Support\Idn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Domains')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Domain::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function domains(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Domain::query()
            ->visibleTo(Auth::user())
            ->with(['registrant', 'registrar', 'tld'])
            ->when($search !== '', function ($query) use ($search) {
                $ascii = Idn::isValidHostName($search) ? Idn::toAscii($search) : $search;

                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$ascii}%")
                    ->orWhere('unicode_name', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(25);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('Domains') }}</flux:heading>

        @can('create', App\Models\Domain::class)
            <flux:button variant="primary" icon="plus" :href="route('domains.create')" wire:navigate>
                {{ __('New domain') }}
            </flux:button>
        @endcan
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search domains...')" />

    <flux:table :paginate="$this->domains">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Registrant') }}</flux:table.column>
            <flux:table.column>{{ __('Registrar') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Expires') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->domains as $domain)
                <flux:table.row :key="$domain->id">
                    <flux:table.cell>
                        <flux:link :href="route('domains.show', $domain)" wire:navigate>{{ $domain->displayName() }}</flux:link>
                        @if ($domain->unicode_name && $domain->unicode_name !== $domain->name)
                            <flux:text size="sm">{{ $domain->name }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $domain->registrant->organization ?: $domain->registrant->name }}</flux:table.cell>
                    <flux:table.cell>{{ $domain->registrar?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @forelse ($domain->statuses ?? [] as $status)
                            <flux:badge size="sm">{{ $status->value }}</flux:badge>
                        @empty
                            <flux:badge size="sm" color="green">ok</flux:badge>
                        @endforelse
                    </flux:table.cell>
                    <flux:table.cell>{{ $domain->expires_at?->toDateString() ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No domains found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
