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
        Schema::table('trips', function (Blueprint $table) {
            $table->index(['start_date', 'end_date'], 'trips_dates_index');
        });

        Schema::table('places', function (Blueprint $table) {
            $table->index('country_code', 'places_country_code_index');
        });

        Schema::table('legs', function (Blueprint $table) {
            $table->index('transport', 'legs_transport_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('profile_visibility', 'users_profile_visibility_index');
        });

        Schema::table('friend_requests', function (Blueprint $table) {
            $table->index(['sender_id', 'status'], 'friend_requests_sender_status_index');
            $table->index(['recipient_id', 'status'], 'friend_requests_recipient_status_index');
        });

        Schema::table('trip_invitations', function (Blueprint $table) {
            $table->index(['invitee_id', 'status'], 'trip_invitations_invitee_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex('trips_dates_index');
        });

        Schema::table('places', function (Blueprint $table) {
            $table->dropIndex('places_country_code_index');
        });

        Schema::table('legs', function (Blueprint $table) {
            $table->dropIndex('legs_transport_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_profile_visibility_index');
        });

        Schema::table('friend_requests', function (Blueprint $table) {
            $table->dropIndex('friend_requests_sender_status_index');
            $table->dropIndex('friend_requests_recipient_status_index');
        });

        Schema::table('trip_invitations', function (Blueprint $table) {
            $table->dropIndex('trip_invitations_invitee_status_index');
        });
    }
};
