<?php

namespace Database\Factories;

use App\Models\RecyclableItem;
use App\Models\RecyclableItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecyclableItem>
 */
class RecyclableItemFactory extends Factory
{
    protected $model = RecyclableItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'barcode' => fake()->unique()->ean13(),
            'category_id' => RecyclableItemCategory::factory(),
            'manual_value' => null,
        ];
    }

    /**
     * Set a specific barcode.
     */
    public function withBarcode(string $barcode): static
    {
        return $this->state(fn(array $attributes) => [
            'barcode' => $barcode,
        ]);
    }

    /**
     * Set a manual value override.
     */
    public function withManualValue(int $value): static
    {
        return $this->state(fn(array $attributes) => [
            'manual_value' => $value,
        ]);
    }

    /**
     * Assign to a specific category.
     */
    public function forCategory(RecyclableItemCategory $category): static
    {
        return $this->state(fn(array $attributes) => [
            'category_id' => $category->id,
        ]);
    }
}
