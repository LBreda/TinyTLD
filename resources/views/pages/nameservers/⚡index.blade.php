<?php

use App\Models\Nameserver;
use App\Support\Idn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('nameservers.plural')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function nameservers(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Nameserver::query()
            ->with('addresses')
            ->withCount('domains')
            ->when($search !== '', function ($query) use ($search) {
                $ascii = Idn::isValidHostName($search) ? Idn::toAscii($search) : $search;

                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$ascii}%")
                    ->orWhereHas('addresses', fn ($a) => $a->where('ip', 'like', "%{$search}%")));
            })
            ->orderBy('name')
            ->paginate(25);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('nameservers.plural') }}</flux:heading>

        <flux:button variant="primary" icon="plus" :href="route('nameservers.create')" wire:navigate>
            {{ __('nameservers.new') }}
        </flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('nameservers.search')" />

    <flux:table :paginate="$this->nameservers">
        <flux:table.columns>
            <flux:table.column>{{ __('nameservers.fields.host') }}</flux:table.column>
            <flux:table.column>{{ __('nameservers.fields.addresses') }}</flux:table.column>
            <flux:table.column>{{ __('domains.plural') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->nameservers as $nameserver)
                <flux:table.row :key="$nameserver->id">
                    <flux:table.cell>
                        <flux:link :href="route('nameservers.edit', $nameserver)" wire:navigate>{{ $nameserver->displayName() }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $nameserver->addresses->pluck('ip')->join(', ') ?: '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $nameserver->domains_count }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3">{{ __('nameservers.empty') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
