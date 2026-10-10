<x-layouts::auth :title="__('auth.register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('auth.register_page.title')" :description="__('auth.register_page.description')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('common.fields.name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('auth.register_page.full_name')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('auth.email_address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('common.fields.password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('common.fields.password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('common.fields.confirm_password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('common.fields.confirm_password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('auth.register_page.submit') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('auth.register_page.have_account') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('auth.log_in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
