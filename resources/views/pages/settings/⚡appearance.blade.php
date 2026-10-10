<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('settings.appearance.title')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('settings.appearance.title') }}</flux:heading>

    <x-pages::settings.layout :heading="__('settings.appearance.nav')" :subheading="__('settings.appearance.description')">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('settings.appearance.light') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('settings.appearance.dark') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('settings.appearance.system') }}</flux:radio>
        </flux:radio.group>
    </x-pages::settings.layout>
</section>
