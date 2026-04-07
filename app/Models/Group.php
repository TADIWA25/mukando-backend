<?php

namespace App\Models;

use Carbon\Carbon;
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
        'start_date',
        'status',
        'total_collected',
        'interest_rate',
        'invite_code',
        'created_by',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'contribution_amount' => 'decimal:2',
        'start_date' => 'date',
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

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function contributionCycles(): HasMany
    {
        return $this->hasMany(ContributionCycle::class);
    }

    public function overdues(): HasMany
    {
        return $this->hasMany(Overdue::class);
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

    public function ensureContributionCyclesThrough($date = null): void
    {
        if ($this->type === 'shared') {
            return;
        }

        $targetDate = Carbon::parse($date ?? now())->startOfDay();
        $cycleStart = Carbon::parse($this->start_date ?? $this->created_at ?? now())->startOfDay();

        if ($cycleStart->gt($targetDate)) {
            return;
        }

        $existingCycleNumbers = $this->contributionCycles()
            ->pluck('cycle_number')
            ->all();

        $existingCycleNumbers = array_flip($existingCycleNumbers);
        $cycleNumber = 1;

        while ($cycleStart->lte($targetDate)) {
            if (! isset($existingCycleNumbers[$cycleNumber])) {
                $dueDate = $this->cycleEndDate($cycleStart);

                $this->contributionCycles()->create([
                    'cycle_number' => $cycleNumber,
                    'due_date' => $dueDate->toDateString(),
                    'status' => $dueDate->lt(now()->startOfDay()) ? 'closed' : 'open',
                ]);
            }

            $cycleStart = $this->advanceCycleStart($cycleStart);
            $cycleNumber++;
        }

        $this->contributionCycles()
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => 'closed']);

        $this->contributionCycles()
            ->whereDate('due_date', '>=', now()->toDateString())
            ->update(['status' => 'open']);
    }

    public function currentContributionCycle(): ?ContributionCycle
    {
        if ($this->type === 'shared') {
            return null;
        }

        $this->ensureContributionCyclesThrough();

        return $this->contributionCycles()
            ->whereDate('due_date', '>=', now()->toDateString())
            ->orderBy('cycle_number')
            ->first();
    }

    private function cycleEndDate(Carbon $cycleStart): Carbon
    {
        return match ($this->frequency) {
            'daily' => $cycleStart->copy()->endOfDay(),
            'weekly' => $cycleStart->copy()->addDays(6)->endOfDay(),
            'monthly' => $cycleStart->copy()->addMonth()->subDay()->endOfDay(),
            default => $cycleStart->copy()->endOfDay(),
        };
    }

    private function advanceCycleStart(Carbon $cycleStart): Carbon
    {
        return match ($this->frequency) {
            'daily' => $cycleStart->copy()->addDay(),
            'weekly' => $cycleStart->copy()->addWeek(),
            'monthly' => $cycleStart->copy()->addMonth(),
            default => $cycleStart->copy()->addMonth(),
        };
    }
}
