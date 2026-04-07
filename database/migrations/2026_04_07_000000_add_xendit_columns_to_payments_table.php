<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'xendit_invoice_id')) {
                $table->string('xendit_invoice_id')->nullable()->unique();
            }
            if (!Schema::hasColumn('payments', 'xendit_transaction_id')) {
                $table->string('xendit_transaction_id')->nullable();
            }
            if (!Schema::hasColumn('payments', 'payment_for')) {
                $table->enum('payment_for', ['booking', 'fine'])->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumnIfExists('xendit_invoice_id');
            $table->dropColumnIfExists('xendit_transaction_id');
            $table->dropColumnIfExists('payment_for');
        });
    }
};
