<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
<<<<<<< HEAD
=======
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)

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
<<<<<<< HEAD
        'due_date' => 'date',
    ];

    public function group()
=======
        'due_date' => 'date:Y-m-d',
    ];

    public function group(): BelongsTo
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
    {
        return $this->belongsTo(Group::class);
    }

<<<<<<< HEAD
    public function contributions()
=======
    public function contributions(): HasMany
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
    {
        return $this->hasMany(Contribution::class, 'cycle_id');
    }
}
