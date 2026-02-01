<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('financial_statements', function (Blueprint $table) {
            $table->id();
            $table->timestamp('period');
            $table->integer('transaction_count');
            $table->bigInteger('income');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_statements');
    }
};
