<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{

    protected $table = 'reservations';

    protected $fillable = [
        'user_id',
        'package_option_id',
        'status',
        'price_purchased',
        'reservation_datetime',
        'review_text',
        'rating',
        'sentiment_analysis',
    ];

    protected $casts = [
        'reservation_datetime' => 'datetime',
        'rating' => 'integer',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function packageOption()
    {
        return $this->belongsTo(PackageOption::class);
    }

    // Helpers
    public function isReviewed(): bool
    {
        return !is_null($this->rating);
    }
}
