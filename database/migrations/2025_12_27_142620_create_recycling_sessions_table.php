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
        Schema::create('recycling_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recycling_bin_id')->constrained('recycling_bins')->cascadeOnDelete();
            $table->string('session_token', 64)->unique();
            $table->string('proof_photo_path')->nullable();
            $table->enum('status', ['active', 'accepted', 'flagged', 'rejected']);
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->index(); //why did we index this?
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recycling_sessions');
    }
};
