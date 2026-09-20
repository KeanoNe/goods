<?php

namespace Database\Factories;

use App\Models\Rack;
use App\Models\Shelf;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shelf>
 */
class ShelfFactory extends Factory
{
    /**
     * Definiert den Standardzustand des Modells.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Fach '.fake()->unique()->numerify('##'),
            'description' => fake()->sentence(),
            'rack_id' => Rack::factory(),
            'notes' => null,
        ];
    }
}
