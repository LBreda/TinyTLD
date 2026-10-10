<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tld;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_command_runs_migrations_seeders_and_registers_admin(): void
    {
        $this->artisan('app:install', [
            '--name' => 'Alice Admin',
            '--email' => 'alice@registry.test',
            '--password' => 'SecurePass123!',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'alice@registry.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('Alice Admin', $user->name);
        $this->assertTrue($user->isAdmin());
        $this->assertNull($user->registrar_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));

        // Seeded records should exist
        $this->assertGreaterThan(0, Tld::query()->count());
        $this->assertGreaterThan(0, Domain::query()->count());
    }

    public function test_install_command_supports_no_seed_option(): void
    {
        Tld::query()->delete();

        $this->artisan('app:install', [
            '--no-seed' => true,
            '--name' => 'Bob Admin',
            '--email' => 'bob@registry.test',
            '--password' => 'SecurePass123!',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'bob@registry.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isAdmin());

        $this->assertSame(0, Tld::query()->count());
    }

    public function test_install_command_aliases(): void
    {
        $this->artisan('tinytld:setup', [
            '--no-seed' => true,
            '--name' => 'Setup Admin',
            '--email' => 'setup@registry.test',
            '--password' => 'SecurePass123!',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'setup@registry.test',
            'is_admin' => true,
        ]);

        $this->artisan('tinytld:install', [
            '--no-seed' => true,
            '--name' => 'TinyTLD Admin',
            '--email' => 'install@registry.test',
            '--password' => 'SecurePass123!',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'install@registry.test',
            'is_admin' => true,
        ]);
    }

    public function test_install_command_updates_existing_user(): void
    {
        User::factory()->create([
            'email' => 'existing@registry.test',
            'name' => 'Old Name',
            'is_admin' => false,
        ]);

        $this->artisan('app:install', [
            '--no-seed' => true,
            '--name' => 'New Name',
            '--email' => 'existing@registry.test',
            '--password' => 'NewSecurePass123!',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'existing@registry.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('New Name', $user->name);
        $this->assertTrue($user->isAdmin());
        $this->assertTrue(Hash::check('NewSecurePass123!', $user->password));
    }

    public function test_install_command_fails_on_invalid_data(): void
    {
        $this->artisan('app:install', [
            '--no-seed' => true,
            '--name' => '',
            '--email' => 'invalid-email',
            '--password' => '',
        ])->assertFailed();
    }
}
