<?php

use App\Models\Registrar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Registrars')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Registrar::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function registrars(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Registrar::query()
            ->withCount(['domains', 'users'])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('handle', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(25);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('Registrars') }}</flux:heading>

        <flux:button variant="primary" icon="plus" :href="route('registrars.create')" wire:navigate>
            {{ __('New registrar') }}
        </flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search registrars...')" />

    <flux:table :paginate="$this->registrars">
        <flux:table.columns>
            <flux:table.column>{{ __('Handle') }}</flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('IANA ID') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Domains') }}</flux:table.column>
            <flux:table.column>{{ __('Operators') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->registrars as $registrar)
                <flux:table.row :key="$registrar->id">
                    <flux:table.cell>
                        <flux:link :href="route('registrars.edit', $registrar)" wire:navigate>{{ $registrar->handle }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $registrar->name }}</flux:table.cell>
                    <flux:table.cell>{{ $registrar->iana_id ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$registrar->status === App\Enums\RegistrarStatus::Active ? 'green' : 'red'">{{ $registrar->status->value }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $registrar->domains_count }}</flux:table.cell>
                    <flux:table.cell>{{ $registrar->users_count }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No registrars found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
