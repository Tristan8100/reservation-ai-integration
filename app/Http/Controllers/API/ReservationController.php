<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Reservation;
use App\Models\PackageOption;
use App\Models\User;
use Illuminate\Validation\Rule;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

class ReservationController extends Controller
{
    // Get all reservations (with optional filters)
    public function index(Request $request)
    {
        $query = Reservation::with(['user', 'packageOption.package']);
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        $reservations = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $reservations
        ]);
    }

    // Get single reservation
    public function show($id)
    {
        $reservation = Reservation::with(['user', 'packageOption.package'])->find($id);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Reservation not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $reservation
        ]);
    }

    // Create new reservation
    public function store(Request $request)
    {
        $validated = $request->validate([
            'package_option_id' => 'required|exists:package_options,id',
            'reservation_datetime' => 'required|date|after:now',
            'address' => 'required|string|max:255',          // Added
        ]);

        try {
            // Get current price from package option to prevent manipulation
            $packageOption = PackageOption::find($validated['package_option_id']);
            
            $reservation = Reservation::create([
                'user_id' => Auth::id(),
                'package_option_id' => $validated['package_option_id'],
                'status' => 'pending',
                'price_purchased' => $packageOption->price, // Use actual price, not user input
                'reservation_datetime' => $validated['reservation_datetime'],
                'address' => $validated['address'],         // Added
            ]);

            return response()->json([
                'success' => true,
                'data' => $reservation->load(['user', 'packageOption.package']),
                'message' => 'Reservation created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Reservation creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update reservation (status)
    public function updateStatus(Request $request, $id)
    {
        $reservation = Reservation::where('id', $id)->where('user_id', Auth::id())->first();
        if (!$reservation) {
            return response()->json(['success' => false, 'message' => 'Reservation not found'], 404);
        }

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'confirmed', 'cancelled', 'completed'])],
        ]);

        // Only allow reviews for completed reservations
        if (isset($validated['rating']) && $reservation->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Can only review completed reservations'
            ], 422);
        }

        $reservation->update($validated);

        return response()->json([
            'success' => true,
            'data' => $reservation->load(['user', 'packageOption.package']),
            'message' => 'Reservation updated successfully'
        ]);
    }

    // Delete reservation
    public function destroy($id)
    {
        $reservation = Reservation::find($id);
        if (!$reservation) {
            return response()->json(['success' => false, 'message' => 'Reservation not found'], 404);
        }

        // Prevent deletion of confirmed/completed reservations
        if (in_array($reservation->status, ['confirmed', 'completed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete confirmed or completed reservations'
            ], 422);
        }

        $reservation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reservation deleted successfully'
        ]);
    }

    // Get one user's reservations
    public function userReservations($userId)
    {
        $reservations = Reservation::with(['packageOption.package'])
            ->where('user_id', $userId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reservations
        ]);
    }

    // Get Authenticated user's reservations
    public function authUserReservations()
    {
        $reservations = Reservation::with(['packageOption.package'])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reservations
        ]);
    }

    public function authUserReservationsStatus(Request $request)
    {
        $query = Reservation::with(['packageOption.package'])
            ->where('user_id', Auth::id())
            ->latest();

        // Optional status filter
        if ($request->has('status') && in_array($request->status, ['pending', 'confirmed', 'cancelled', 'completed'])) {
            $query->where('status', $request->status);
        }

        $reservations = $query->get();

        return response()->json([
            'success' => true,
            'data' => $reservations
        ]);
    }

    public function cancel($id)
    {
        $reservation = Reservation::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Reservation not found.'
            ], 404);
        }

        if ($reservation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending reservations can be cancelled.'
            ], 400);
        }

        $reservation->status = 'cancelled';
        $reservation->save();

        return response()->json([
            'success' => true,
            'message' => 'Reservation cancelled successfully.',
            'data' => $reservation
        ]);
    }



    // Submit review for a reservation
    public function submitReview(Request $request, $id)
    {
        $reservation = Reservation::where('id', $id)->where('user_id', Auth::id())->first();
        if (!$reservation) {
            return response()->json(['success' => false, 'message' => 'Reservation not found'], 404);
        }

        if ($reservation->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Can only review completed reservations'
            ], 422);
        }

        $validated = $request->validate([
            'review_text' => 'required|string',
            'rating' => 'required|integer|between:1,5',
            //'sentiment_analysis' => ['required', Rule::in(['Positive', 'Neutral', 'Negative'])], //HARDCODED FIRST, WILL CALL AI
        ]);

        $schema = new ObjectSchema(
        name: 'reservation_review',
        description: 'A structured review of user reservation experience',
        properties: [
            new StringSchema('sentiment', 'the sentiment of the review: Positive, Neutral, or Negative'),
        ],
        requiredFields: ['sentiment']);

        $response = Prism::structured()
            ->using(Provider::Gemini, 'gemini-2.0-flash')
            ->withSchema($schema)
            ->withPrompt($validated['review_text'])
            ->asStructured();

        $data = $reservation->update([
            'review_text'        => $validated['review_text'],
            'rating'             => $validated['rating'],
            'sentiment_analysis' => $response->structured['sentiment'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Review submitted successfully'
        ]);
    }
}
