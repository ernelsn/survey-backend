<?php

use App\Models\FormSectionQuestion;
use App\Models\FormResponse;
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
        Schema::create('form_response_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(FormResponse::class)
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignIdFor(FormSection::class)
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignIdFor(FormSectionQuestion::class)
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->json('response')->nullable();
            $table->boolean('is_correct')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_response_questions');
    }
};
