<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{

    use HasApiTokens, Notifiable;

    protected $guard = 'admin';

    protected $table = 'admins';

    protected $fillable = [
        'name', 
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];
}
