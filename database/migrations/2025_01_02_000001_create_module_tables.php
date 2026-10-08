<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Results import ---
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('enrollment_number')->nullable()->unique();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('max_marks')->default(100);
            $table->timestamps();
        });

        Schema::create('course_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unique(['course_id', 'subject_id']);
        });

        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('marks')->nullable(); // null when absent
            $table->boolean('absent')->default(false);
            $table->timestamps();
            // Re-importing the same sheet updates rows instead of duplicating them.
            $table->unique(['enrollment_id', 'subject_id']);
        });

        // --- Admission drafts ---
        Schema::create('admission_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('course_id')->constrained();
            $table->string('student_name');
            $table->string('student_email');
            $table->string('status')->default('draft'); // draft|approved|rejected
            $table->boolean('paid')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('student_id')->nullable()->constrained('users');
            $table->timestamps();
        });

        // --- Invoicing (accounting-system style: contact first, then invoice) ---
        Schema::create('invoice_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->unsignedInteger('amount');
            $table->string('status')->default('draft');
            $table->json('custom_fields')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_contacts');
        Schema::dropIfExists('admission_drafts');
        Schema::dropIfExists('results');
        Schema::dropIfExists('course_subjects');
        Schema::dropIfExists('subjects');
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('enrollment_number');
        });
    }
};
