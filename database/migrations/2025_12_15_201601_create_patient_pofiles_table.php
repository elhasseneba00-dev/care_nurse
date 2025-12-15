<?php

use App\Models\User;
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
        Schema::create('patient_pofiles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable(); // M | F | OTHER
            $table->string('city', 80)->nullable();
            $table->text('address')->nullable();
            $table->double('lat')->nullable();
            $table->double('lng')->nullable();
            $table->text('medical_notes')->nullable();
            $table->timestamps();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_pofiles');
    }
};
