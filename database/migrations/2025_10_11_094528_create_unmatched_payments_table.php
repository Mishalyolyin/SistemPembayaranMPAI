<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('unmatched_payments', function (Blueprint $table) {
            $table->id();
            $table->string('journal_seq', 128)->unique();
            $table->string('bank_code', 16)->nullable();
            $table->string('briva_no', 64)->nullable();
            $table->string('cust_code', 64)->nullable();
            $table->string('nim', 64)->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('reason', 64)->nullable(); // amount_mismatch | invoice_not_found | dll
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('cust_code');
            $table->index('nim');
        });
    }

    public function down(): void {
        Schema::dropIfExists('unmatched_payments');
    }
};
