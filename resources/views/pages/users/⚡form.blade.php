<?php

use App\Models\Registrar;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('users.singular')] class extends Component {
    public ?User $user = null;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public bool $is_admin = false;
    public string $registrar_id = '';

    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            $this->user = $user;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->is_admin = $user->isAdmin();
            $this->registrar_id = (string) $user->registrar_id;
        }
    }

    #[Computed]
    public function registrars(): Collection
    {
        return Registrar::query()->orderBy('name')->get(['id', 'name', 'handle']);
    }

    public function save(): void
    {
        $this->authorize('admin');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user)],
            'password' => [$this->user ? 'nullable' : 'required', 'string', PasswordRule::default()],
            'is_admin' => ['boolean'],
            'registrar_id' => ['nullable', 'exists:registrars,id'],
        ]);

        if ($this->user?->is(Auth::user()) && ! $this->is_admin) {
            $this->addError('is_admin', __('users.errors.cannot_remove_own_admin'));

            return;
        }

        $user = $this->user ?? new User;
        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);

        if ($validated['password']) {
            $user->password = $validated['password'];
        }

        // Role fields are not mass assignable on purpose.
        $user->forceFill([
            'is_admin' => $this->is_admin,
            'registrar_id' => $this->registrar_id ?: null,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Flux::toast(variant: 'success', text: __('users.saved'));

        $this->redirectRoute('users.index', navigate: true);
    }

    public function sendResetLink(): void
    {
        $this->authorize('admin');

        Password::sendResetLink(['email' => $this->user->email]);

        Flux::toast(text: __('users.reset_link_sent'));
    }

    public function delete(): void
    {
        $this->authorize('admin');

        if ($this->user->is(Auth::user())) {
            Flux::toast(variant: 'danger', text: __('users.errors.cannot_delete_self'));

            return;
        }

        $this->user->delete();

        $this->redirectRoute('users.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ $user ? $user->name : __('users.new') }}</flux:heading>

        @if ($user)
            <div class="flex gap-2">
                <flux:button icon="envelope" wire:click="sendResetLink">{{ __('users.send_reset_link') }}</flux:button>
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('users.delete_confirm') }}">{{ __('common.actions.delete') }}</flux:button>
            </div>
        @endif
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="name" :label="__('common.fields.name')" required />
        <flux:input wire:model="email" type="email" :label="__('common.fields.email')" required />
        <flux:input wire:model="password" type="password" :label="__('common.fields.password')" :description="$user ? __('users.fields.password_help') : null" viewable />

        <flux:select wire:model="registrar_id" :label="__('registrars.singular')" :description="__('users.fields.operators_help')">
            <flux:select.option value="">{{ __('common.none') }}</flux:select.option>
            @foreach ($this->registrars as $registrar)
                <flux:select.option :value="$registrar->id">{{ $registrar->name }} ({{ $registrar->handle }})</flux:select.option>
            @endforeach
        </flux:select>

        <flux:switch wire:model="is_admin" :label="__('users.fields.registry_administrator')" />

        <div class="flex gap-2">
            <flux:button variant="primary" type="submit">{{ __('common.actions.save') }}</flux:button>
            <flux:button :href="route('users.index')" wire:navigate>{{ __('common.actions.cancel') }}</flux:button>
        </div>
    </form>
</section>
