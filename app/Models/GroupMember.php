<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
<<<<<<< HEAD
=======
use Illuminate\Database\Eloquent\Relations\BelongsTo;
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)

class GroupMember extends Model
{
    use HasFactory;
<<<<<<< HEAD
    protected $fillable = [
        'user_id',
        'group_id',
        'role',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
=======

    protected $fillable = [
        'group_id',
        'user_id',
        'role',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
>>>>>>> 5916f9f (feat: group routes, promote endpoint, show payload, and schema updates)
}
