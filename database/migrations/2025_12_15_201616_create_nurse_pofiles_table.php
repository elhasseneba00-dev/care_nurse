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
        Schema::create('nurse_pofiles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('diploma', 150)->nullable();
            $table->unsignedInteger('experience_years')->default(0);
            $table->text('bio')->nullable();
            $table->string('city', 80)->nullable();
            $table->text('address')->nullable();
            $table->double('lat')->nullable();
            $table->double('lng')->nullable();
            $table->unsignedInteger('coverage_km')->default(10);
            $table->unsignedInteger('price_min')->nullable();
            $table->unsignedInteger('price_max')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nurse_pofiles');
    }
};
