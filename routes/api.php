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


// ── Public routes ──────────────────────────────────────────────
Route::get('config',             [SuperAdminController::class, 'getConfig']);
Route::get('cars',               [CarController::class, 'index']);
Route::get('cars/featured/list', [CarController::class, 'featured']);
Route::get('cars/{car}',         [CarController::class, 'show']);
Route::post('messages',          [MessageController::class, 'store']);
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

  
    // Messages
    Route::get('messages',                  [MessageController::class, 'index']);
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

    // Full data access
    Route::get('cars',                      [CarController::class,     'superIndex']);
    Route::get('messages',                  [MessageController::class,  'superIndex']);
    Route::patch('messages/{message}/read', [MessageController::class,  'superMarkRead']);

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
