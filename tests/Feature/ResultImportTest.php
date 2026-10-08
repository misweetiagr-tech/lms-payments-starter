<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Result;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResultImportTest extends TestCase
{
    use RefreshDatabase;

    private Enrollment $enrollment;

    private Subject $maths;

    protected function setUp(): void
    {
        parent::setUp();

        $student = User::factory()->create();
        $course = Course::create(['title' => 'Demo', 'price' => 1000]);
        $otherCourse = Course::create(['title' => 'Other', 'price' => 1000]);

        $this->maths = Subject::create(['code' => 'MATH', 'name' => 'Maths', 'max_marks' => 100]);
        $physics = Subject::create(['code' => 'PHYS', 'name' => 'Physics', 'max_marks' => 50]);
        $history = Subject::create(['code' => 'HIST', 'name' => 'History', 'max_marks' => 100]);

        DB::table('course_subjects')->insert([
            ['course_id' => $course->id, 'subject_id' => $this->maths->id],
            ['course_id' => $course->id, 'subject_id' => $physics->id],
            ['course_id' => $otherCourse->id, 'subject_id' => $history->id],
        ]);

        $this->enrollment = Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'enrollment_number' => 'EN-001']);
    }

    private function importAs(array $rows, array $permissions = ['results:import'])
    {
        $user = User::factory()->create(['permissions' => $permissions]);

        return $this->actingAs($user)->postJson('/api/admin/results/import', ['rows' => $rows]);
    }

    public function test_valid_rows_are_saved(): void
    {
        $this->importAs([
            ['enrollment_number' => 'EN-001', 'subject_code' => 'MATH', 'marks' => '88'],
            ['enrollment_number' => 'EN-001', 'subject_code' => 'PHYS', 'marks' => '40'],
        ])->assertOk()->assertJson(['imported' => 2, 'errors' => []]);

        $this->assertSame(2, Result::count());
    }

    public function test_extra_columns_from_the_sheet_are_ignored(): void
    {
        // A sheet cannot choose the student or course: only the enrollment number counts.
        $this->importAs([
            ['enrollment_number' => 'EN-001', 'subject_code' => 'MATH', 'marks' => '70', 'student_name' => 'Someone Else', 'course_id' => 999],
        ])->assertOk()->assertJson(['imported' => 1]);

        $this->assertSame($this->enrollment->id, Result::first()->enrollment_id);
    }

    public function test_a_subject_from_another_course_is_rejected_with_its_row_number(): void
    {
        $response = $this->importAs([
            ['enrollment_number' => 'EN-001', 'subject_code' => 'MATH', 'marks' => '50'],
            ['enrollment_number' => 'EN-001', 'subject_code' => 'HIST', 'marks' => '50'],
        ])->assertOk();

        $response->assertJson(['imported' => 1]);
        $this->assertSame(2, $response->json('errors.0.row'));
        $this->assertSame(1, Result::count());
    }

    public function test_marks_above_the_maximum_are_rejected(): void
    {
        $this->importAs([['enrollment_number' => 'EN-001', 'subject_code' => 'PHYS', 'marks' => '51']])
            ->assertJson(['imported' => 0]);

        $this->assertSame(0, Result::count());
    }

    public function test_unknown_enrollment_and_bad_marks_are_reported(): void
    {
        $response = $this->importAs([
            ['enrollment_number' => 'NOPE', 'subject_code' => 'MATH', 'marks' => '50'],
            ['enrollment_number' => 'EN-001', 'subject_code' => 'MATH', 'marks' => 'abc'],
        ]);

        $response->assertJson(['imported' => 0]);
        $this->assertCount(2, $response->json('errors'));
    }

    public function test_absent_is_stored_as_absent_not_zero(): void
    {
        $this->importAs([['enrollment_number' => 'EN-001', 'subject_code' => 'MATH', 'marks' => 'absent']])->assertOk();

        $result = Result::first();
        $this->assertTrue($result->absent);
        $this->assertNull($result->marks);
    }

    public function test_importing_the_same_sheet_again_updates_instead_of_duplicating(): void
    {
        $row = ['enrollment_number' => 'EN-001', 'subject_code' => 'MATH', 'marks' => '60'];

        $this->importAs([$row]);
        $this->importAs([['marks' => '75'] + $row]);

        $this->assertSame(1, Result::count());
        $this->assertSame(75, Result::first()->marks);
    }

    public function test_user_without_the_import_permission_is_forbidden(): void
    {
        $this->importAs([], ['results:view'])->assertStatus(403);
    }
}
