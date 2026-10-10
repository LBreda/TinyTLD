<?php

use App\Models\Tld;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('tlds.plural')] class extends Component {
    #[Computed]
    public function tlds(): Collection
    {
        return Tld::query()->withCount('domains')->orderBy('name')->get();
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('tlds.plural') }}</flux:heading>

        <flux:button variant="primary" icon="plus" :href="route('tlds.create')" wire:navigate>
            {{ __('tlds.new') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('common.fields.name') }}</flux:table.column>
            <flux:table.column>{{ __('tlds.fields.soa_serial') }}</flux:table.column>
            <flux:table.column>{{ __('domains.plural') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->tlds as $tld)
                <flux:table.row :key="$tld->id">
                    <flux:table.cell>
                        <flux:link :href="route('tlds.edit', $tld)" wire:navigate>.{{ $tld->displayName() }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $tld->serial }}</flux:table.cell>
                    <flux:table.cell>{{ $tld->domains_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" icon="arrow-down-tray" :href="route('tlds.zone', $tld)">{{ __('tlds.zone_file') }}</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">{{ __('tlds.empty') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
