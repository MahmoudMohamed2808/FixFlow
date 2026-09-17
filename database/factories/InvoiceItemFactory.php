<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->randomElement([
                'أجور صيانة وإصلاح المعدة',
                'توريد وتركيب قطع غيار',
                'صيانة وقائية دورية شاملة',
                'كشف ومعاينة فنية',
                'توريد فلتر زيت + مصنعية التركيب',
                'إصلاح لوحة التحكم وبرمجتها',
                'نقل وتركيب وتشغيل',
            ]),
            'quantity' => fake()->randomFloat(2, 1, 5),
            'unit_price' => fake()->randomFloat(2, 200, 8000),
            // 'total' يُحسب تلقائيًا من الكمية × السعر عبر saving hook
        ];
    }
}
