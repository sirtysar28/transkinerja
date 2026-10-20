<?php

namespace Database\Factories;

use App\Models\UnitKerja;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        static $n = 0;

        return [
            'name' => fake()->name(),
            'nip' => null,
            'username' => 'user'.(++$n).Str::lower(Str::random(5)),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= 'password',
            'role' => 'pegawai',
            'pangkat_golongan' => 'Penata / IIIc',
            'jabatan' => 'Analis Kinerja',
            'unit_kerja_id' => null,
            'is_aktif' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
