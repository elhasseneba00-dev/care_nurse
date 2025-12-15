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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('care_request_id');
            $table->unsignedBigInteger('sender_user_id');
            $table->text('message');
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('care_request_id')->references('id')->on('care_requests')->cascadeOnDelete();
            $table->foreign('sender_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['care_request_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
