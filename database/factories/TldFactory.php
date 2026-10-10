<?php

namespace Database\Factories;

use App\Models\Tld;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tld>
 */
class TldFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->lexify('????');

        return [
            'name' => $name,
            'default_ttl' => 3600,
            'soa_mname' => "a.nic.{$name}",
            'soa_rname' => "hostmaster.nic.{$name}",
            'serial' => 0,
            'apex_nameservers' => ["a.nic.{$name}", "b.nic.{$name}"],
        ];
    }
}
