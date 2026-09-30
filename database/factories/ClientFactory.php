<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nom' => 'Église '.fake()->unique()->lastName(),
            'pays' => 'RD Congo',
            'ville' => fake()->randomElement(['Kinshasa', 'Lubumbashi', 'Kolwezi']),
        ];
    }
}
