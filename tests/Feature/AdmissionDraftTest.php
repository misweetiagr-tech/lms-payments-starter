<?php

namespace Tests\Feature;

use App\Models\AdmissionDraft;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionDraftTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private User $sales;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->course = Course::create(['title' => 'Demo', 'price' => 1000]);
        $this->sales = User::factory()->create(['permissions' => ['drafts:view', 'drafts:create']]);
        $this->admin = User::factory()->create(['permissions' => ['drafts:view', 'drafts:create', 'drafts:approve']]);
    }

    private function newDraft(User $by, string $email = 'new.student@example.com'): AdmissionDraft
    {
        return AdmissionDraft::create([
            'created_by' => $by->id,
            'course_id' => $this->course->id,
            'student_name' => 'New Student',
            'student_email' => $email,
        ]);
    }

    public function test_a_salesperson_creates_a_draft_but_no_student_exists_yet(): void
    {
        $this->actingAs($this->sales)->postJson('/api/admission-drafts', [
            'course_id' => $this->course->id,
            'student_name' => 'New Student',
            'student_email' => 'new.student@example.com',
        ])->assertCreated();

        $this->assertDatabaseMissing('users', ['email' => 'new.student@example.com']);
        $this->assertSame(0, Enrollment::count());
    }

    public function test_a_salesperson_only_sees_their_own_drafts(): void
    {
        $otherSales = User::factory()->create(['permissions' => ['drafts:view', 'drafts:create']]);
        $this->newDraft($this->sales, 'a@example.com');
        $this->newDraft($otherSales, 'b@example.com');

        $this->actingAs($this->sales)->getJson('/api/admission-drafts')->assertJsonCount(1);
        $this->actingAs($this->admin)->getJson('/api/admission-drafts')->assertJsonCount(2);
    }

    public function test_a_salesperson_cannot_approve(): void
    {
        $draft = $this->newDraft($this->sales);

        $this->actingAs($this->sales)->postJson("/api/admission-drafts/{$draft->id}/approve")->assertStatus(403);
    }

    public function test_approval_is_refused_until_payment_is_confirmed(): void
    {
        $draft = $this->newDraft($this->sales);

        $this->actingAs($this->admin)->postJson("/api/admission-drafts/{$draft->id}/approve")->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'new.student@example.com']);
    }

    public function test_approval_after_payment_creates_the_student_and_enrollment(): void
    {
        $draft = $this->newDraft($this->sales);

        $this->actingAs($this->admin)->postJson("/api/admission-drafts/{$draft->id}/mark-paid")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/admission-drafts/{$draft->id}/approve")
            ->assertOk()->assertJson(['already_approved' => false]);

        $student = User::where('email', 'new.student@example.com')->firstOrFail();
        $this->assertSame('active', Enrollment::where('user_id', $student->id)->firstOrFail()->status);
        $this->assertSame('approved', $draft->fresh()->status);
    }

    public function test_approving_twice_does_not_create_a_second_student_or_enrollment(): void
    {
        $draft = $this->newDraft($this->sales);
        $draft->update(['paid' => true]);

        $first = $this->actingAs($this->admin)->postJson("/api/admission-drafts/{$draft->id}/approve");
        $second = $this->actingAs($this->admin)->postJson("/api/admission-drafts/{$draft->id}/approve");

        $second->assertOk()->assertJson(['already_approved' => true]);
        $this->assertSame($first->json('student_id'), $second->json('student_id'));
        $this->assertSame(1, Enrollment::count());
    }

    public function test_rejecting_a_paid_draft_flags_a_refund(): void
    {
        $draft = $this->newDraft($this->sales);
        $draft->update(['paid' => true]);

        $this->actingAs($this->admin)->postJson("/api/admission-drafts/{$draft->id}/reject")
            ->assertOk()->assertJson(['status' => 'rejected', 'refund_needed' => true]);
    }
}
