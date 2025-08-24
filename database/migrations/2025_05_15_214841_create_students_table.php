<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('father_name');
            $table->string('mother_name');
            $table->string('father_number');
            $table->string('mother_number');
            $table->decimal('cashed_points', 8, 2)->default(0.00);
            $table->enum('gender', ['male', 'female']);
            $table->string('location');
            $table->date('birth_day');
            $table->string('diseases')->nullable();
            $table->string('special_notes')->nullable();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
