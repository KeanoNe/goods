<?php

namespace Database\Factories;

use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Artikel '.fake()->unique()->numerify('####'),
            'description' => fake()->sentence(),
            'sku' => fake()->unique()->numerify('SKU-#####'),
            'minimum_stock' => 0,
            'barcode' => fake()->unique()->ean13(),
            'notes' => null,
        ];
    }
}
