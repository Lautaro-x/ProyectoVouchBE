<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomTrailerItem extends Model
{
    protected $fillable = ['name', 'youtube_url', 'sort_order'];
}
