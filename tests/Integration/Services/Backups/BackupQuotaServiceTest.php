<?php

namespace Pterodactyl\Tests\Integration\Services\Backups;

use GuzzleHttp\Psr7\Response;
use Pterodactyl\Models\Backup;
use Pterodactyl\Services\Backups\BackupQuotaService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Repositories\Wings\DaemonBackupRepository;
use Pterodactyl\Exceptions\Service\Backup\BackupStorageLimitException;

class BackupQuotaServiceTest extends IntegrationTestCase
{
    public function testStorageLimitBlocksNewBackupsWhenOverrideIsDisabled()
    {
        $server = $this->createServerModel(['backup_storage_limit' => 1]);
        Backup::factory()->create([
            'server_id' => $server->id,
            'bytes' => 1024 * 1024,
        ]);

        $this->expectException(BackupStorageLimitException::class);

        $this->getService()->ensureStorageIsAvailable($server);
    }

    public function testStorageLimitAllowsUnlimitedStorageWhenSetToZero()
    {
        $server = $this->createServerModel(['backup_storage_limit' => 0]);
        Backup::factory()->create([
            'server_id' => $server->id,
            'bytes' => 1024 * 1024,
        ]);

        $this->getService()->ensureStorageIsAvailable($server);

        $this->assertDatabaseHas('backups', ['server_id' => $server->id, 'deleted_at' => null]);
    }

    public function testOverrideRotatesOldUnlockedBackupsToFreeStorage()
    {
        $server = $this->createServerModel(['backup_storage_limit' => 1]);
        $backup = Backup::factory()->create([
            'server_id' => $server->id,
            'bytes' => 1024 * 1024,
        ]);

        $this->mockDeleteRequest($backup);

        $this->getService()->ensureStorageIsAvailable($server, true);

        $this->assertSoftDeleted($backup);
    }

    public function testOverrideDoesNotRotateLockedBackups()
    {
        $server = $this->createServerModel(['backup_storage_limit' => 1]);
        Backup::factory()->create([
            'server_id' => $server->id,
            'bytes' => 1024 * 1024,
            'is_locked' => true,
        ]);

        $this->expectException(BackupStorageLimitException::class);

        $this->getService()->ensureStorageIsAvailable($server, true);
    }

    public function testCompletedBackupPrunesOldBackupsBeforeFailingCurrentBackup()
    {
        $server = $this->createServerModel(['backup_storage_limit' => 2]);
        $oldBackup = Backup::factory()->create([
            'server_id' => $server->id,
            'bytes' => 1024 * 1024,
        ]);
        $currentBackup = Backup::factory()->create([
            'server_id' => $server->id,
            'bytes' => 0,
            'completed_at' => null,
            'is_successful' => false,
        ]);

        $this->mockDeleteRequest($oldBackup);

        $this->assertTrue($this->getService()->prepareStorageForCompletedBackup($currentBackup, 2 * 1024 * 1024));
        $this->assertSoftDeleted($oldBackup);
    }

    private function getService(): BackupQuotaService
    {
        return $this->app->make(BackupQuotaService::class);
    }

    private function mockDeleteRequest(Backup $backup): void
    {
        $mock = $this->mock(DaemonBackupRepository::class);
        $mock->expects('setServer->delete')->with(\Mockery::on(function (Backup $value) use ($backup) {
            return $value->id === $backup->id;
        }))->andReturn(new Response());
    }
}
