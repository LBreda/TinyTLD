{{-- Override of Flux's with-field: the description is shown as a toggleable info tooltip next to the label instead of a line between label and control. --}}
@blaze(fold: true, unsafe: [
    'name', 'label', 'badge',
    'description', 'description:trailing',
    'label:badge', 'label:aside', 'label:trailing',
    'error:name', 'error:bag', 'error:message', 'error:icon', 'error:nested', 'error:deep',
])

@php
extract(Flux::forwardedAttributes($attributes, [
    'name',
    'descriptionTrailing',
    'description',
    'label',
    'badge',
]));
@endphp

@php $descriptionTrailing = $descriptionTrailing ??= $attributes->pluck('description:trailing'); @endphp

@props([
    'name' => $attributes->whereStartsWith('wire:model')->first(),
    'descriptionTrailing' => null,
    'description' => null,
    'label' => null,
    'badge' => null,
])

<?php if (isset($label) || isset($description) || isset($descriptionTrailing)): ?>
    <?php

        $fieldAttributes = Flux::attributesAfter('field:', $attributes, []);
        $labelAttributes = Flux::attributesAfter('label:', $attributes, ['badge' => $badge]);
        $descriptionAttributes = Flux::attributesAfter('description:', $attributes, []);
        $errorAttributes = Flux::attributesAfter('error:', $attributes, ['name' => $name]);
    ?>
    <flux:field :attributes="$fieldAttributes">
        <?php if (isset($label)): ?>
            <flux:label :attributes="$labelAttributes">
                {{ $label }}

                <?php if (isset($description)): ?>
                    <flux:tooltip toggleable position="top" class="ms-1.5 inline-flex">
                        <button type="button" class="inline-flex cursor-help rounded-full text-zinc-400 hover:text-zinc-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent dark:text-zinc-500 dark:hover:text-zinc-300" aria-label="{{ $description }}">
                            <flux:icon.information-circle variant="micro" />
                        </button>

                        <flux:tooltip.content class="max-w-xs whitespace-normal !px-2.5 !py-2 font-normal">{{ $description }}</flux:tooltip.content>
                    </flux:tooltip>
                <?php endif; ?>
            </flux:label>
        <?php elseif (isset($description)): ?>
            <flux:description :attributes="$descriptionAttributes">{{ $description }}</flux:description>
        <?php endif; ?>

        {{ $slot }}

        {{-- We're using ->getAttributes() here because ->all() is only available since Laravel 11... --}}
        @unblaze(scope: ['attributes' => $errorAttributes->getAttributes()])
        <flux:error :attributes="new \Illuminate\View\ComponentAttributeBag($scope['attributes'])" />
        @endunblaze

        <?php if (isset($descriptionTrailing)): ?>
            <flux:description :attributes="$descriptionAttributes">{{ $descriptionTrailing }}</flux:description>
        <?php endif; ?>
    </flux:field>
<?php else: ?>
    {{ $slot }}
<?php endif; ?>
