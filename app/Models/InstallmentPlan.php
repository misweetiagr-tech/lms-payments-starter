<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallmentPlan extends Model
{
    protected $guarded = [];

    protected $casts = ['enabled' => 'boolean'];
}
