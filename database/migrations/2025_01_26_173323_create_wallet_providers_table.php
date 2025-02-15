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
        Schema::create('wallet_providers', function (Blueprint $table) {
            $table->id();
            $table->string('provider_name');
            $table->decimal('balance', 10, 2);
            $table->decimal('minimum_balance', 10, 2);
            $table->string('currency')->default('NGN');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_providers');
    }
};
