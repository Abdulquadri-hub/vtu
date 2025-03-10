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
        Schema::create('user_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // bronze, silver, gold
            $table->decimal('monthly_transaction_limit', 12, 2);
            $table->decimal('single_transaction_limit', 10, 2);
            $table->json('benefits');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_tiers');
    }
};
