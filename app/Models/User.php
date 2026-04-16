<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        'reviews_public',
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
        'reviews_public'    => 'boolean',
        'social_links'      => 'array',
    ];

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
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
