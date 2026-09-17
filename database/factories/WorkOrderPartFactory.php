<?php

namespace Database\Factories;

use App\Models\SparePart;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderPart>
 */
class WorkOrderPartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'spare_part_id' => SparePart::factory(),
            'quantity_used' => fake()->numberBetween(1, 4),
            // 'unit_cost' يُفضّل تمريره من سعر القطعة وقت الاستخدام؛
            // إن تُرك null سيُخزَّن 0 لذا نملؤه افتراضيًا بسعر واقعي.
            'unit_cost' => fake()->randomFloat(2, 50, 3000),
        ];
    }
}
