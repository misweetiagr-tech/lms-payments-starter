<?php

use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Public: signature-checked inside the controller, no session or CSRF involved.
Route::post('/webhooks/payments', [WebhookController::class, 'handle']);

Route::post('/subscriptions', [SubscriptionController::class, 'store']);
Route::get('/subscriptions/{reference}/status', [SubscriptionController::class, 'status']);

// Permission-protected admin routes.
Route::middleware(['auth', 'perm:enrollments,view'])->get('/admin/enrollments', [EnrollmentController::class, 'index']);
Route::middleware(['auth', 'perm:enrollments,delete'])->delete('/admin/enrollments/{enrollment}', [EnrollmentController::class, 'destroy']);
