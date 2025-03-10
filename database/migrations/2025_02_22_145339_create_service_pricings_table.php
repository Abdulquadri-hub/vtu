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
        Schema::create('service_pricings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers'); // Link to a providers table
            $table->foreignId('service_id')->constrained('services'); // Link to a services table
            $table->decimal('amount', 10, 2); // Price charged to the user
            $table->decimal('provider_amount', 10, 2); // Cost from the provider
            $table->decimal('profit_margin', 5, 2)->nullable(); // Calculated profit margin
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_pricings');
    }
};
