<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Admins table (separate auth guard)
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        // Packages table (base packages)
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('picture_url');
            $table->text('analysis')->nullable();
            $table->text('recommendation')->nullable();
            $table->timestamps();
        });

        // Package_options table (variants)
        Schema::create('package_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->onDelete('cascade');
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 10, 2); // 10 digits, 2 decimal places
            $table->string('picture_url');
            $table->text('analysis')->nullable();
            $table->text('recommendation')->nullable();
            $table->timestamps();
        });

        // Reservations table (now includes review fields)
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('package_option_id')->constrained('package_options')->onDelete('cascade');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->decimal('price_purchased', 10, 2);
            $table->dateTime('reservation_datetime');

            // Review fields (merged from Reviews table)
            $table->text('review_text')->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5
            $table->enum('sentiment_analysis', ['Positive', 'Neutral', 'Negative'])->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('package_options');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('admins');
    }
};
