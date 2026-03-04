<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'group_id',
        'amount',
        'interest',
        'total_amount',
        'due_date',
        'status',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Loan $loan) {
            $loan->payments()->delete();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function groupMember(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class, 'user_id', 'user_id', 'group_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    /**
     * Get the total amount paid towards this loan
     */
    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    /**
     * Get the remaining balance on this loan
     */
    public function getRemainingBalanceAttribute(): float
    {
        return (float) $this->total_amount - $this->total_paid;
    }

    /**
     * Check if the loan is fully paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->remaining_balance <= 0;
    }

    /**
     * Check if the loan is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && now()->greaterThan($this->due_date);
    }
}
