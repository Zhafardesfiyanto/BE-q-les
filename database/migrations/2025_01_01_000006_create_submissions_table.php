<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['dikumpulkan', 'terlambat'])->default('dikumpulkan');
            $table->string('screenshot_path')->nullable();
            $table->json('gesture_log')->nullable();
            $table->decimal('total_grade', 5, 2)->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->unique(['assignment_id', 'user_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};