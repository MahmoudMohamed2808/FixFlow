<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\PreventiveSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreventiveSchedule>
 */
class PreventiveScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'title' => fake()->randomElement([
                'صيانة شهرية — تنظيف وتشحيم',
                'فحص ربع سنوي شامل',
                'تغيير فلاتر دوري',
                'معايرة سنوية للحساسات',
                'فحص أسبوعي سريع',
            ]),
            'frequency_type' => fake()->randomElement(['weekly', 'monthly', 'monthly', 'yearly']),
            'frequency_value' => 1,
            'last_generated_date' => null,
            'next_due_date' => now()->addMonth(),
            'is_active' => true,
        ];
    }

    public function due(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_due_date' => now()->subDay(),
        ]);
    }
}
