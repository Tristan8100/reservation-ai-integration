<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::withCount('reservations');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $request->query('per_page', 10);
        $users = $query->paginate($perPage);

        return response()->json($users);
    }

    public function show($id)
    {
        $user = User::with([
            'reservations.packageOption.package'
        ])->findOrFail($id);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at,
            'reservations_count' => $user->reservations->count(),
            'reservations' => $user->reservations->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'status' => $reservation->status,
                    'price_purchased' => $reservation->price_purchased,
                    'reservation_datetime' => $reservation->reservation_datetime,
                    'address' => $reservation->address,
                    'review_text' => $reservation->review_text,
                    'rating' => $reservation->rating,
                    'sentiment_analysis' => $reservation->sentiment_analysis,
                    'package_option_id' => $reservation->package_option_id,
                ];
            }),
        ]);
    }
}
