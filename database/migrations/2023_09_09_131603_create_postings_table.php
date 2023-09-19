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
        Schema::create('postings', function (Blueprint $table) {
            $table->id();
            $table->string('query');
            $table->text('query_url');
            $table->string('region');
            $table->unsignedInteger('post_count');
            $table->unsignedInteger('freq_per_month');
            $table->unsignedInteger('exact_freq');
            $table->unsignedBigInteger('post_id')->index();
            $table->string('account');
            $table->string('post_url');
            $table->timestamps();

            $table->unique(['query', 'post_id'], 'postings_unique');
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
