<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'target_amount',
        'contribution_amount',
        'frequency',
        'status',
        'invite_code',
        'created_by',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'contribution_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Group $group): void {
            if (! $group->invite_code) {
                do {
                    $code = Str::upper(Str::random(6));
                } while (Group::query()->where('invite_code', $code)->exists());

                $group->invite_code = $code;
            }

            if (auth()->check() && ! $group->created_by) {
                $group->created_by = auth()->id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(ContributionCycle::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function periodStart($date = null)
    {
        $date = $date ?? now();

        return match ($this->frequency) {
            'daily' => $date->copy()->startOfDay(),
            'weekly' => $date->copy()->startOfWeek(),
            'monthly' => $date->copy()->startOfMonth(),
            default => $date->copy()->startOfMonth(),
        };
    }

    public function periodEnd($date = null)
    {
        $date = $date ?? now();

        return match ($this->frequency) {
            'daily' => $date->copy()->endOfDay(),
            'weekly' => $date->copy()->endOfWeek(),
            'monthly' => $date->copy()->endOfMonth(),
            default => $date->copy()->endOfMonth(),
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
}
