<?php

use App\Http\Controllers\API\ResetPasswordController;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\VerifyEmailController;
use App\Http\Controllers\API\AdminAuthenticationController;

use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

Route::group(['namespace' => 'App\Http\Controllers\API'], function () {
    // --------------- Register and Login ----------------//
    Route::post('register', 'AuthenticationController@register')->name('register');
    Route::post('login', 'AuthenticationController@login')->name('login');
    
    // ------------------ Get Data ----------------------//
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('get-user', 'AuthenticationController@userInfo')->name('get-user');
        Route::post('logout', 'AuthenticationController@logOut')->name('logout');
    });

    Route::middleware('auth:admin-api')->group(function () {
        Route::get('verify-admin', 'AuthenticationController@user');
    });
    
    Route::middleware('auth:user-api')->group(function () {
        Route::get('verify-user', 'AuthenticationController@user');
    });
});

Route::post('/send-otp', [VerifyEmailController::class, 'sendOtp'])
    ->name('verification.send')
    ->middleware(['throttle:6,1']);

Route::post('/verify-otp', [VerifyEmailController::class, 'verifyOtp'])
    ->name('verification.verify')
    ->middleware(['throttle:6,1']);

Route::post('/forgot-password', [ResetPasswordController::class, 'sendResetLink'])
    ->name('password.email')
    ->middleware(['throttle:6,1']);

Route::post('/forgot-password-token', [ResetPasswordController::class, 'verifyOtp'])
    ->name('password.reset')
    ->middleware(['throttle:6,1']);

Route::post('/reset-password', [ResetPasswordController::class, 'resetPassword'])
    ->name('password.update')
    ->middleware(['throttle:6,1']);

Route::post('/admin-login', [AdminAuthenticationController::class, 'login'])
    ->middleware(['throttle:6,1']);


include __DIR__ . '/package.php';
include __DIR__ . '/package_option.php';
include __DIR__ . '/reservation.php';
include __DIR__ . '/analytics.php';
include __DIR__ . '/user.php';



//general

Route::post('/add-admin', [AdminAuthenticationController::class, 'addAdmin']);
Route::get('/get-admins', [AdminAuthenticationController::class, 'getAdmins']);

Route::get('/debug-gemini', function () {

    try {
        $schema = new ObjectSchema(
            name: 'debug_test',
            description: 'Simple test schema',
            properties: [
                new StringSchema('message', 'Test output'),
            ],
            requiredFields: ['message']
        );

        $response = Prism::structured()
            ->using(Provider::Gemini, 'gemini-2.5-flash') //changed to flash only
            ->withSchema($schema)
            ->withPrompt("Say hello and confirm API is working.")
            ->asStructured();

        return response()->json([
            'success' => true,
            'structured' => $response->structured ?? null,
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
    }
});

Route::get('/debug-env-gemini', function () {
    return response()->json([
        'app_env'     => app()->environment(),
        'gemini_url'  => config('prism.providers.gemini.url'), // Checks the actual loaded config
        'raw_env_url' => env('GEMINI_URL'),                 // Checks the raw .env value
        'status'      => 'Checking if proxy is active'
    ]);
});

// REVERSE PROXY CODE CLOUDFLARE
// export default {
//   async fetch(request, env) {
//     const url = new URL(request.url);
    
//     // This replaces your worker URL with Google's URL but keeps the rest of the path
//     const targetUrl = `https://generativelanguage.googleapis.com${url.pathname}${url.search}`;

//     const proxyRequest = new Request(targetUrl, {
//       method: request.method,
//       headers: request.headers,
//       body: request.body,
//     });

//     // We MUST tell Google it's talking to Google, or it rejects the request
//     proxyRequest.headers.set("Host", "generativelanguage.googleapis.com");

//     return fetch(proxyRequest);
//   },
// };