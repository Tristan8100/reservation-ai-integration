<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{

    protected $table = 'packages';

    protected $fillable = [
        'name',
        'description',
        'picture_url',
        'analysis',
        'recommendation',
    ];

    public function options()
    {
        return $this->hasMany(PackageOption::class);
    }
}
