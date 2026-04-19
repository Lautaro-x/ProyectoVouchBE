<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();
        $desc = fake()->sentence();

        return [
            'name'        => ['en' => $name, 'es' => $name, 'fr' => $name, 'pt' => $name, 'it' => $name],
            'description' => ['en' => $desc, 'es' => $desc, 'fr' => $desc, 'pt' => $desc, 'it' => $desc],
            'slug'        => Str::slug($name),
        ];
    }
}
