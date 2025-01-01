<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivationLicense extends Model
{

    use HasFactory;
    protected $table = "activationlicenses";

    protected $fillable = ['code', 'status', 'type', 'subscriptions_days'];

}
