<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => $this->faker->unique()->slug(2),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'notes' => null,
        ];
    }
}
