<?php

namespace Database\Factories;

use App\Models\RecyclableItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecyclableItemCategory>
 */
class RecyclableItemCategoryFactory extends Factory
{
    protected $model = RecyclableItemCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word() . ' Category',
            'value' => fake()->numberBetween(5, 50),
        ];
    }

    /**
     * Set a specific point value for the category.
     */
    public function withValue(int $value): static
    {
        return $this->state(fn(array $attributes) => [
            'value' => $value,
        ]);
    }
}
