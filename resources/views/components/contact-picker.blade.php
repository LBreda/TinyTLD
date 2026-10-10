@props([
    'model',
    'label' => null,
    'multiple' => false,
    'labels' => [],
    'placeholder' => null,
])

<flux:field
    x-data="{
        value: $wire.entangle(@js($model)),
        labels: @js((object) $labels),
        multiple: @js((bool) $multiple),
        query: '',
        results: [],
        open: false,
        active: -1,
        dirty: false,
        init() {
            if (this.multiple) return;
            this.reset();
            this.$watch('value', () => this.dirty || this.reset());
        },
        display(handle) {
            if (! handle) return '';
            return this.labels[handle] ? handle + ' — ' + this.labels[handle] : handle;
        },
        reset() {
            if (this.multiple) return;
            this.dirty = false;
            this.query = this.display(this.value);
        },
        close() {
            this.open = false;
            this.reset();
        },
        get selected() {
            if (this.multiple) return this.value ?? [];
            return this.value ? [this.value] : [];
        },
        async search() {
            const query = this.multiple || this.dirty ? this.query : '';
            this.results = await $wire.searchContacts(query, this.multiple ? this.selected : []);
            this.active = this.results.length ? 0 : -1;
            this.open = true;
        },
        select(contact) {
            if (! contact) return;
            this.labels[contact.handle] = contact.name;
            this.value = this.multiple ? [...this.selected, contact.handle] : contact.handle;
            this.query = this.multiple ? '' : this.display(contact.handle);
            this.dirty = false;
            this.results = [];
            this.open = false;
        },
        remove(handle) {
            this.value = this.multiple ? this.selected.filter(h => h !== handle) : '';
            if (this.open) this.$nextTick(() => this.search());
        },
        move(step) {
            if (! this.open) return this.search();
            if (! this.results.length) return;
            this.active = (this.active + step + this.results.length) % this.results.length;
        },
    }"
    x-on:click.outside="close()"
>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <div class="relative">
        @if ($multiple)
        <div
            class="flex min-h-10 w-full cursor-text flex-wrap items-center gap-1 rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white py-1.5 ps-3 pe-3 shadow-xs focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent dark:border-white/10 dark:bg-white/10 dark:shadow-none"
            x-on:click="$refs.search.focus()"
        >
            <flux:icon.magnifying-glass variant="mini" class="me-1 text-zinc-400/75 dark:text-white/60" />
            <template x-for="handle in selected" :key="handle">
                <flux:badge size="sm" class="max-w-full">
                    <span class="truncate"><span class="font-mono" x-text="handle"></span><span x-show="labels[handle]" x-text="' — ' + labels[handle]"></span></span>
                    <flux:badge.close x-on:click.stop="remove(handle)" />
                </flux:badge>
            </template>
            <input
                x-ref="search"
                x-model="query"
                x-on:input.debounce.250ms="search()"
                x-on:focus="search()"
                x-on:keydown.arrow-down.prevent="move(1)"
                x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent="select(results[active])"
                x-on:keydown.backspace="query === '' && selected.length && remove(selected[selected.length - 1])"
                x-on:keydown.escape="close()"
                x-on:keydown.tab="close()"
                autocomplete="off"
                :placeholder="selected.length ? '' : @js($placeholder ?? __('domains.fields.contact_search'))"
                class="h-7 min-w-32 flex-1 border-0 bg-transparent p-0 text-base text-zinc-700 placeholder-zinc-400 outline-none focus:outline-none focus:ring-0 sm:text-sm dark:text-zinc-300 dark:placeholder-zinc-400"
            />
        </div>
        @else
        <flux:input
            x-model="query"
            x-on:input="dirty = true"
            x-on:input.debounce.250ms="search()"
            x-on:focus="$event.target.select(); search()"
            x-on:keydown.arrow-down.prevent="move(1)"
            x-on:keydown.arrow-up.prevent="move(-1)"
            x-on:keydown.enter.prevent="select(results[active])"
            x-on:keydown.escape="close()"
            x-on:keydown.tab="close()"
            icon="magnifying-glass"
            autocomplete="off"
            :placeholder="$placeholder ?? __('domains.fields.contact_search')"
        />
        @endif

        <div
            x-show="open"
            style="display: none"
            class="absolute inset-x-0 top-full z-20 mt-1 max-h-64 overflow-auto rounded-lg border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-600 dark:bg-zinc-700"
        >
            <template x-for="(contact, index) in results" :key="contact.handle">
                <button
                    type="button"
                    class="flex w-full flex-col items-start rounded-md px-2 py-1.5 text-start text-sm"
                    :class="index === active ? 'bg-zinc-100 dark:bg-zinc-600' : ''"
                    x-on:mouseenter="active = index"
                    x-on:click="select(contact)"
                >
                    <span class="font-mono font-medium text-zinc-800 dark:text-white" x-text="contact.handle"></span>
                    <span class="text-zinc-500 dark:text-zinc-300" x-text="contact.organization ? contact.name + ' (' + contact.organization + ')' : contact.name"></span>
                </button>
            </template>
            <div x-show="! results.length" class="px-2 py-1.5 text-sm text-zinc-500 dark:text-zinc-300">{{ __('contacts.empty') }}</div>
        </div>
    </div>

    <flux:error :name="$model" />
</flux:field>
