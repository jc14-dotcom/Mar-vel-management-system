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
        Schema::create('vouchers', function (Blueprint $table): void {
            $table->id();
            $table->string('voucher_number')->unique();
            $table->date('voucher_date');
            $table->string('payee');
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('project_name_snapshot');
            $table->text('scope_of_work')->nullable();
            $table->text('expense_category')->nullable();
            $table->string('payment_type', 20);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('prepared_by');
            $table->decimal('total_amount', 12, 2);
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
