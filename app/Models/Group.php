<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
<<<<<<< HEAD
=======
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
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

<<<<<<< HEAD
    public function members()
=======
    protected $casts = [
        'target_amount' => 'decimal:2',
        'contribution_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Group $group): void {
            if ($group->invite_code) {
                return;
            }

            do {
                $code = Str::upper(Str::random(6));
            } while (Group::query()->where('invite_code', $code)->exists());

            $group->invite_code = $code;
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
    {
        return $this->hasMany(GroupMember::class);
    }

<<<<<<< HEAD
    public function contributions()
    {
        return $this->hasMany(Contribution::class);
    }

    public function cycles()
=======
    public function cycles(): HasMany
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
    {
        return $this->hasMany(ContributionCycle::class);
    }

<<<<<<< HEAD
    public function loans()
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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($group) {
            if (empty($group->invite_code)) {
                $group->invite_code = static::generateUniqueInviteCode();
            }

            if (auth()->check() && !$group->created_by) {
                $group->created_by = auth()->id();
            }
        });
    }

    protected static function generateUniqueInviteCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (static::where('invite_code', $code)->exists());

        return $code;
=======
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
    }
}
