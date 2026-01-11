<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PackageOption;
use App\Models\Reservation;
use Cloudinary\Cloudinary;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

class PackageOptionController extends Controller
{
    public function index()
    {
        $options = PackageOption::with('package')->latest()->get();
        return response()->json(['success' => true, 'data' => $options]);
    }

    public function show($id)
    {
        $option = PackageOption::with('package')->find($id);
        if (!$option) return response()->json(['success' => false, 'message' => 'Package option not found'], 404);
        return response()->json(['success' => true, 'data' => $option]);
    }

    public function showwithReservations($id)
    {
        $option = PackageOption::with(['reservations' => fn($q) => $q->whereNotNull('sentiment_analysis')])->find($id);
        if (!$option) return response()->json(['success' => false, 'message' => 'Package option not found'], 404);
        return response()->json(['success' => true, 'data' => $option]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'picture' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($request->file('picture')->getRealPath())
                ->resize(800, null, fn($c) => $c->aspectRatio()->upsize())
                ->toJpeg(80);

            $cloudinary = new Cloudinary();
            $upload = $cloudinary->uploadApi()->upload($image->toDataUri(), [
                'folder' => 'sweet-and-savory/package-options',
                'public_id' => 'option_' . Str::uuid(),
                'overwrite' => true,
            ]);

            $packageOption = PackageOption::create([
                'package_id' => $validated['package_id'],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'price' => $validated['price'],
                'picture_url' => $upload['secure_url'],
            ]);

            return response()->json(['success' => true, 'data' => $packageOption->load('package'), 'message' => 'Package option created successfully'], 201);

        } catch (\Exception $e) {
            Log::error('PackageOption store failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $option = PackageOption::findOrFail($id);

        $validated = $request->validate([
            'package_id' => 'sometimes|exists:packages,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'picture' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            $cloudinary = new Cloudinary();
            $newImageUrl = $option->picture_url;

            if ($request->hasFile('picture')) {
                // Delete old image if it exists on Cloudinary
                if ($option->picture_url && str_starts_with($option->picture_url, 'https://res.cloudinary.com')) {
                    try {
                        $publicId = pathinfo(parse_url($option->picture_url, PHP_URL_PATH), PATHINFO_FILENAME);
                        $cloudinary->uploadApi()->destroy('sweet-and-savory/package-options/' . $publicId);
                    } catch (\Exception $e) {
                        Log::warning("Failed to delete old Cloudinary image for option {$option->id}: " . $e->getMessage());
                    }
                }

                // Resize and upload new image
                $manager = new ImageManager(new Driver());
                $image = $manager->read($request->file('picture')->getRealPath())
                    ->resize(800, null, fn($c) => $c->aspectRatio()->upsize())
                    ->toJpeg(80);

                $upload = $cloudinary->uploadApi()->upload($image->toDataUri(), [
                    'folder' => 'sweet-and-savory/package-options',
                    'public_id' => 'option_' . Str::uuid(),
                    'overwrite' => true,
                ]);

                $newImageUrl = $upload['secure_url'];
            }

            $option->update(array_merge($validated, ['picture_url' => $newImageUrl]));

            return response()->json(['success' => true, 'data' => $option->load('package')]);

        } catch (\Exception $e) {
            Log::error('PackageOption update failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $option = PackageOption::findOrFail($id);

        try {
            if ($option->picture_url && str_starts_with($option->picture_url, 'https://res.cloudinary.com')) {
                $cloudinary = new Cloudinary();
                try {
                    $publicId = pathinfo(parse_url($option->picture_url, PHP_URL_PATH), PATHINFO_FILENAME);
                    $cloudinary->uploadApi()->destroy('sweet-and-savory/package-options/' . $publicId);
                } catch (\Exception $e) {
                    Log::warning("Failed to delete Cloudinary image for option {$option->id}: " . $e->getMessage());
                }
            }

            $option->delete();
            return response()->json(['success' => true, 'message' => 'Package option deleted']);

        } catch (\Exception $e) {
            Log::error('PackageOption deletion failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Deletion failed: ' . $e->getMessage()], 500);
        }
    }

    public function getByPackage($packageId)
    {
        $options = PackageOption::where('package_id', $packageId)
            ->with('package')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $options]);
    }

    public function AiAnalysis($id)
    {
        $reviews = Reservation::where('package_option_id', $id)
            ->whereNotNull('sentiment_analysis')
            ->select('review_text', 'rating', 'sentiment_analysis')
            ->get();

        if ($reviews->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No reviews with sentiment analysis found'], 404);
        }

        $reviewText = $reviews->map(fn($r) => "Review: {$r->review_text}\nRating: {$r->rating}\nSentiment: {$r->sentiment_analysis}")->implode("\n\n");

        $schema = new ObjectSchema(
            name: 'package_analysis',
            description: 'AI-generated analysis and recommendations based on customer reviews',
            properties: [
                new StringSchema('analysis', 'Summarized analysis of reviews'),
                new StringSchema('recommendation', 'Actionable recommendations for improvements'),
            ],
            requiredFields: ['analysis', 'recommendation']
        );

        $response = Prism::structured()
            ->using(Provider::Gemini, 'gemini-2.5-flash-lite') //gemini-2.0-flash not working
            ->withSchema($schema)
            ->withPrompt("Here are customer reviews for a package option:\n\n{$reviewText}\n\nPlease provide an overall analysis and recommendations.")
            ->asStructured();

        $option = PackageOption::findOrFail($id);
        $option->update([
            'analysis' => $response->structured['analysis'],
            'recommendation' => $response->structured['recommendation'],
        ]);

        return response()->json(['success' => true, 'data' => $option, 'message' => 'Package analysis and recommendation generated successfully']);
    }
}
