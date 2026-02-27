<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
<<<<<<< HEAD
=======
use Illuminate\Database\Eloquent\Relations\BelongsTo;
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)

class Contribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'cycle_id',
        'user_id',
        'amount_paid',
        'status',
        'paid_at',
        'marked_by',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

<<<<<<< HEAD
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
=======
    public function group(): BelongsTo
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
    {
        return $this->belongsTo(Group::class);
    }

<<<<<<< HEAD
    public function cycle()
=======
    public function cycle(): BelongsTo
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
    {
        return $this->belongsTo(ContributionCycle::class, 'cycle_id');
    }

<<<<<<< HEAD
    public function marker()
=======
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function marker(): BelongsTo
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
