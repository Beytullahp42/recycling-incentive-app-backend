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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recycling_session_id')->constrained('recycling_sessions')->cascadeOnDelete();
            $table->foreignId('recyclable_item_id')->constrained('recyclable_items')->cascadeOnDelete();
            $table->string('barcode');
            $table->integer('points_awarded');
            $table->enum('status', ['accepted', 'flagged', 'rejected']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
