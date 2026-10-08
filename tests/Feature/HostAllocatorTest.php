<?php

namespace Tests\Feature;

use App\Models\Host;
use App\Models\LiveClass;
use App\Services\Live\HostAllocator;
use App\Services\Live\NoHostAvailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostAllocatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeClass(string $title, string $start, string $end): LiveClass
    {
        return LiveClass::create(['title' => $title, 'starts_at' => $start, 'ends_at' => $end]);
    }

    public function test_overlapping_classes_get_different_hosts(): void
    {
        Host::create(['name' => 'A']);
        Host::create(['name' => 'B']);
        $allocator = app(HostAllocator::class);

        $first = $allocator->allocate($this->makeClass('One', '2025-01-01 10:00', '2025-01-01 11:00'));
        $second = $allocator->allocate($this->makeClass('Two', '2025-01-01 10:30', '2025-01-01 11:30'));

        $this->assertNotEquals($first->id, $second->id);
    }

    public function test_back_to_back_classes_can_share_a_host(): void
    {
        Host::create(['name' => 'Only']);
        $allocator = app(HostAllocator::class);

        $first = $allocator->allocate($this->makeClass('One', '2025-01-01 10:00', '2025-01-01 11:00'));
        $second = $allocator->allocate($this->makeClass('Two', '2025-01-01 11:00', '2025-01-01 12:00'));

        $this->assertSame($first->id, $second->id);
    }

    public function test_fails_clearly_when_every_host_is_busy(): void
    {
        Host::create(['name' => 'Only']);
        $allocator = app(HostAllocator::class);

        $allocator->allocate($this->makeClass('One', '2025-01-01 10:00', '2025-01-01 11:00'));

        $this->expectException(NoHostAvailable::class);
        $allocator->allocate($this->makeClass('Two', '2025-01-01 10:30', '2025-01-01 11:30'));
    }

    public function test_rescheduling_a_class_does_not_clash_with_itself(): void
    {
        Host::create(['name' => 'Only']);
        $allocator = app(HostAllocator::class);

        $class = $this->makeClass('One', '2025-01-01 10:00', '2025-01-01 11:00');
        $allocator->allocate($class);

        $class->update(['ends_at' => '2025-01-01 11:30']);
        $host = $allocator->allocate($class->fresh());

        $this->assertNotNull($host);
    }
}
