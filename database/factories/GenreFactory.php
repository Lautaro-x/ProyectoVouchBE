<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GenreFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ['en' => $name, 'es' => $name, 'fr' => $name, 'pt' => $name, 'it' => $name],
            'slug' => Str::slug($name),
        ];
    }
}
