<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Domain;
use App\Models\Registrar;
use App\Models\Tld;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tld_id' => Tld::factory(),
            'name' => fn (array $attributes) => fake()->unique()->slug(2).'.'.Tld::whereKey($attributes['tld_id'])->firstOrFail()->name,
            'registrant_id' => Contact::factory(),
            'registrar_id' => Registrar::factory(),
            'statuses' => [],
            'auth_code' => Str::random(16),
            'registered_at' => now()->subYear(),
            'expires_at' => now()->addYear(),
        ];
    }

    /**
     * Domain managed directly by the registry (no sponsoring registrar).
     */
    public function withoutRegistrar(): static
    {
        return $this->state(fn () => ['registrar_id' => null]);
    }
}
