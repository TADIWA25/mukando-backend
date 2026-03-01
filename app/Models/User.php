<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
<<<<<<< HEAD
    public function members() { return $this->hasMany(GroupMember::class); }
    public function groups() { return $this->belongsToMany(Group::class, 'group_members')->withPivot('role')->withTimestamps(); }
    public function contributions() { return $this->hasMany(Contribution::class); }
    public function loans() { return $this->hasMany(Loan::class); }
=======
>>>>>>> 694c252 (updated files)
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
<<<<<<< HEAD
        'phone',
=======
        'email',
>>>>>>> 694c252 (updated files)
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
<<<<<<< HEAD
        'password' => 'hashed',
    ];

    /**
     * Get the login username to be used by the controller.
     */
    public function username()
    {
        return 'phone';
    }
=======
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
>>>>>>> 694c252 (updated files)
}
