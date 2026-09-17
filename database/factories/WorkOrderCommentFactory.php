<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderComment>
 */
class WorkOrderCommentFactory extends Factory
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
            'comment' => fake()->randomElement([
                'تم المعاينة وجارٍ توفير قطعة الغيار.',
                'العطل اتصلّح وجارٍ التجربة قبل التسليم.',
                'محتاجين موافقة العميل على تغيير الموتور.',
                'تم التسليم للعميل بعد التجربة بحضوره.',
                'في انتظار وصول الفلتر من المورد.',
                'تم عمل اللازم والوردية استلمت المعدة شغالة.',
            ]),
        ];
    }
}
