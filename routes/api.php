<?php

use App\Http\Controllers\Api\AdmissionDraftController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\ResultImportController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\WebhookController;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public: signature-checked inside the controller, no session or CSRF involved.
Route::post('/webhooks/payments', [WebhookController::class, 'handle']);

Route::post('/subscriptions', [SubscriptionController::class, 'store']);
Route::get('/subscriptions/{reference}/status', [SubscriptionController::class, 'status']);

// Permission-protected admin routes.
Route::middleware(['auth', 'perm:enrollments,view'])->get('/admin/enrollments', [EnrollmentController::class, 'index']);
Route::middleware(['auth', 'perm:enrollments,delete'])->delete('/admin/enrollments/{enrollment}', [EnrollmentController::class, 'destroy']);

Route::middleware(['auth', 'perm:results,import'])->post('/admin/results/import', [ResultImportController::class, 'store']);

// Admission drafts: salesperson creates, admin approves.
Route::middleware('auth')->group(function () {
    Route::get('/admission-drafts', [AdmissionDraftController::class, 'index'])->middleware('perm:drafts,view');
    Route::post('/admission-drafts', [AdmissionDraftController::class, 'store'])->middleware('perm:drafts,create');
    Route::post('/admission-drafts/{draft}/mark-paid', [AdmissionDraftController::class, 'markPaid'])->middleware('perm:drafts,approve');
    Route::post('/admission-drafts/{draft}/approve', [AdmissionDraftController::class, 'approve'])->middleware('perm:drafts,approve');
    Route::post('/admission-drafts/{draft}/reject', [AdmissionDraftController::class, 'reject'])->middleware('perm:drafts,approve');
});

// Node JWT bridge: same token as the Node service, additive /api/app prefix.
Route::middleware('node.jwt')->prefix('app')->group(function () {
    Route::get('/me', fn (Request $request) => response()->json([
        'id' => $request->user()->id,
        'name' => $request->user()->name,
    ]));

    // Only the signed-in student's own enrollments, never anyone else's.
    Route::get('/enrollments', fn (Request $request) => Enrollment::where('user_id', $request->user()->id)
        ->join('courses', 'courses.id', '=', 'enrollments.course_id')
        ->orderBy('enrollments.id')
        ->get(['enrollments.id', 'courses.title as course', 'enrollments.status']));
});
