<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\TransactionStatus;
use App\Enums\SessionLifecycle;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recycling_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recycling_bin_id')->constrained()->cascadeOnDelete();
            $table->string('session_token')->unique();

            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();

            $table->enum('lifecycle_status', array_column(SessionLifecycle::cases(), 'value'))
                ->default(SessionLifecycle::ACTIVE->value);

            $table->enum('audit_status', array_column(TransactionStatus::cases(), 'value'))
                ->default(TransactionStatus::ACCEPTED->value);

            $table->string('proof_photo_path')->nullable();
            $table->timestamps();

            $table->index(['lifecycle_status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recycling_sessions');
    }
};
