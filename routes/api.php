<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\HeroImageController;
use App\Http\Controllers\Api\TestimonialController;
use App\Http\Controllers\Api\SystemHealthController;
use App\Http\Controllers\Api\SecurityLogController;
use App\Http\Controllers\Api\SubscriberController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\NoticeController;
use App\Http\Controllers\Api\RentalCarController;
use App\Http\Controllers\Api\RentalRequestController;


// ── Public routes ──────────────────────────────────────────────
Route::get('config',             [SuperAdminController::class, 'getConfig']);
Route::get('cars',               [CarController::class, 'index']);
Route::get('cars/featured/list', [CarController::class, 'featured']);
Route::get('classics',           [CarController::class, 'classics']);
Route::get('cars/{car}',         [CarController::class, 'show']);
Route::post('cars/{car}/view',   [\App\Http\Controllers\Api\CarViewController::class, 'store'])->middleware('throttle:60,1');
Route::post('messages',          [MessageController::class, 'store']);
Route::get('rentals',            [RentalCarController::class, 'publicIndex']);
Route::get('rentals/{car}',      [RentalCarController::class, 'publicShow']);
Route::post('rental-requests',   [RentalRequestController::class, 'store'])->middleware('throttle:6,1');
Route::get('hero-images',        [HeroImageController::class, 'index']);
Route::get('testimonials',       [TestimonialController::class, 'index']);
Route::post('testimonials',      [TestimonialController::class, 'store']);
Route::post('subscribe',         [SubscriberController::class, 'subscribe']);
Route::get('unsubscribe/{token}',[SubscriberController::class, 'unsubscribe']);
Route::get('/health', fn () => response()->json(['status' => 'ok']));

// ── Dealer auth ────────────────────────────────────────────────
Route::post('dealer/login',  [AuthController::class, 'dealerLogin']);
Route::post('dealer/logout', [AuthController::class, 'dealerLogout'])->middleware(['auth:sanctum', 'dealer.auth']);

// ── Dealer protected routes ────────────────────────────────────
Route::middleware(['auth:sanctum', 'dealer.auth'])->prefix('dealer')->group(function () {

    Route::get('me', [AuthController::class, 'dealerMe']);

    // Cars CRUD
    Route::get('cars',                   [CarController::class, 'dealerIndex']);
    Route::post('cars',                  [CarController::class, 'store']);
    Route::put('cars/{car}',             [CarController::class, 'update']);
    Route::delete('cars/{car}',          [CarController::class, 'destroy']);
    Route::patch('cars/{car}/toggle',    [CarController::class, 'toggle']);

    // Car images
    Route::post('cars/{car}/images',         [ImageController::class, 'upload']);
    Route::delete('cars/{car}/images/{img}', [ImageController::class, 'destroy']);
    Route::post('cars/{car}/images/reorder', [ImageController::class, 'reorder']);


    // Messages (the fixed words "unread-count" and "bulk" come BEFORE {message})
    Route::get('messages',                  [MessageController::class, 'index']);
    Route::get('messages/unread-count',     [MessageController::class, 'unreadCount']);
    Route::post('messages/bulk',            [MessageController::class, 'bulk']);
    Route::get('messages/{message}',        [MessageController::class, 'show']);
    Route::patch('messages/{message}/read', [MessageController::class, 'markRead']);
    Route::delete('messages/{message}',     [MessageController::class, 'destroy']);

    // Notices — read-only for dealers, with per-dealer read tracking
    Route::get('notices',              [NoticeController::class, 'index']);
    Route::patch('notices/{id}/read',  [NoticeController::class, 'markRead']);

    // Stats & profile
    Route::get('stats',   [StatsController::class,  'dealer']);
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
});

// ── Rental admin auth ──────────────────────────────────────────
Route::post('rental/login',  [AuthController::class, 'rentalLogin'])->middleware('throttle:10,1');
Route::post('rental/logout', [AuthController::class, 'rentalLogout'])->middleware(['auth:sanctum', 'rental.auth']);

// ── Rental admin protected routes ──────────────────────────────
Route::middleware(['auth:sanctum', 'rental.auth'])->prefix('rental')->group(function () {
    Route::get('me', [AuthController::class, 'rentalMe']);

    // Rental cars
    Route::get('cars',                [RentalCarController::class, 'index']);
    Route::post('cars',               [RentalCarController::class, 'store']);
    Route::put('cars/{car}',          [RentalCarController::class, 'update']);
    Route::delete('cars/{car}',       [RentalCarController::class, 'destroy']);
    Route::patch('cars/{car}/toggle', [RentalCarController::class, 'toggle']);

    // Car images
    Route::post('cars/{car}/images',         [ImageController::class, 'upload']);
    Route::delete('cars/{car}/images/{img}', [ImageController::class, 'destroy']);
    Route::post('cars/{car}/images/reorder', [ImageController::class, 'reorder']);

    // Requests forwarded to me
    Route::get('requests',                           [RentalRequestController::class, 'rentalIndex']);
    Route::patch('requests/{rentalRequest}/status',  [RentalRequestController::class, 'rentalStatus']);
});

// ── Super admin auth ───────────────────────────────────────────
Route::post('super/login',  [AuthController::class, 'superLogin']);
Route::post('super/logout', [AuthController::class, 'superLogout'])->middleware(['auth:sanctum', 'super.auth']);

// ── Super admin protected routes ───────────────────────────────
Route::middleware(['auth:sanctum', 'super.auth'])->prefix('super')->group(function () {
    Route::get('me',    [AuthController::class, 'superMe']);
    Route::get('stats', [StatsController::class, 'super']);

    // Hero images
    Route::get('hero-images',                [HeroImageController::class, 'superIndex']);
    Route::post('hero-images',               [HeroImageController::class, 'store']);
    Route::delete('hero-images/{heroImage}', [HeroImageController::class, 'destroy']);

    // Profile & config
    Route::get('profile', [ProfileController::class,    'show']);
    Route::put('profile', [ProfileController::class,    'update']);
    Route::get('config',  [SuperAdminController::class, 'getConfig']);
    Route::put('config',  [SuperAdminController::class, 'updateConfig']);

    // Dealer management
    Route::get('dealers',                [SuperAdminController::class, 'dealers']);
    Route::post('dealers',               [SuperAdminController::class, 'createDealer']);
    Route::patch('dealers/{id}/suspend', [SuperAdminController::class, 'suspend']);
    Route::delete('dealers/{id}',        [SuperAdminController::class, 'deleteDealer']);

    // Rental admin management
    Route::get('rental-admins',                  [SuperAdminController::class, 'rentalAdmins']);
    Route::post('rental-admins',                 [SuperAdminController::class, 'createRentalAdmin']);
    Route::put('rental-admins/{id}',             [SuperAdminController::class, 'updateRentalAdmin']);
    Route::patch('rental-admins/{id}/suspend',   [SuperAdminController::class, 'suspendRentalAdmin']);
    Route::delete('rental-admins/{id}',          [SuperAdminController::class, 'deleteRentalAdmin']);

    // Rental requests
    Route::get('rental-requests',                           [RentalRequestController::class, 'superIndex']);
    Route::patch('rental-requests/{rentalRequest}/forward', [RentalRequestController::class, 'forward']);
    Route::patch('rental-requests/{rentalRequest}/status',  [RentalRequestController::class, 'superStatus']);

    // Full data access — the super admin uses the SAME car and message actions the dealers use.
    // The "owner or super admin" rule lives inside the controllers, so nothing is written twice.
    Route::get('cars',                    [CarController::class, 'superIndex']);
    Route::post('cars/bulk',              [CarController::class, 'bulk']);
    Route::put('cars/{car}',              [CarController::class, 'update']);
    Route::patch('cars/{car}/toggle',     [CarController::class, 'toggle']);
    Route::delete('cars/{car}',           [CarController::class, 'destroy']);

    Route::get('messages',                  [MessageController::class, 'index']);
    Route::get('messages/unread-count',     [MessageController::class, 'unreadCount']);
    Route::post('messages/bulk',            [MessageController::class, 'bulk']);
    Route::get('messages/{message}',        [MessageController::class, 'show']);
    Route::patch('messages/{message}/read', [MessageController::class, 'markRead']);
    Route::delete('messages/{message}',     [MessageController::class, 'destroy']);

    // Testimonials
    Route::get('testimonials',                        [TestimonialController::class, 'superIndex']);
    Route::patch('testimonials/{testimonial}/status', [TestimonialController::class, 'updateStatus']);
    Route::delete('testimonials/{testimonial}',       [TestimonialController::class, 'destroy']);

    // Notices — super admin manages
    Route::get('notices',               [NoticeController::class, 'superIndex']);
    Route::post('notices',              [NoticeController::class, 'store']);
    Route::patch('notices/{id}/toggle', [NoticeController::class, 'toggle']);
    Route::delete('notices/{id}',       [NoticeController::class, 'destroy']);

    // Subscribers & newsletters
    Route::get('subscribers',            [SubscriberController::class,  'index']);
    Route::delete('subscribers/{id}',    [SubscriberController::class,  'destroy']);
    Route::get('newsletters',            [NewsletterController::class,  'index']);
    Route::post('newsletters',           [NewsletterController::class,  'store']);
    Route::put('newsletters/{id}',       [NewsletterController::class,  'update']);
    Route::delete('newsletters/{id}',    [NewsletterController::class,  'destroy']);
    Route::post('newsletters/{id}/send', [NewsletterController::class,  'send']);

    // System
    Route::get('health',       [SystemHealthController::class, 'index']);
    Route::get('security-log', [SecurityLogController::class,  'index']);

});
