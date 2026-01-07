<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // PIN authentication fields
            $table->string('pin_hash')->nullable()->after('password');
            $table->boolean('pin_enabled')->default(false)->after('pin_hash');
            $table->tinyInteger('pin_failed_attempts')->unsigned()->default(0)->after('pin_enabled');
            $table->dateTime('pin_locked_until')->nullable()->after('pin_failed_attempts');

            // Alert preferences for leaving stop with open items
            $table->boolean('alert_on_leave_with_open')->default(true)->after('pin_locked_until');
            $table->boolean('alert_email_enabled')->default(true)->after('alert_on_leave_with_open');
            $table->boolean('alert_slack_enabled')->default(false)->after('alert_email_enabled');
            $table->boolean('alert_popup_enabled')->default(true)->after('alert_slack_enabled');
            $table->boolean('alert_sms_enabled')->default(false)->after('alert_popup_enabled');

            // Contact info for alerts
            $table->string('phone_e164', 20)->nullable()->after('alert_sms_enabled');
            $table->string('slack_member_id', 50)->nullable()->after('phone_e164');

            // Index for PIN lookup (must be unique for PIN auth to work)
            $table->index('pin_hash', 'users_pin_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_pin_hash_idx');

            $table->dropColumn([
                'pin_hash',
                'pin_enabled',
                'pin_failed_attempts',
                'pin_locked_until',
                'alert_on_leave_with_open',
                'alert_email_enabled',
                'alert_slack_enabled',
                'alert_popup_enabled',
                'alert_sms_enabled',
                'phone_e164',
                'slack_member_id',
            ]);
        });
    }
};
