<?php

use Illuminate\Support\Facades\Schedule;

// Safety net for missed webhooks. Needs one system cron: * * * * * php artisan schedule:run
Schedule::command('payments:reconcile')->hourly()->withoutOverlapping();
