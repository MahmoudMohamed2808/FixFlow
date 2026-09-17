<?php

namespace Database\Factories;

use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderAttachment>
 */
class WorkOrderAttachmentFactory extends Factory
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
            'file_path' => 'attachments/'.fake()->bothify('report-####').'.pdf',
        ];
    }
}
