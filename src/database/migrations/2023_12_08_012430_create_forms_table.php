<?php

use App\Models\User;
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
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->tinyText('image')->nullable();
            $table->string('title', 1000);
            $table->string('slug', 1000);
            $table->text('description')->nullable();
            $table->smallInteger('time_limit')->nullable();
            $table->date('expire_date')->nullable();
            $table->boolean('is_published')->nullable();
            $table->boolean('accept_response')->nulllable()->default(true);
            $table->boolean('multiple_attempts')->nullable();
            $table->boolean('is_quiz')->nullable();
            $table->boolean('show_results')->nullable();
            $table->tinyInteger('default_points')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
