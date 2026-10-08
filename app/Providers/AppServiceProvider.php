<?php

namespace App\Providers;

use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One shared instance so tests and tinker can drive the same fake.
        $this->app->singleton(FakeGateway::class);

        $this->app->bind(PaymentGateway::class, function ($app) {
            return match (config('payments.gateway')) {
                'fake' => $app->make(FakeGateway::class),
                default => throw new InvalidArgumentException('Unknown payments gateway.'),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
