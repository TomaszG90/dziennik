<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trip_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('from_place_id')
                ->constrained('places')
                ->cascadeOnDelete();

            $table->foreignId('to_place_id')
                ->constrained('places')
                ->cascadeOnDelete();

            $table->string('transport');
            $table->decimal('distance', 10, 2)->nullable();
            $table->unsignedInteger('position');

            $table->timestamps();

            $table->unique(['trip_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legs');
    }
};
