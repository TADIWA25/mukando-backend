<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overdue extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'user_id',
        'amount',
        'status',
        'due_date',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
