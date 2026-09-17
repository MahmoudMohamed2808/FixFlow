<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $first = fake()->randomElement([
            'أحمد', 'محمود', 'مصطفى', 'كريم', 'إبراهيم', 'السيد', 'حسن', 'علي',
            'محمد', 'عمر', 'خالد', 'وليد', 'طارق', 'شريف', 'هاني', 'ياسر',
            'منى', 'هبة', 'دينا', 'رانيا', 'سارة', 'مروة', 'آية', 'نورا',
        ]);
        $last = fake()->randomElement([
            'محمد', 'أحمد', 'السيد', 'إبراهيم', 'عبد الله', 'حسن', 'علي',
            'فؤاد', 'عبد الرحمن', 'الشريف', 'حمدي', 'عزت', 'فراج', 'رمضان',
        ]);

        return [
            'name' => trim("{$first} {$last}"),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'department_id' => null,
            'phone' => fake()->randomElement(['010', '011', '012', '015']).fake()->numerify('########'),
            'is_active' => true,
            'hourly_rate' => fake()->randomElement([120, 150, 180, 200, 250, 300]),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
