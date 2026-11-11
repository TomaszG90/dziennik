<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legs', function (Blueprint $table) {
            $table->json('geometry')->nullable()->after('distance');
        });
    }

    public function down(): void
    {
        Schema::table('legs', function (Blueprint $table) {
            $table->dropColumn('geometry');
        });
    }
};
