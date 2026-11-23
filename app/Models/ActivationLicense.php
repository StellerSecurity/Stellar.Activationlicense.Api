<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivationLicense extends Model
{
    use HasFactory;

    protected $table = 'activationlicenses';

    protected $fillable = [
        'code',
        'status',
        'type',
        'subscriptions_days',
        'idempotency_key',
        'reseller_user_id',
    ];

    protected $casts = [
        'status' => 'integer',
        'type' => 'integer',
        'subscriptions_days' => 'integer',
        'reseller_user_id' => 'integer',
    ];
}
