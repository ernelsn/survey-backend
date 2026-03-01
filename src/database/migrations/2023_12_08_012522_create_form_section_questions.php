<?php

use App\Models\FormSection;
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
        Schema::create('form_section_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(FormSection::class)
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('type', 45);
            $table->text('question');
            $table->text('description')->nullable();
            $table->tinyInteger('points')->nullable();
            $table->json('data')->nullable();
            $table->boolean('is_required')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_section_questions');
    }
};
