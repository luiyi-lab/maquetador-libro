<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('format', 10); // md | txt
            $table->string('original_path'); // relative to storage, never overwritten
            $table->string('original_name');
            $table->json('snapshot')->nullable(); // BookDocument at import time
            $table->json('warnings')->nullable();
            $table->json('normalization')->nullable(); // registered technical cleanups (§10)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_versions');
    }
};
