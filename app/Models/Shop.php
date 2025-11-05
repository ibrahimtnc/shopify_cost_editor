<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    protected $fillable = [
        'shop_domain',
        'access_token',
        'scope',
        'installed_at',
        'uninstalled_at',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'uninstalled_at' => 'datetime',
    ];

    /**
     * Access token'ı encrypted olarak sakla
     */
    public function setAccessTokenAttribute($value)
    {
        $this->attributes['access_token'] = encrypt($value);
    }

    /**
     * Access token'ı decrypt ederek döndür
     */
    public function getAccessTokenAttribute($value)
    {
        try {
            return decrypt($value);
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Cost audit logs relationship
     */
    public function costAuditLogs(): HasMany
    {
        return $this->hasMany(CostAuditLog::class);
    }
}
