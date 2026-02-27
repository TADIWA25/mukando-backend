<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContributionCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'cycle_number',
        'due_date',
        'status',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function contributions()
    {
        return $this->hasMany(Contribution::class, 'cycle_id');
    }
}
