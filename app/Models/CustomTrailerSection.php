<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomTrailerSection extends Model
{
    protected $fillable = ['title', 'is_active'];

    protected $casts = [
        'title'     => 'array',
        'is_active' => 'boolean',
    ];

    public static function instance(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'title' => [
                    'es' => 'Últimos tráilers',
                    'en' => 'Latest trailers',
                    'fr' => 'Dernières bandes-annonces',
                    'pt' => 'Últimos trailers',
                    'it' => 'Ultimi trailer',
                ],
                'is_active' => false,
            ]
        );
    }
}
