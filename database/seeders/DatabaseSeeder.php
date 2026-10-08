<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Host;
use App\Models\InstallmentPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Fake demo data only. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create(['name' => 'Demo Admin', 'email' => 'admin@example.com', 'permissions' => ['*']]);
        User::factory()->create(['name' => 'Demo Support', 'email' => 'support@example.com', 'permissions' => ['enrollments:view']]);
        User::factory()->create(['name' => 'Demo Student', 'email' => 'student@example.com']);

        $installable = Course::create(['title' => 'Demo Course (installments)', 'price' => 30000]);
        Course::create(['title' => 'Demo Workshop (full payment only)', 'price' => 5000]);

        InstallmentPlan::create(['course_id' => $installable->id, 'enabled' => true, 'count' => 3, 'interval_value' => 1, 'interval_unit' => 'months']);

        foreach (['Host A', 'Host B', 'Host C'] as $name) {
            Host::create(['name' => $name]);
        }
    }
}
