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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('care_request_id')->unique();
            $table->unsignedBigInteger('patient_user_id');
            $table->unsignedBigInteger('nurse_user_id');
            $table->unsignedTinyInteger('rating'); // validate 1..5 in app
            $table->text('comment')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('care_request_id')->references('id')->on('care_requests')->cascadeOnDelete();
            $table->foreign('patient_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('nurse_user_id')->references('id')->on('users')->restrictOnDelete();

            $table->index(['nurse_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
