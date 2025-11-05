<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OAuthState extends Model
{
    protected $table = 'oauth_states';

    protected $fillable = [
        'state',
        'shop_domain',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
