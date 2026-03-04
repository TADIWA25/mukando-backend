<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'group_id',
        'amount',
        'duration_months',
        'reason',
        'requested_due_date',
        'status',
        'review_notes',
        'reviewed_at',
        'loan_id',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'requested_due_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
