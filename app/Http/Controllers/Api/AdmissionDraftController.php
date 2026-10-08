<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdmissionDraft;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A salesperson fills a DRAFT. Nothing becomes a real student until an admin
 * approves it, and approval is refused until payment is marked received.
 */
class AdmissionDraftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = AdmissionDraft::orderBy('id');

        // Ownership scope: a salesperson only ever sees their own drafts.
        if (! $user->hasPermission('drafts', 'approve')) {
            $query->where('created_by', $user->id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'student_name' => ['required', 'string', 'max:255'],
            'student_email' => ['required', 'email'],
        ]);

        $draft = AdmissionDraft::create($data + ['created_by' => $request->user()->id]);

        return response()->json($draft, 201);
    }

    public function markPaid(AdmissionDraft $draft): JsonResponse
    {
        $draft->update(['paid' => true]);

        return response()->json($draft);
    }

    public function approve(Request $request, AdmissionDraft $draft): JsonResponse
    {
        return DB::transaction(function () use ($request, $draft) {
            $locked = AdmissionDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();

            // Approving twice is harmless: return the same student, create nothing.
            if ($locked->status === 'approved') {
                return response()->json(['student_id' => $locked->student_id, 'already_approved' => true]);
            }

            if ($locked->status !== 'draft') {
                return response()->json(['error' => 'Draft is not open.'], 409);
            }

            if (! $locked->paid) {
                return response()->json(['error' => 'Payment has not been confirmed.'], 422);
            }

            $student = User::firstOrCreate(
                ['email' => $locked->student_email],
                ['name' => $locked->student_name, 'password' => Str::random(32)],
            );

            Enrollment::firstOrCreate(
                ['user_id' => $student->id, 'course_id' => $locked->course_id],
                ['status' => 'active'],
            );

            $locked->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'student_id' => $student->id,
            ]);

            return response()->json(['student_id' => $student->id, 'already_approved' => false]);
        });
    }

    public function reject(AdmissionDraft $draft): JsonResponse
    {
        if ($draft->status !== 'draft') {
            return response()->json(['error' => 'Draft is not open.'], 409);
        }

        $draft->update(['status' => 'rejected']);

        // A paid draft that is rejected needs a refund: flag it, never lose it.
        return response()->json(['status' => 'rejected', 'refund_needed' => $draft->paid]);
    }
}
