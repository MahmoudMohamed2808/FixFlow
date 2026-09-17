<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $clients = [
            ['name' => 'م. أحمد النجار', 'company' => 'النور للصناعات الغذائية', 'phone' => '01001112233', 'email' => 'a.naggar@elnour-foods.test', 'address' => 'مدينة 6 أكتوبر، المنطقة الصناعية الثالثة', 'notes' => 'عميل صيانة سنوية — خطوط التعبئة'],
            ['name' => 'م. محمد عبد العزيز', 'company' => 'الدلتا للمقاولات', 'phone' => '01112223334', 'email' => 'm.abdelaziz@delta-contracting.test', 'address' => 'مدينة العاشر من رمضان، المنطقة الصناعية A2', 'notes' => 'مولدات ومعدات مواقع'],
            ['name' => 'م. كريم فؤاد', 'company' => 'المصرية للتعبئة والتغليف', 'phone' => '01223334445', 'email' => 'karim@egy-pack.test', 'address' => 'مدينة بدر، المنطقة الصناعية', 'notes' => null],
            ['name' => 'م. طارق الشريف', 'company' => 'العبور للصناعات الهندسية', 'phone' => '01533445566', 'email' => 'tarek@elobour-eng.test', 'address' => 'العبور، المنطقة الصناعية الأولى', 'notes' => null],
            ['name' => 'د. هاني عزت', 'company' => 'النيل للأدوية', 'phone' => '01044556677', 'email' => 'hany@elnile-pharma.test', 'address' => 'برج العرب، المنطقة الصناعية، الإسكندرية', 'notes' => 'تكييف مركزي وغرف تبريد'],
            ['name' => 'م. وليد حمدي', 'company' => 'القاهرة للصناعات المعدنية', 'phone' => '01155667788', 'email' => 'walid@cairo-metal.test', 'address' => 'شبرا الخيمة، المنطقة الصناعية', 'notes' => null],
            ['name' => 'أ. منى فاروق', 'company' => 'الشرق للمنسوجات', 'phone' => '01266778899', 'email' => 'mona@elsharq-textile.test', 'address' => 'المحلة الكبرى، الغربية', 'notes' => null],
            ['name' => 'م. سامح رضوان', 'company' => 'الصفا للألبان', 'phone' => '01577889900', 'email' => 'sameh@elsafa-dairy.test', 'address' => 'مدينة السادات، المنطقة الصناعية', 'notes' => 'غلايات وخطوط بسترة'],
            ['name' => 'م. شريف منير', 'company' => 'الخليج للتبريد والتكييف', 'phone' => '01088990011', 'email' => 'sherif@elkhaleeg-hvac.test', 'address' => 'فيصل، الجيزة', 'notes' => null],
            ['name' => 'ورشة الإصلاح الداخلي', 'company' => null, 'phone' => '01299001122', 'email' => 'workshop.internal@fixflow.test', 'address' => 'مصنع 6 أكتوبر', 'notes' => 'جهة داخلية للأوامر غير المحاسبية'],
        ];

        foreach ($clients as $client) {
            Client::firstOrCreate(
                ['email' => $client['email']],
                [
                    'name' => $client['name'],
                    'company_name' => $client['company'],
                    'phone' => $client['phone'],
                    'address' => $client['address'],
                    'notes' => $client['notes'],
                ]
            );
        }
    }
}
