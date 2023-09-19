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
        Schema::create('posting_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fk_posting_id')->constrained('postings');
            $table->unsignedInteger('position');
            $table->unsignedInteger('total');
            $table->date('check_date');
            $table->timestamps();

            $table->unique(['fk_posting_id', 'check_date'], 'postings_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postings');
    }
};
