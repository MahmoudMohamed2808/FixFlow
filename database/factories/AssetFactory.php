<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$name, $manufacturer] = fake()->randomElement([
            ['كمبروسور حلزوني 50 حصان', 'Atlas Copco'],
            ['مولد ديزل 250 ك.ف.أ', 'Caterpillar'],
            ['ماكينة تعبئة وتغليف', 'Siemens'],
            ['غلاية بخار 2 طن', 'Bosch'],
            ['طلمبة مياه غاطسة', 'Grundfos'],
            ['تكييف مركزي 20 طن', 'Carrier'],
            ['محول كهرباء 500 ك.ف.أ', 'ABB'],
            ['ونش علوي 5 طن', 'Demag'],
            ['ماكينة حقن بلاستيك', 'Haitian'],
            ['خلاط صناعي 500 لتر', 'Siemens'],
            ['فرن تجفيف صناعي', 'Bosch'],
            ['ماكينة لحام CO2', 'ESAB'],
        ]);

        $purchase = fake()->dateTimeBetween('-5 years', '-3 months');

        return [
            'name' => $name,
            'serial_number' => fake()->unique()->bothify('EG-####-??'),
            'category_id' => AssetCategory::factory(),
            'location_id' => Location::factory(),
            'purchase_date' => $purchase->format('Y-m-d'),
            'warranty_expiry' => fake()->optional(0.4)->dateTimeBetween('-1 year', '+2 years')?->format('Y-m-d'),
            'status' => fake()->randomElement(['operational', 'operational', 'operational', 'under_maintenance', 'broken', 'retired']),
            'manufacturer' => $manufacturer,
            'model' => fake()->bothify('MDL-####'),
            'image' => null,
            'created_by' => null,
        ];
    }
}
