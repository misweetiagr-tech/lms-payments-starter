<?php

namespace App\Services\Live;

use App\Models\Host;
use App\Models\LiveClass;
use Illuminate\Support\Facades\DB;

/**
 * Picks a free host licence for a class from a small pool.
 *
 * Two classes overlap only when  existing.start < new.end  AND  existing.end > new.start.
 * Back-to-back classes (one ends exactly when the next starts) do NOT overlap.
 *
 * The lookup and the assignment happen in one transaction with the host rows
 * locked, so two simultaneous requests cannot both take the same host.
 */
class HostAllocator
{
    public function allocate(LiveClass $class): Host
    {
        return DB::transaction(function () use ($class) {
            $hosts = Host::orderBy('id')->lockForUpdate()->get();

            foreach ($hosts as $host) {
                $busy = LiveClass::where('host_id', $host->id)
                    ->where('id', '!=', $class->id)
                    ->where('starts_at', '<', $class->ends_at)
                    ->where('ends_at', '>', $class->starts_at)
                    ->exists();

                if (! $busy) {
                    $class->host_id = $host->id;
                    $class->save();

                    return $host;
                }
            }

            throw new NoHostAvailable('All hosts are busy for that time slot.');
        });
    }
}
