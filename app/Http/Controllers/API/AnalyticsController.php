<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Package;
use Illuminate\Support\Facades\DB;
use App\Models\PackageOption;

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

        // Sentiment ratio
        $sentiments = Reservation::selectRaw('sentiment_analysis, COUNT(*) as count')
            ->whereNotNull('sentiment_analysis')
            ->groupBy('sentiment_analysis')
            ->pluck('count', 'sentiment_analysis');

        $totalSentiments = $sentiments->sum();
        $sentimentRatios = [
            'positive' => $totalSentiments > 0 ? round(($sentiments['Positive'] ?? 0) / $totalSentiments * 100, 2) : 0,
            'neutral' => $totalSentiments > 0 ? round(($sentiments['Neutral'] ?? 0) / $totalSentiments * 100, 2) : 0,
            'negative' => $totalSentiments > 0 ? round(($sentiments['Negative'] ?? 0) / $totalSentiments * 100, 2) : 0,
        ];

        // Best package option by reservations & reviews
        $bestPackageOption = PackageOption::withCount(['reservations as reservations_count' => function ($query) {
                $query->where('status', 'completed');
            }])
            ->withCount(['reservations as reviews_count' => function ($query) {
                $query->whereNotNull('review_text');
            }])
            ->orderByDesc('reservations_count')
            ->first();

        // Monthly revenue for last 12 months
        $monthlyRevenue = Reservation::selectRaw("DATE_FORMAT(reservation_datetime, '%Y-%m') as month, SUM(price_purchased) as revenue")
            ->where('status', 'completed')
            ->whereNotNull('reservation_datetime')
            ->where('reservation_datetime', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recentReservations' => $recentReservations,
                'sentimentRatios' => $sentimentRatios,
                'bestPackageOption' => $bestPackageOption ? [
                    'id' => $bestPackageOption->id,
                    'name' => $bestPackageOption->name,
                    'description' => $bestPackageOption->description,
                    'reservations_count' => $bestPackageOption->reservations_count,
                    'reviews_count' => $bestPackageOption->reviews_count,
                ] : null,
                'monthlyRevenue' => $monthlyRevenue,
            ],
        ]);
    }


}
