<?php

use App\Enums\ContactKind;
use App\Models\Contact;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('contacts.singular')] class extends Component {
    public ?Contact $contact = null;

    public string $handle = '';
    public string $kind = 'individual';
    public string $name = '';
    public string $organization = '';
    public string $street = '';
    public string $city = '';
    public string $state_province = '';
    public string $postal_code = '';
    public string $country_code = '';
    public string $email = '';
    public string $phone = '';
    public string $fax = '';
    /** @var list<string> */
    public array $disclose = [];

    public function mount(?Contact $contact = null): void
    {
        if ($contact?->exists) {
            $this->authorize('update', $contact);

            $this->contact = $contact;
            $this->fill(array_map(fn ($v) => (string) $v, $contact->only([
                'handle', 'name', 'organization', 'street', 'city', 'state_province',
                'postal_code', 'country_code', 'email', 'phone', 'fax',
            ])));
            $this->kind = $contact->kind->value;
            $this->disclose = $contact->disclose ?? [];
        } else {
            $this->authorize('create', Contact::class);

            $this->handle = strtoupper('C-'.Str::random(8));
        }
    }

    public function save(): void
    {
        $this->authorize($this->contact ? 'update' : 'create', $this->contact ?? Contact::class);

        $validated = $this->validate([
            'handle' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/', Rule::unique('contacts', 'handle')->ignore($this->contact)],
            'kind' => ['required', Rule::enum(ContactKind::class)],
            'name' => ['required', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255', Rule::requiredIf($this->kind === ContactKind::Organization->value)],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state_province' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country_code' => ['required', 'string', 'size:2', 'alpha'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'fax' => ['nullable', 'string', 'max:32'],
            'disclose' => ['array'],
            'disclose.*' => [Rule::in(Contact::DISCLOSABLE)],
        ]);

        $validated = array_map(fn ($v) => $v === '' ? null : $v, $validated);
        $validated['country_code'] = strtoupper($validated['country_code']);
        $validated['disclose'] = array_values($this->disclose);

        $contact = $this->contact ?? new Contact;
        $contact->fill($validated)->save();

        Flux::toast(variant: 'success', text: __('contacts.saved'));

        $this->redirectRoute('contacts.show', $contact, navigate: true);
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <flux:heading size="xl" level="1">{{ $contact ? __('contacts.edit', ['handle' => $contact->handle]) : __('contacts.new') }}</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="handle" :label="__('common.fields.handle')" required />
            <flux:select wire:model.live="kind" :label="__('common.fields.type')">
                <flux:select.option value="individual">{{ __('contacts.individual') }}</flux:select.option>
                <flux:select.option value="org">{{ __('common.fields.organization') }}</flux:select.option>
            </flux:select>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="name" :label="__('common.fields.name')" required />
            <flux:input wire:model="organization" :label="__('common.fields.organization')" />
        </div>

        <flux:input wire:model="street" :label="__('common.fields.street')" />

        <div class="grid gap-4 md:grid-cols-4">
            <flux:input wire:model="postal_code" :label="__('common.fields.postal_code')" />
            <flux:input wire:model="city" :label="__('common.fields.city')" />
            <flux:input wire:model="state_province" :label="__('common.fields.state_province')" />
            <flux:input wire:model="country_code" :label="__('common.fields.country_code')" maxlength="2" required />
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <flux:input wire:model="email" type="email" :label="__('common.fields.email')" required />
            <flux:input wire:model="phone" :label="__('common.fields.phone')" placeholder="+39.0612345678" />
            <flux:input wire:model="fax" :label="__('common.fields.fax')" />
        </div>

        <flux:checkbox.group wire:model="disclose" :label="__('contacts.disclose.label')" :description="__('contacts.disclose.help')" class="grid gap-2 md:grid-cols-3">
            @foreach (App\Models\Contact::DISCLOSABLE as $field)
                <flux:checkbox :value="$field" :label="__('common.fields.'.$field)" />
            @endforeach
        </flux:checkbox.group>

        <div class="flex gap-2">
            <flux:button variant="primary" type="submit">{{ __('common.actions.save') }}</flux:button>
            <flux:button :href="$contact ? route('contacts.show', $contact) : route('contacts.index')" wire:navigate>{{ __('common.actions.cancel') }}</flux:button>
        </div>
    </form>
</section>
