<?php

namespace Pterodactyl\Tests\Integration\Services\Backups;

use GuzzleHttp\Psr7\Response;
use Pterodactyl\Models\Backup;
use Pterodactyl\Services\Backups\InitiateBackupService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Repositories\Wings\DaemonBackupRepository;
use Pterodactyl\Exceptions\Service\Backup\TooManyBackupsException;

class InitiateBackupServiceTest extends IntegrationTestCase
{
    public function testLargeBackupsDoNotPreventCreatingAnotherBackup(): void
    {
        config()->set('backups.throttles.period', 0);

        $server = $this->createServerModel(['backup_limit' => 2]);
        $existing = Backup::factory()->create([
            'server_id' => $server->id,
            'bytes' => 1024 ** 4,
            'is_locked' => true,
        ]);

        $repository = $this->mock(DaemonBackupRepository::class);
        $repository->expects('setServer->setBackupAdapter->backup')
            ->with(\Mockery::on(function (Backup $backup) use ($server) {
                return $backup->exists && $backup->server_id === $server->id;
            }))
            ->andReturn(new Response());

        $backup = $this->app->make(InitiateBackupService::class)->handle($server, 'New backup');

        $this->assertSame('New backup', $backup->name);
        $this->assertDatabaseHas('backups', ['id' => $existing->id, 'deleted_at' => null]);
        $this->assertSame(2, $server->backups()->count());
    }

    public function testBackupCountLimitStillPreventsCreatingAnotherBackup(): void
    {
        config()->set('backups.throttles.period', 0);

        $server = $this->createServerModel(['backup_limit' => 1]);
        Backup::factory()->create(['server_id' => $server->id]);

        $this->mock(DaemonBackupRepository::class)->shouldNotReceive('setServer');

        $this->expectException(TooManyBackupsException::class);

        $this->app->make(InitiateBackupService::class)->handle($server);
    }
}
