<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = fake()->randomElement([
            'النور للصناعات الغذائية', 'الدلتا للمقاولات', 'المصرية للتعبئة والتغليف',
            'المهندس للبلاستيك', 'العبور للصناعات الهندسية', 'النيل للأدوية',
            'القاهرة للصناعات المعدنية', 'الشرق للمنسوجات', 'الصفا للألبان',
            'الخليج للتبريد والتكييف', 'المروة للورق والكرتون', 'السلام للسيراميك',
        ]);
        $contact = fake()->randomElement([
            'أحمد النجار', 'محمد عبد العزيز', 'كريم فؤاد', 'طارق الشريف',
            'هاني عزت', 'وليد حمدي', 'منى فاروق', 'دينا سمير',
        ]);
        $city = fake()->randomElement([
            'مدينة 6 أكتوبر، المنطقة الصناعية الثالثة',
            'مدينة العاشر من رمضان، المنطقة الصناعية A2',
            'مدينة بدر، المنطقة الصناعية',
            'برج العرب، المنطقة الصناعية، الإسكندرية',
            'العبور، المنطقة الصناعية الأولى',
            'شبرا الخيمة، المنطقة الصناعية',
            'المعادي، القاهرة', 'فيصل، الجيزة',
        ]);

        return [
            'name' => $contact,
            'company_name' => $company,
            'phone' => fake()->randomElement(['010', '011', '012', '015']).fake()->numerify('########'),
            'email' => fake()->unique()->companyEmail(),
            'address' => $city,
            'notes' => null,
        ];
    }
}
