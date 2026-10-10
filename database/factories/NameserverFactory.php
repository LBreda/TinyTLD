<?php

namespace Database\Factories;

use App\Models\Nameserver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nameserver>
 */
class NameserverFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'ns'.fake()->unique()->numberBetween(1, 99999).'.'.fake()->domainName(),
        ];
    }
}
