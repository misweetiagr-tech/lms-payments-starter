<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // JSON list such as ["orders:view", "enrollments:*"] or ["*"].
            $table->json('permissions')->nullable();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('price'); // smallest currency unit
            $table->timestamps();
        });

        // One row per course. No row means "full payment only".
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedTinyInteger('count');
            $table->unsignedSmallInteger('interval_value')->default(1);
            $table->string('interval_unit')->default('months'); // days|months
            $table->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending|active
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('payment_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('gateway_subscription_id')->unique();
            $table->string('status')->default('pending'); // pending|active|completed
            $table->unsignedTinyInteger('total_cycles');
            $table->unsignedTinyInteger('paid_count')->default(0);
            $table->unsignedInteger('amount_per_cycle');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('cycle_no');
            $table->string('provider_payment_id')->unique();
            $table->unsignedInteger('amount');
            $table->string('source'); // webhook|reconcile
            $table->timestamps();
            // The same cycle can never be recorded twice, whichever path saw it first.
            $table->unique(['payment_subscription_id', 'cycle_no']);
        });

        Schema::create('hosts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('live_classes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->foreignId('host_id')->nullable()->constrained('hosts')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_classes');
        Schema::dropIfExists('hosts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_subscriptions');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('installment_plans');
        Schema::dropIfExists('courses');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
