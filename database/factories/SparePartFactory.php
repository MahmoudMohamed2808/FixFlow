<?php

namespace Database\Factories;

use App\Models\SparePart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SparePart>
 */
class SparePartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$name, $unit, $price] = fake()->randomElement([
            ['فلتر زيت', 'قطعة', [250, 900]],
            ['فلتر هواء', 'قطعة', [180, 700]],
            ['سير ناقل مطاطي', 'متر', [400, 1500]],
            ['رولمان بلي 6204', 'قطعة', [150, 600]],
            ['كونتاكتور 32 أمبير', 'قطعة', [800, 2500]],
            ['فيوز حراري', 'قطعة', [40, 200]],
            ['لمبة إشارة LED', 'قطعة', [25, 120]],
            ['زيت هيدروليك 46', 'لتر', [120, 350]],
            ['شحم حراري', 'علبة', [200, 600]],
            ['طقم مسامير استانلس', 'علبة', [100, 450]],
            ['مضخة مياه 2 حصان', 'قطعة', [3000, 9000]],
            ['موتور 5.5 كيلووات', 'قطعة', [8000, 25000]],
            ['حساس حرارة PT100', 'قطعة', [500, 1800]],
            ['بريكر 63 أمبير', 'قطعة', [600, 2000]],
        ]);

        return [
            'name' => $name,
            'code' => fake()->unique()->bothify('SP-####'),
            'unit' => $unit,
            'quantity_in_stock' => fake()->numberBetween(20, 300),
            'minimum_quantity' => fake()->numberBetween(3, 15),
            'unit_price' => fake()->randomFloat(2, $price[0], $price[1]),
            'supplier_id' => null,
        ];
    }
}
