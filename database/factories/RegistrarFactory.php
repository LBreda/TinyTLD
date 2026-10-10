<?php

namespace Database\Factories;

use App\Enums\RegistrarStatus;
use App\Models\Registrar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registrar>
 */
class RegistrarFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'handle' => strtoupper(fake()->unique()->bothify('REG-####')),
            'iana_id' => fake()->unique()->numberBetween(1000, 99999),
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'phone' => '+39.'.fake()->numerify('##########'),
            'url' => fake()->url(),
            'abuse_email' => fake()->safeEmail(),
            'abuse_phone' => '+39.'.fake()->numerify('##########'),
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'country_code' => 'IT',
            'status' => RegistrarStatus::Active,
        ];
    }
}
