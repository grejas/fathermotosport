<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('phone');
            $table->timestamp('last_password_change')->nullable()->after('email_verified_at');
            $table->timestamp('security_reminder_dismissed_at')->nullable()->after('last_password_change');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'last_password_change', 'security_reminder_dismissed_at']);
        });
    }
};
