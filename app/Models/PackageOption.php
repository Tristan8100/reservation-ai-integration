<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageOption extends Model
{

    protected $table = 'package_options';

    protected $fillable = [
        'package_id',
        'name',
        'description',
        'price',
        'picture_url',
        'analysis',
        'recommendation',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
