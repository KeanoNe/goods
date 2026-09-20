<?php

namespace Database\Factories;

use App\Models\Rack;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rack>
 */
class RackFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Regal '.fake()->unique()->bothify('?##'),
            'description' => fake()->sentence(),
            'warehouse_id' => Warehouse::factory(),
            'notes' => null,
        ];
    }
}
