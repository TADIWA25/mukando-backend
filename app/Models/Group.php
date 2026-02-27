<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function members()
    {
        return $this->hasMany(GroupMember::class);
    }

    public function contributions()
    {
        return $this->hasMany(Contribution::class);
    }

    public function cycles()
    {
        return $this->hasMany(ContributionCycle::class);
    }

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
    }
}
