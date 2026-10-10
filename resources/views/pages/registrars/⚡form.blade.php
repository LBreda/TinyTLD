<?php

use App\Enums\RegistrarStatus;
use App\Models\Registrar;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('registrars.singular')] class extends Component {
    public ?Registrar $registrar = null;

    public string $handle = '';
    public string $iana_id = '';
    public string $status = 'active';
    public string $name = '';
    public string $organization = '';
    public string $email = '';
    public string $phone = '';
    public string $url = '';
    public string $abuse_email = '';
    public string $abuse_phone = '';
    public string $street = '';
    public string $city = '';
    public string $state_province = '';
    public string $postal_code = '';
    public string $country_code = '';

    /**
     * Fields registrar operators may change; handle, IANA ID and status are registry-controlled.
     */
    private const PROFILE_FIELDS = [
        'name', 'organization', 'email', 'phone', 'url', 'abuse_email', 'abuse_phone',
        'street', 'city', 'state_province', 'postal_code', 'country_code',
    ];

    public function mount(?Registrar $registrar = null): void
    {
        if ($registrar?->exists) {
            $this->authorize('update', $registrar);

            $this->registrar = $registrar;
            $this->fill(array_map(fn ($v) => (string) $v, $registrar->only(['handle', 'iana_id', ...self::PROFILE_FIELDS])));
            $this->status = $registrar->status->value;
        } else {
            $this->authorize('create', Registrar::class);
        }
    }

    public function isAdmin(): bool
    {
        return Auth::user()->isAdmin();
    }

    public function save(): void
    {
        $this->authorize($this->registrar ? 'update' : 'create', $this->registrar ?? Registrar::class);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'url' => ['nullable', 'url', 'max:255'],
            'abuse_email' => ['nullable', 'email', 'max:255'],
            'abuse_phone' => ['nullable', 'string', 'max:32'],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state_province' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
        ];

        if ($this->isAdmin()) {
            $rules += [
                'handle' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/', Rule::unique('registrars', 'handle')->ignore($this->registrar)],
                'iana_id' => ['nullable', 'integer', 'min:1', Rule::unique('registrars', 'iana_id')->ignore($this->registrar)],
                'status' => ['required', Rule::enum(RegistrarStatus::class)],
            ];
        }

        $validated = array_map(fn ($v) => $v === '' ? null : $v, $this->validate($rules));

        if (isset($validated['country_code'])) {
            $validated['country_code'] = strtoupper($validated['country_code']);
        }

        $registrar = $this->registrar ?? new Registrar;
        $registrar->fill($validated)->save();

        Flux::toast(variant: 'success', text: __('registrars.saved'));

        if (! $this->registrar) {
            $this->redirectRoute('registrars.edit', $registrar, navigate: true);
        }
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->registrar);

        $this->registrar->delete();

        Flux::toast(variant: 'success', text: __('registrars.deleted'));

        $this->redirectRoute('registrars.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ $registrar ? $registrar->name : __('registrars.new') }}</flux:heading>

        @if ($registrar)
            <div class="flex gap-2">
                <flux:button icon="code-bracket" :href="route('rdap.entity', $registrar->handle)" target="_blank">{{ __('common.rdap') }}</flux:button>
                @can('delete', $registrar)
                    <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('registrars.delete_confirm') }}">{{ __('common.actions.delete') }}</flux:button>
                @endcan
            </div>
        @endif
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <flux:input wire:model="handle" :label="__('common.fields.handle')" :disabled="! $this->isAdmin()" required />
            <flux:input wire:model="iana_id" type="number" :label="__('registrars.fields.iana_id')" :disabled="! $this->isAdmin()" />
            <flux:select wire:model="status" :label="__('common.fields.status')" :disabled="! $this->isAdmin()">
                @foreach (App\Enums\RegistrarStatus::cases() as $case)
                    <flux:select.option :value="$case->value">{{ $case->value }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="name" :label="__('common.fields.name')" required />
            <flux:input wire:model="organization" :label="__('registrars.fields.legal_name')" />
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <flux:input wire:model="email" type="email" :label="__('common.fields.email')" required />
            <flux:input wire:model="phone" :label="__('common.fields.phone')" placeholder="+39.0612345678" />
            <flux:input wire:model="url" type="url" :label="__('registrars.fields.website')" />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="abuse_email" type="email" :label="__('registrars.fields.abuse_email')" />
            <flux:input wire:model="abuse_phone" :label="__('registrars.fields.abuse_phone')" />
        </div>

        <flux:input wire:model="street" :label="__('common.fields.street')" />

        <div class="grid gap-4 md:grid-cols-4">
            <flux:input wire:model="postal_code" :label="__('common.fields.postal_code')" />
            <flux:input wire:model="city" :label="__('common.fields.city')" />
            <flux:input wire:model="state_province" :label="__('common.fields.state_province')" />
            <flux:input wire:model="country_code" :label="__('common.fields.country_code')" maxlength="2" />
        </div>

        <div class="flex gap-2">
            <flux:button variant="primary" type="submit">{{ __('common.actions.save') }}</flux:button>
        </div>
    </form>

    @if ($registrar)
        <flux:card class="space-y-2">
            <flux:heading>{{ __('registrars.operators') }}</flux:heading>
            @forelse ($registrar->users()->orderBy('name')->get() as $operator)
                <flux:text>
                    @can('admin')
                        <flux:link :href="route('users.edit', $operator)" wire:navigate>{{ $operator->name }}</flux:link>
                    @else
                        {{ $operator->name }}
                    @endcan
                    — {{ $operator->email }}
                </flux:text>
            @empty
                <flux:text>{{ __('registrars.no_operators') }}</flux:text>
            @endforelse
        </flux:card>
    @endif
</section>
