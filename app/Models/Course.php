<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Course extends Model
{
    protected $guarded = [];

    public function installmentPlan(): HasOne
    {
        return $this->hasOne(InstallmentPlan::class);
    }
}
