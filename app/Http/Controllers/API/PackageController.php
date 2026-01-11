<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Package;
use App\Models\Reservation;
use Cloudinary\Cloudinary;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

class PackageController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'picture' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($request->file('picture')->getRealPath())
                ->resize(800, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->toJpeg(80);

            $cloudinary = new Cloudinary();

            $upload = $cloudinary->uploadApi()->upload($image->toDataUri(), [
                'folder' => 'sweet-and-savory/packages',
                'public_id' => 'package_' . Str::uuid(),
                'overwrite' => true,
            ]);

            $package = Package::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'picture_url' => $upload['secure_url'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $package,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Package upload failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error uploading image: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function index()
    {
        $packages = Package::with('options')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $packages
        ]);
    }

    public function show($id)
    {
        $package = Package::with('options')->find($id);

        if (!$package) {
            return response()->json([
                'success' => false,
                'message' => 'Package not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $package
        ]);
    }

    public function update(Request $request, $id)
    {
        $package = Package::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'picture' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            $cloudinary = new Cloudinary();
            $newImageUrl = $package->picture_url;

            if ($request->hasFile('picture')) {
                // Delete old image if on Cloudinary
                if ($package->picture_url && str_starts_with($package->picture_url, 'https://res.cloudinary.com')) {
                    try {
                        $publicId = pathinfo(parse_url($package->picture_url, PHP_URL_PATH), PATHINFO_FILENAME);
                        $cloudinary->uploadApi()->destroy('sweet-and-savory/packages/' . $publicId);
                    } catch (\Exception $e) {
                        Log::warning("Failed to delete old image for package {$package->id}: " . $e->getMessage());
                    }
                }

                // Resize and upload new image
                $manager = new ImageManager(new Driver());
                $image = $manager->read($request->file('picture')->getRealPath())
                    ->resize(800, null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })
                    ->toJpeg(80);

                $upload = $cloudinary->uploadApi()->upload($image->toDataUri(), [
                    'folder' => 'sweet-and-savory/packages',
                    'public_id' => 'package_' . Str::uuid(),
                    'overwrite' => true,
                ]);

                $newImageUrl = $upload['secure_url'];
            }

            $package->update([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'picture_url' => $newImageUrl,
            ]);

            return response()->json([
                'success' => true,
                'data' => $package,
            ]);

        } catch (\Exception $e) {
            Log::error('Package update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating package: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $package = Package::find($id);
        if (!$package) {
            return response()->json(['success' => false, 'message' => 'Package not found'], 404);
        }

        try {
            if ($package->picture_url && str_starts_with($package->picture_url, 'https://res.cloudinary.com')) {
                $cloudinary = new Cloudinary();
                try {
                    $publicId = pathinfo(parse_url($package->picture_url, PHP_URL_PATH), PATHINFO_FILENAME);
                    $cloudinary->uploadApi()->destroy('sweet-and-savory/packages/' . $publicId);
                } catch (\Exception $e) {
                    Log::warning("Failed to delete Cloudinary image for package {$package->id}: " . $e->getMessage());
                }
            }

            $package->delete();

            return response()->json([
                'success' => true,
                'message' => 'Package deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete package: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Deletion failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function AiAnalysis($id)
    {
        $reviews = Reservation::whereHas('packageOption', function ($query) use ($id) {
                $query->where('package_id', $id);
            })
            ->whereNotNull('sentiment_analysis')
            ->select('review_text', 'rating', 'sentiment_analysis')
            ->get();

        if ($reviews->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No reviews with sentiment analysis found for this package.'
            ], 404);
        }

        $reviewText = $reviews->map(function ($r) {
            return "Review: {$r->review_text}\nRating: {$r->rating}\nSentiment: {$r->sentiment_analysis}";
        })->implode("\n\n");

        $schema = new ObjectSchema(
            name: 'package_analysis',
            description: 'AI-generated analysis and recommendations for a package based on customer reviews',
            properties: [
                new StringSchema('analysis', 'Summarized analysis of reviews for the package'),
                new StringSchema('recommendation', 'Actionable recommendations for improving the package'),
            ],
            requiredFields: ['analysis', 'recommendation']
        );

        $response = Prism::structured()
            ->using(Provider::Gemini, 'gemini-2.5-flash-lite') //gemini-2.0-flash not working
            ->withSchema($schema)
            ->withPrompt("Here are customer reviews for a package:\n\n{$reviewText}\n\nPlease provide an overall analysis and recommendations.")
            ->asStructured();

        $package = Package::findOrFail($id);
        $package->update([
            'analysis' => $response->structured['analysis'],
            'recommendation' => $response->structured['recommendation'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $package,
            'message' => 'Package analysis and recommendation generated successfully.'
        ]);
    }
}
