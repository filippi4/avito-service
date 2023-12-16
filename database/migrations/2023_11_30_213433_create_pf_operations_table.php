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
        Schema::create('pf_operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation_type', 20)->comment('accrual - Начисление / outcome - Выплата');
            $table->date('operation_date')->comment('Дата оплаты');
            $table->boolean('is_calculation_committed')->default(true)->comment('Подтвердить начисление');
            $table->boolean('is_committed')->default(true)->comment('Подтвердить оплату');
            $table->unsignedBigInteger('account_id')->comment('Счет и юрлицо');
            $table->string('account_title')->comment('Название счета');
            $table->decimal('value')->comment('Сумма');
            $table->string('currency_code', 10)->comment('Валюта');
            $table->string('comment')->comment('Назначение платежа');
            $table->timestamp('operation_dt');
            $table->unsignedTinyInteger('status')->default(0)
                ->comment('0 - ожидает / 1 - завершен / 2 - ошибка');
            $table->timestamps();

            $table->unique(['operation_dt', 'value', 'comment'], 'pf_operations_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pf_operations');
    }
};
