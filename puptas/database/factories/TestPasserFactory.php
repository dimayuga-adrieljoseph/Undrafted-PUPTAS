<?php

namespace Database\Factories;

use App\Models\TestPasser;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestPasserFactory extends Factory
{
    protected $model = TestPasser::class;

    public function definition()
    {
        return [
            'surname' => $this->faker->lastName,
            'first_name' => $this->faker->firstName,
            'middle_name' => $this->faker->optional()->firstName,
            'strand' => $this->faker->randomElement(['STEM', 'ABM', 'HUMSS', 'GAS']),
            'year_graduated' => $this->faker->year(),
            'email' => $this->faker->unique()->safeEmail,
            'reference_number' => $this->faker->unique()->numerify('REF-######'),
            'batch_number' => $this->faker->randomElement(['1', '2', '3']),
            'school_year' => $this->faker->randomElement(['2023-2024', '2024-2025', '2025-2026']),
            'pupcet_total_score' => $this->faker->randomFloat(2, 60, 100),
            'passer_status_id' => $this->faker->randomElement([1, 2, 3]), // 1=qualified, 2=waitlisted, 3=unqualified
            'status' => 'active',
        ];
    }
}