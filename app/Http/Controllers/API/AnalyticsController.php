<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Package;

class AnalyticsController extends Controller
{
    public function index()
    {
        // Dashboard stats
        $stats = [
            'totalReservations' => Reservation::count(),
            'activeUsers' => User::count(),
            'totalPackages' => Package::count(),
            'revenue' => Reservation::where('status', 'completed')->sum('price_purchased'),
        ];

        // Recent reservations (latest 5)
        $recentReservations = Reservation::with(['user', 'packageOption.package'])
            ->orderBy('reservation_datetime', 'desc')
            ->take(5)
            ->get()
            ->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'user' => $reservation->user?->name ?? 'Unknown',
                    'package' => $reservation->packageOption?->package?->name ?? 'N/A',
                    'status' => $reservation->status,
                    'date' => $reservation->reservation_datetime
                        ? $reservation->reservation_datetime->format('Y-m-d')
                        : $reservation->created_at->format('Y-m-d'),
                    'amount' => $reservation->price_purchased ?? 0,
                    'reviewed' => $reservation->isReviewed(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recentReservations' => $recentReservations,
            ],
        ]);
    }
}
