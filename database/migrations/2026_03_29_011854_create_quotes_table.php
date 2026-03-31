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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->string('reference_code', 120)->nullable();
            $table->string('client_name');
            $table->string('client_rfc', 30)->nullable();
            $table->string('location', 120)->nullable();
            $table->date('issued_at');
            $table->string('currency', 3)->default('MXN');
            $table->decimal('vat_rate', 5, 2)->default(16);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->string('status', 40)->default('emitida');
            $table->text('terms')->nullable();
            $table->string('contact_phone', 60)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
