<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('city')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('avatar_path')->nullable();
            $table->json('privacy_settings')->default('{"name":false,"email":false,"firstName":false,"lastName":false,"city":false,"birthDate":false,"avatar":false}');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'city',
                'birth_date',
                'avatar_path',
                'privacy_settings',
            ]);
        });
    }
};
