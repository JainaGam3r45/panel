<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveBackupStorageLimits extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('servers', 'backup_storage_limit')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->dropColumn('backup_storage_limit');
            });
        }

        if (Schema::hasColumn('backups', 'failure_reason')) {
            Schema::table('backups', function (Blueprint $table) {
                $table->dropColumn('failure_reason');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('servers', 'backup_storage_limit')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->unsignedInteger('backup_storage_limit')->default(0)->after('backup_limit');
            });
        }

        if (!Schema::hasColumn('backups', 'failure_reason')) {
            Schema::table('backups', function (Blueprint $table) {
                $table->string('failure_reason')->nullable()->after('bytes');
            });
        }
    }
}
