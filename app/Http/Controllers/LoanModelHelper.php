<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'group_id', 'amount', 'interest', 'total_amount', 'due_date', 'status'
    ];

    // Call this when creating a loan
    public static function createLoan($userId, $groupId, $amount, $interestRate, $dueDays)
    {
        $interest = ($amount * $interestRate) / 100;
        $totalAmount = $amount + $interest;

        return self::create([
            'user_id' => $userId,
            'group_id' => $groupId,
            'amount' => $amount,
            'interest' => $interest,
            'total_amount' => $totalAmount,
            'due_date' => now()->addDays($dueDays),
            'status' => 'approved',
        ]);
    }
}