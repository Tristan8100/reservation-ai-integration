<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class VerifyEmailController extends Controller
{
    // Generate and send OTP to user email
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;

        // Generate 6-digit OTP
        $otp = rand(100000, 999999);

        // Create or update the OTP record
        EmailVerification::updateOrCreate(
            ['email' => $email],
            [
                'otp' => $otp,
                'verified' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Send OTP email (using Laravel mail)
        Mail::raw("Your verification OTP is: $otp. It expires in 10 minutes.", function ($message) use ($email) {
            $message->to($email)
                    ->subject('Email Verification OTP');
        });

        return response()->json(['message' => 'OTP sent to your email.', 'email' => $email]);
    }

    // Verify the OTP
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
        ]);

        $email = $request->email;
        $otp = $request->otp;

        // Fetch OTP record
        $record = EmailVerification::where('email', $email)->first();

        if (!$record) {
            return response()->json(['message' => 'No OTP found for this email.'], 404);
        }

        // Check if already verified
        if ($record->verified) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }

        // Check OTP match
        if ($record->otp !== $otp) {
            return response()->json(['message' => 'Invalid OTP.'], 400);
        }

        // Check expiration (10 minutes)
        $expiresAt = $record->updated_at->addMinutes(10);
        if (Carbon::now()->greaterThan($expiresAt)) {
            return response()->json(['message' => 'OTP expired. Please request a new one.'], 400);
        }

        // Mark verified
        $record->verified = true;
        $record->save();
        
        User::where('email', $email)->update(['email_verified_at' => now()]);

        return response()->json(['message' => 'Email verified successfully!']);
    }
}
