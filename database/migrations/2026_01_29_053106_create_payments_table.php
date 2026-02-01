<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->string('order_id')->unique();
            $table->integer('gross_amount');
            $table->string('payment_type')->nullable();
            $table->enum('transaction_status', [
                'pending',
                'settlement',
                'capture',
                'deny',
                'cancel',
                'expire',
                'refund'
            ])->default('pending');
            $table->string('fraud_status')->nullable();
            $table->string('midtrans_transaction_id')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->foreign('transaction_id')
                ->references('id')
                ->on('transactions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
