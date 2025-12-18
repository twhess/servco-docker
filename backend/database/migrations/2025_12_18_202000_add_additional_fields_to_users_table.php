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
        Schema::table('users', function (Blueprint $table) {
            // Rename name column to username
            $table->renameColumn('name', 'username');

            // Add new fields
            $table->string('employee_id')->nullable()->after('id');
            $table->string('first_name')->nullable()->after('username');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('preferred_name')->nullable()->after('last_name');
            $table->string('phone_number')->nullable()->after('preferred_name');
            $table->string('pin_code')->nullable()->after('phone_number');
            $table->string('home_shop')->nullable()->after('pin_code');
            $table->string('personal_email')->nullable()->after('email');
            $table->string('slack_id')->nullable()->after('personal_email');
            $table->string('dext_email')->nullable()->after('slack_id');
            $table->text('address')->nullable()->after('dext_email');
            $table->string('paytype')->nullable()->after('address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove added fields
            $table->dropColumn([
                'employee_id',
                'first_name',
                'last_name',
                'preferred_name',
                'phone_number',
                'pin_code',
                'home_shop',
                'personal_email',
                'slack_id',
                'dext_email',
                'address',
                'paytype',
            ]);

            // Rename username back to name
            $table->renameColumn('username', 'name');
        });
    }
};
