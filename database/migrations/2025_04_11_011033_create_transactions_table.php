<?php

use App\Models\Customer;
use App\Models\Voucher;
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
            $table->uuid('id');
            $table->foreignIdFor(Customer::class);
            $table->foreignIdFor(Voucher::class)->nullable();
            $table->string('status')->nullable();
            $table->string('midtrans_payment_method')->nullable();
            $table->string('midtrans_token')->nullable();
            $table->string('midtrans_redirect_url')->nullable();
            $table->string('fraud_status')->nullable();
            $table->timestamp('settlement_time')->nullable();
            $table->string('qr_url')->nullable();
            $table->integer('total_before_discount')->nullable();
            $table->integer('total')->nullable();
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
