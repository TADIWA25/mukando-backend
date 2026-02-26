<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    // Group.php
public function members() { return $this->hasMany(GroupMember::class); }
public function contributions() { return $this->hasMany(Contribution::class); }
public function loans() { return $this->hasMany(Loan::class); }
public function periodStart($date=null){
    $date = $date ?? now();

    return match($this->frequency) {
        'weekly' => $date->startOfWeek(),
        'bi-monthly' => $date->day <= 15 ? $date->startOfMonth() : $date->startOfMonth()->addDays(15),
        'monthly' => $date->startOfMonth(),
        'yearly' => $date->startOfYear(),
    };
}
public function periodEnd($date = null) {
    $date = $date ?? now();

    return match($this->frequency) {
        'weekly' => $date->endOfWeek(),
        'bi-monthly' => $date->day <= 15 ? $date->startOfMonth()->addDays(14) : $date->endOfMonth(),
        'monthly' => $date->endOfMonth(),
        'yearly' => $date->endOfYear(),
    };
}

public function currentPeriodStart()
{
    return $this->periodStart(now());
}

public function currentPeriodEnd()
{
    return $this->periodEnd(now());
}
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'contribution_amount',
        'frequency',
        'interest_rate',
        'created_by',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($group) {
            $group->invite_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
            if (auth()->check() && !$group->created_by) {
                $group->created_by = auth()->id();
            }
        });
    }
  
}
