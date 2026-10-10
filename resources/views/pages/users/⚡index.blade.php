<?php

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('users.plural')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return User::query()
            ->with('registrar')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(25);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('users.plural') }}</flux:heading>

        <flux:button variant="primary" icon="plus" :href="route('users.create')" wire:navigate>
            {{ __('users.new') }}
        </flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('users.search')" />

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column>{{ __('common.fields.name') }}</flux:table.column>
            <flux:table.column>{{ __('common.fields.email') }}</flux:table.column>
            <flux:table.column>{{ __('users.fields.role') }}</flux:table.column>
            <flux:table.column>{{ __('registrars.singular') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell>
                        <flux:link :href="route('users.edit', $user)" wire:navigate>{{ $user->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($user->isAdmin())
                            <flux:badge size="sm" color="purple">{{ __('users.roles.admin') }}</flux:badge>
                        @elseif ($user->registrar_id)
                            <flux:badge size="sm" color="blue">{{ __('users.roles.operator') }}</flux:badge>
                        @else
                            <flux:badge size="sm">{{ __('users.roles.none') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->registrar?->name ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
