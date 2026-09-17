<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'النور للتوريدات العمومية', 'الدلتا لقطع الغيار', 'المصرية للهندسة والتوريدات',
            'العبور للتجارة والتوكيلات', 'المهندس لقطع الغيار', 'النيل للتوريدات الصناعية',
            'التوحيد للمعدات', 'الصفا للتجارة', 'الخليج للتوريدات', 'الأمانة للعدد والأدوات',
        ]);
        $city = fake()->randomElement([
            'العتية، القاهرة', 'شارع الجمهورية، القاهرة', 'المنطقة الصناعية، 6 أكتوبر',
            'فيكتوريا، الإسكندرية', 'طنطا، الغربية', 'المنصورة، الدقهلية',
        ]);

        return [
            'name' => $name.' - '.fake()->randomElement(['فرع القاهرة', 'فرع الجيزة', 'فرع الإسكندرية', 'المركز الرئيسي']),
            'phone' => fake()->randomElement(['010', '011', '012', '015']).fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'address' => $city,
        ];
    }
}
