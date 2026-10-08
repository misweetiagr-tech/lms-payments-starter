<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    private function enrollment(): Enrollment
    {
        $student = User::factory()->create();
        $course = Course::create(['title' => 'Demo', 'price' => 1000]);

        return Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id]);
    }

    public function test_guests_get_401(): void
    {
        $this->getJson('/api/admin/enrollments')->assertStatus(401);
    }

    public function test_view_only_user_can_list_but_not_delete(): void
    {
        $enrollment = $this->enrollment();
        $viewer = User::factory()->create(['permissions' => ['enrollments:view']]);

        $this->actingAs($viewer)->getJson('/api/admin/enrollments')->assertOk();
        $this->actingAs($viewer)->deleteJson("/api/admin/enrollments/{$enrollment->id}")->assertStatus(403);

        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id]);
    }

    public function test_user_with_no_permissions_is_forbidden(): void
    {
        $nobody = User::factory()->create(['permissions' => null]);

        $this->actingAs($nobody)->getJson('/api/admin/enrollments')->assertStatus(403);
    }

    public function test_legacy_flat_token_still_grants_every_action_in_that_section(): void
    {
        $enrollment = $this->enrollment();
        $legacy = User::factory()->create(['permissions' => ['enrollments']]);

        $this->actingAs($legacy)->deleteJson("/api/admin/enrollments/{$enrollment->id}")->assertOk();
    }

    public function test_star_grants_everything(): void
    {
        $admin = User::factory()->create(['permissions' => ['*']]);

        $this->actingAs($admin)->getJson('/api/admin/enrollments')->assertOk();
    }
}
