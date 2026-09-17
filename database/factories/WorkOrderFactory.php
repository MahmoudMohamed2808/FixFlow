<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['corrective', 'corrective', 'corrective', 'preventive']);
        $description = $type === 'preventive'
            ? fake()->randomElement([
                'صيانة وقائية دورية — تغيير فلاتر وتنظيف عام',
                'فحص دوري شامل وتشحيم الأجزاء المتحركة',
                'معايرة الحساسات وفحص لوحة التحكم',
                'مراجعة الأحزمة والسيور وشدّها',
            ])
            : fake()->randomElement([
                'عطل مفاجئ — الماكينة بتفصل بعد التشغيل بدقائق',
                'صوت عالي من الموتور مع اهتزاز واضح',
                'تسريب زيت من الجانب الأيمن للمعدة',
                'ارتفاع حرارة الكمبروسور عن المعدل الطبيعي',
                'لوحة التحكم مش بتستجيب لأوامر التشغيل',
                'ضعف ضغط الطلمبة وخروج مياه متقطعة',
                'المولد مش بيقوم مع أول تشغيلة الصبح',
                'بلاغ من الوردية الليلية: توقف خط الإنتاج',
            ]);

        return [
            'asset_id' => Asset::factory(),
            'client_id' => null,
            'code' => fake()->unique()->numerify('WO-####'),
            'type' => $type,
            'priority' => fake()->randomElement(['low', 'medium', 'medium', 'high', 'urgent']),
            'status' => fake()->randomElement(['open', 'assigned', 'in_progress', 'completed', 'completed']),
            'description' => $description,
            'reported_by' => null,
            'assigned_to' => null,
            'schedule_id' => null,
            'scheduled_date' => fake()->optional(0.8)->dateTimeBetween('-4 months', '+1 month'),
            'started_at' => null,
            'completed_at' => null,
            'cost' => null,
        ];
    }

    public function corrective(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'corrective',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    }
}
