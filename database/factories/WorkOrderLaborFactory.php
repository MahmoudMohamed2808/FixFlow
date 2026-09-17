<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLabor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderLabor>
 */
class WorkOrderLaborFactory extends Factory
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
            'user_id' => User::factory(),
            'hours_spent' => fake()->randomFloat(2, 0.5, 8),
            'hourly_rate' => fake()->randomFloat(2, 50, 300),
            // 'cost' يُحسب تلقائيًا من الساعات × الأجر عبر saving hook
        ];
    }
}
