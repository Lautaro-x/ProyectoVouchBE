<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'role',
        'badges',
        'banned_at',
        'ban_reason',
        'show_email',
        'social_links',
        'card_big_bg',
        'card_mid_bg',
        'card_mini_bg',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'badges'            => 'array',
        'banned_at'         => 'datetime',
        'show_email'        => 'boolean',
        'social_links'      => 'array',
    ];

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function cardData(): array
    {
        $lastReviews = $this->reviews()
            ->whereHas('product', fn($q) => $q->where('type', 'game'))
            ->with(['product:id,type,title,slug,cover_image'])
            ->whereNull('banned_at')
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(fn($r) => [
                'weighted_score' => $r->weighted_score,
                'letter_grade'   => $r->letter_grade,
                'product'        => [
                    'title'       => $r->product->title,
                    'slug'        => $r->product->slug,
                    'type'        => $r->product->type,
                    'cover_image' => $r->product->cover_image,
                ],
            ]);

        $this->loadCount([
            'reviews as reviews_count' => fn($q) => $q->whereNull('banned_at'),
            'followers as followers_count',
        ]);

        $sharedSocials = collect($this->social_links ?? [])
            ->filter(fn($link) => !empty($link['url']) && ($link['shared'] ?? false))
            ->map(fn($link) => $link['url']);

        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'avatar'          => $this->avatar,
            'email'           => $this->show_email ? $this->email : null,
            'badges'          => $this->badges ?? [],
            'social_links'    => $sharedSocials,
            'reviews_count'   => $this->reviews_count,
            'followers_count' => $this->followers_count,
            'last_reviews'    => $lastReviews,
            'card_big_bg'     => $this->card_big_bg,
            'card_mid_bg'     => $this->card_mid_bg,
            'card_mini_bg'    => $this->card_mini_bg,
        ];
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'Follows', 'follower_id', 'followed_id')
            ->withTimestamps('created_at', 'created_at');
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'Follows', 'followed_id', 'follower_id')
            ->withTimestamps('created_at', 'created_at');
    }
}
