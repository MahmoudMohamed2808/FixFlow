<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-60 days', 'now');

        return [
            'title' => 'ميزانية صيانة '.fake()->year(),
            'department_id' => null,
            'period_start' => $start->format('Y-m-d'),
            'period_end' => (clone $start)->modify('+90 days')->format('Y-m-d'),
            'allocated_amount' => fake()->randomFloat(2, 10000, 200000),
            'notes' => null,
        ];
    }

    public function forDepartment(Department $department): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $department->id,
        ]);
    }
}
