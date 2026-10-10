<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:install {--force : Force the operation to run when in production} {--no-seed : Do not run database seeders} {--name= : Name of the initial administrator} {--email= : Email of the initial administrator} {--password= : Password of the initial administrator}')]
#[Description('Install TinyTLD: run database migrations, seeds, and register the initial administrator')]
class InstallCommand extends Command
{
    /**
     * The console command aliases.
     *
     * @var array<int, string>
     */
    protected $aliases = ['install', 'tinytld:install', 'tinytld:setup'];

    public function handle(): int
    {
        $this->components->info('Starting TinyTLD installation...');

        if ($this->laravel->isProduction() && ! $this->option('force')) {
            if (! $this->confirm('Application is in production mode. Do you really wish to proceed with the installation?')) {
                $this->components->warn('Installation aborted.');

                return self::FAILURE;
            }
        }

        $this->components->info('Running database migrations...');
        $migrationResult = $this->call('migrate', ['--force' => true]);

        if ($migrationResult !== self::SUCCESS) {
            $this->components->error('Database migration failed.');

            return self::FAILURE;
        }

        if (! $this->option('no-seed')) {
            $this->components->info('Seeding database...');
            $seedResult = $this->call('db:seed', ['--force' => true]);

            if ($seedResult !== self::SUCCESS) {
                $this->components->error('Database seeding failed.');

                return self::FAILURE;
            }
        }

        $this->components->info('Registering initial administrator user...');

        $name = $this->option('name');
        $email = $this->option('email');
        $password = $this->option('password');

        if ($this->input->isInteractive()) {
            if ($name === null) {
                $name = text(
                    label: 'What is the administrator name?',
                    default: 'Administrator',
                    required: true,
                    validate: fn (string $value) => match (true) {
                        trim($value) === '' => 'The name field is required.',
                        strlen($value) > 255 => 'The name must not exceed 255 characters.',
                        default => null,
                    },
                );
            }

            if ($email === null) {
                $email = text(
                    label: 'What is the administrator email?',
                    placeholder: 'admin@example.com',
                    required: true,
                    validate: function (string $value) {
                        $validator = Validator::make(['email' => $value], [
                            'email' => ['required', 'string', 'email', 'max:255'],
                        ]);

                        return $validator->fails() ? $validator->errors()->first('email') : null;
                    },
                );
            }

            if ($password === null) {
                $password = password(
                    label: 'What is the administrator password?',
                    required: true,
                    validate: function (string $value) {
                        $validator = Validator::make(['password' => $value], [
                            'password' => ['required', 'string', Password::default()],
                        ]);

                        return $validator->fails() ? $validator->errors()->first('password') : null;
                    },
                );
            }
        }

        $name = is_string($name) ? $name : '';
        $email = is_string($email) ? $email : '';
        $password = is_string($password) ? $password : '';

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::default()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        /** @var string $name */
        /** @var string $email */
        /** @var string $password */
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = $password;
        $user->is_admin = true;
        $user->registrar_id = null;
        $user->email_verified_at = now();
        $user->save();

        $this->components->twoColumnDetail('Admin Name', $user->name);
        $this->components->twoColumnDetail('Admin Email', $user->email);
        $this->components->twoColumnDetail('Role', 'Registry Administrator');

        $this->components->success('TinyTLD installation completed successfully!');

        return self::SUCCESS;
    }
}
