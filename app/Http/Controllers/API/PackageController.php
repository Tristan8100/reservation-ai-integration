<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Package;
use Intervention\Image\Facades\Image;
use App\Models\Reservation;

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
            // Initialize ImageManager with GD driver
            $manager = new ImageManager(new Driver()); // or 'imagick'
            
            // Process image
            $image = $manager->read($request->file('picture'))
                ->resize(800, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })->toJpeg(80);

            // Generate filename and path
            $filename = 'package-' . Str::slug($request->name) . '-' . time() . '.jpg';
            $uploadPath = public_path('uploads/packages');
            File::ensureDirectoryExists($uploadPath);
            $imagePath = $uploadPath . '/' . $filename;

            // Save as WebP
            $image->save($imagePath);

            // Create package
            $package = Package::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'picture_url' => '/uploads/packages/' . $filename,
            ]);

            return response()->json([
                'success' => true,
                'data' => $package,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
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
            // Initialize $newImagePath early to avoid undefined variable issues
            $newImagePath = $package->picture_url; // Default to old image if no update

            // Handle image upload if provided
            if ($request->hasFile('picture')) {
                $manager = new ImageManager(new Driver());
                $image = $manager->read($request->file('picture'))
                    ->resize(800, null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })
                    ->toJpeg(80); //80% decrease quality

                // Generate new filename
                $filename = 'package-' . Str::slug($request->name) . '-' . time() . '.jpg';
                $uploadPath = public_path('uploads/packages');
                File::ensureDirectoryExists($uploadPath);
                $imagePath = $uploadPath . '/' . $filename;

                // Save new image to DB
                $image->save($imagePath);

                // Delete old image if it exists
                if ($package->picture_url && File::exists(public_path($package->picture_url))) {
                    File::delete(public_path($package->picture_url));
                }

                // Update the image path
                $newImagePath = '/uploads/packages/' . $filename;// rewrite
            }

            // Update package data
            $package->update([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'picture_url' => $newImagePath, // Original or Rewrited
            ]);

            return response()->json([
                'success' => true,
                'data' => $package,
                'new_image' => $newImagePath //for debugging
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
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
            // Delete associated image
            if ($package->picture_url && File::exists(public_path($package->picture_url))) {
                File::delete(public_path($package->picture_url));
            }

            $package->delete();

            return response()->json([
                'success' => true,
                'message' => 'Package deleted'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function AiAnalysis($id)
    {
        // Step 1: Collect all reservations for this package (via options)
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

        // Step 2: Format reviews for AI
        $reviewText = $reviews->map(function ($r) {
            return "Review: {$r->review_text}\nRating: {$r->rating}\nSentiment: {$r->sentiment_analysis}";
        })->implode("\n\n");

        // Step 3: Define schema
        $schema = new ObjectSchema(
            name: 'package_analysis',
            description: 'AI-generated analysis and recommendations for a package based on customer reviews',
            properties: [
                new StringSchema('analysis', 'Summarized analysis of reviews for the package'),
                new StringSchema('recommendation', 'Actionable recommendations for improving the package'),
            ],
            requiredFields: ['analysis', 'recommendation']
        );

        // Step 4: Send to AI
        $response = Prism::structured()
            ->using(Provider::Gemini, 'gemini-2.0-flash')
            ->withSchema($schema)
            ->withPrompt("Here are customer reviews for a package:\n\n{$reviewText}\n\nPlease provide an overall analysis and recommendations.")
            ->asStructured();

        // Step 5: Update the Package
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
