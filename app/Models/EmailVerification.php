<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerification extends Model
{
    protected $table = 'email_verifications';

    protected $fillable = ['email', 'otp', 'verified'];
    public $timestamps = true;
}
