<?php

namespace Database\Factories;

use App\Models\PasserStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class PasserStatusFactory extends Factory
{
    protected $model = PasserStatus::class;

    public function definition()
    {
        return [
            'status' => $this->faker->randomElement(['qualified', 'waitlisted', 'unqualified']),
        ];
    }

    public function qualified()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'qualified',
            ];
        });
    }

    public function waitlisted()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'waitlisted',
            ];
        });
    }

    public function unqualified()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'unqualified',
            ];
        });
    }
}