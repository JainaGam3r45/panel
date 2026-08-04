<?php

namespace Pterodactyl\Tests\Integration\Services\Backups;

use Pterodactyl\Models\Backup;
use Pterodactyl\Services\Backups\BackupQuotaService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Exceptions\Service\Backup\BackupDiskLimitException;

class BackupQuotaServiceTest extends IntegrationTestCase
{
    public function testDiskLimitBlocksNewBackupsWhenStorageIsFull()
    {
        $server = $this->createServerModel(['disk' => 1]);
        Backup::factory()->create([
            'server_id' => $server->id,
            'bytes' => 1024 * 1024,
        ]);

        $this->expectException(BackupDiskLimitException::class);

        $this->getService()->ensureStorageIsAvailable($server);
    }

    public function testDiskLimitAllowsUnlimitedStorageWhenDiskIsZero()
    {
        $server = $this->createServerModel(['disk' => 0]);
        Backup::factory()->create([
            'server_id' => $server->id,
            'bytes' => 1024 * 1024,
        ]);

        $this->getService()->ensureStorageIsAvailable($server);

        $this->assertDatabaseHas('backups', ['server_id' => $server->id, 'deleted_at' => null]);
    }

    public function testOverrideDoesNotDeleteBackupsWhenDiskLimitIsReached()
    {
        $server = $this->createServerModel(['disk' => 1]);
        $backup = Backup::factory()->create([
            'server_id' => $server->id,
            'bytes' => 1024 * 1024,
        ]);

        $this->expectException(BackupDiskLimitException::class);

        try {
            $this->getService()->ensureStorageIsAvailable($server, true);
        } finally {
            $this->assertDatabaseHas('backups', ['id' => $backup->id, 'deleted_at' => null]);
        }
    }

    public function testCompletedBackupIsRejectedWhenItWouldExceedDiskLimit()
    {
        $server = $this->createServerModel(['disk' => 2]);
        Backup::factory()->create([
            'server_id' => $server->id,
            'bytes' => 1024 * 1024,
        ]);
        $currentBackup = Backup::factory()->create([
            'server_id' => $server->id,
            'bytes' => 0,
            'completed_at' => null,
            'is_successful' => false,
        ]);

        $this->assertFalse($this->getService()->prepareStorageForCompletedBackup($currentBackup, 2 * 1024 * 1024));
        $this->assertDatabaseHas('backups', ['server_id' => $server->id, 'deleted_at' => null]);
    }

    public function testCompletedBackupIsAcceptedWhenItFitsUnderDiskLimit()
    {
        $server = $this->createServerModel(['disk' => 3]);
        Backup::factory()->create([
            'server_id' => $server->id,
            'bytes' => 1024 * 1024,
        ]);
        $currentBackup = Backup::factory()->create([
            'server_id' => $server->id,
            'bytes' => 0,
            'completed_at' => null,
            'is_successful' => false,
        ]);

        $this->assertTrue($this->getService()->prepareStorageForCompletedBackup($currentBackup, 2 * 1024 * 1024));
    }

    private function getService(): BackupQuotaService
    {
        return $this->app->make(BackupQuotaService::class);
    }
}
