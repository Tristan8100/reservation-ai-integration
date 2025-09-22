<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

    public function getAllReservation()
    {
        $userId = Auth::id();

        // Count reservations by status
        $statuses = ['pending', 'cancelled', 'completed', 'confirmed'];
        $counts = [];

        foreach ($statuses as $status) {
            $counts[$status] = Reservation::where('user_id', $userId)
                ->where('status', $status)
                ->count();
        }

        // Optionally, get total reservations
        $counts['total'] = array_sum($counts);

        return response()->json($counts);
    }

    public function updateName(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $userId = Auth::id();
        $user = User::findOrFail($userId);
        $user->name = $request->name;
        $user->save();

        return response()->json([
            'message' => 'Name updated successfully',
            'user' => $user
        ]);
    }

    public function change(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $userId = Auth::id();
        $user = User::findOrFail($userId);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 403);
        }

        $user->password = $request->new_password; // Will be hashed due to User cast
        $user->save();

        return response()->json(['message' => 'Password updated successfully']);
    }
}
