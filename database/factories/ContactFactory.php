<?php

namespace Database\Factories;

use App\Enums\ContactKind;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'handle' => strtoupper(fake()->unique()->bothify('C-######')),
            'kind' => ContactKind::Individual,
            'name' => fake()->name(),
            'organization' => null,
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'country_code' => 'IT',
            'email' => fake()->safeEmail(),
            'phone' => '+39.'.fake()->numerify('##########'),
            'disclose' => [],
        ];
    }

    public function organization(): static
    {
        return $this->state(fn () => [
            'kind' => ContactKind::Organization,
            'organization' => fake()->company(),
            'disclose' => ['name', 'organization', 'address'],
        ]);
    }
}
