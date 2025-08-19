<?php

namespace App\Http\Controllers\API;


use App\Models\PackageOption;



use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Package;
use Intervention\Image\Facades\Image;



class PackageOptionController extends Controller
{
    // Get all package options
    public function index()
    {
        $options = PackageOption::with('package')->latest()->get();
        
        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }

    // Get single package option
    public function show($id)
    {
        $option = PackageOption::with('package')->find($id);

        if (!$option) {
            return response()->json([
                'success' => false,
                'message' => 'Package option not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $option
        ]);
    }

    // Create new package option
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
            // Initialize ImageManager with GD driver
            $manager = new ImageManager(new Driver());
            
            // Process image
            $image = $manager->read($request->file('picture'))
                ->resize(800, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })->toJpeg(80);

            // Generate filename and path
            $filename = 'package-option-' . Str::slug($request->name) . '-' . time() . '.jpg';
            $uploadPath = public_path('uploads/package-options');
            File::ensureDirectoryExists($uploadPath);
            $imagePath = $uploadPath . '/' . $filename;

            // Save as JPEG
            $image->save($imagePath);

            // Create package option
            $packageOption = PackageOption::create([
                'package_id' => $validated['package_id'],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'price' => $validated['price'],
                'picture_url' => '/uploads/package-options/' . $filename,
            ]);

            return response()->json([
                'success' => true,
                'data' => $packageOption->load('package'),
                'message' => 'Package option created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Update package option
    public function update(Request $request, $id)
    {
        $option = PackageOption::find($id);
        if (!$option) {
            return response()->json(['success' => false, 'message' => 'Package option not found'], 404);
        }

        $validated = $request->validate([
            'package_id' => 'sometimes|exists:packages,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'picture' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            // Handle image update if provided
            if ($request->hasFile('picture')) {
                $manager = new ImageManager(new Driver());
                $image = $manager->read($request->file('picture'))
                    ->resize(800, null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })->toJpeg(80);

                $filename = 'option-' . Str::slug($request->name ?? $option->name) . '-' . time() . '.webp';
                $uploadPath = public_path('uploads/package-options');
                File::ensureDirectoryExists($uploadPath);
                $imagePath = $uploadPath . '/' . $filename;

                // Delete old image if exists
                if ($option->picture_url && File::exists(public_path($option->picture_url))) {
                    File::delete(public_path($option->picture_url));
                }

                $image->save($imagePath);
                $validated['picture_url'] = 'uploads/package-options/' . $filename;
            }

            $option->update($validated);

            return response()->json([
                'success' => true,
                'data' => $option->load('package')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete package option
    public function destroy($id)
    {
        $option = PackageOption::find($id);
        if (!$option) {
            return response()->json(['success' => false, 'message' => 'Package option not found'], 404);
        }

        try {
            // Delete associated image
            if ($option->picture_url && File::exists(public_path($option->picture_url))) {
                File::delete(public_path($option->picture_url));
            }

            $option->delete();

            return response()->json([
                'success' => true,
                'message' => 'Package option deleted'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // Get options by package
    public function getByPackage($packageId)
    {
        $options = PackageOption::where('package_id', $packageId)
            ->with('package')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }
}
