<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "slug",
        "description",
        "monthly_price",
        "default_commission_rate",
        "commission_type",
        "fixed_commission_amount",
        "is_active",
        "is_default",
        "sort_order",
    ];

    protected $casts = [
        "monthly_price" => "decimal:2",
        "default_commission_rate" => "decimal:4",
        "fixed_commission_amount" => "decimal:2",
        "is_active" => "boolean",
        "is_default" => "boolean",
    ];

    public const TYPE_PERCENTAGE = 1;
    public const TYPE_FIXED = 2;

    public function subscriptions(): HasMany { return $this->hasMany(DoctorSubscription::class); }

    public function scopeActive($q) { return $q->where("is_active", true); }
    public function scopeDefault($q) { return $q->where("is_default", true); }
}
