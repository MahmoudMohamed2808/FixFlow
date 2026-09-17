<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\DowntimeRecord;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DowntimeRecord>
 */
class DowntimeRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-90 days', 'now');

        return [
            'asset_id' => Asset::factory(),
            'work_order_id' => WorkOrder::factory(),
            'start_time' => $start,
            'end_time' => (clone $start)->modify('+'.fake()->numberBetween(2, 48).' hours'),
            'duration_minutes' => null,
        ];
    }
}
