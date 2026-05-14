<?php

namespace Pterodactyl\Services\Backups;

use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\Service\Backup\BackupStorageLimitException;

class BackupQuotaService
{
    private const BYTES_PER_MIB = 1024 * 1024;

    /**
     * BackupQuotaService constructor.
     */
    public function __construct(private DeleteBackupService $deleteBackupService)
    {
    }

    /**
     * @throws \Throwable
     * @throws BackupStorageLimitException
     */
    public function ensureStorageIsAvailable(Server $server, bool $override = false): void
    {
        if ($server->backup_storage_limit <= 0 || $this->getUsedStorage($server) < $this->getStorageLimit($server)) {
            return;
        }

        if (!$override) {
            throw new BackupStorageLimitException($server->backup_storage_limit);
        }

        if (!$this->pruneUnlockedBackups($server, 1)) {
            throw new BackupStorageLimitException($server->backup_storage_limit);
        }
    }

    /**
     * @throws \Throwable
     */
    public function prepareStorageForCompletedBackup(Backup $backup, int $size): bool
    {
        $server = $backup->server;

        if ($server->backup_storage_limit <= 0) {
            return true;
        }

        return $this->pruneUnlockedBackups($server, $size, $backup);
    }

    public function getUsedStorage(Server $server, ?Backup $excluding = null): int
    {
        $query = $server->backups()
            ->where(function ($query) {
                $query->whereNull('completed_at')
                    ->orWhere('is_successful', true);
            });

        if (!is_null($excluding)) {
            $query->whereKeyNot($excluding->id);
        }

        return (int) $query->sum('bytes');
    }

    private function getStorageLimit(Server $server): int
    {
        return $server->backup_storage_limit * self::BYTES_PER_MIB;
    }

    /**
     * @throws \Throwable
     */
    private function pruneUnlockedBackups(Server $server, int $requiredBytes, ?Backup $protectedBackup = null): bool
    {
        $storageLimit = $this->getStorageLimit($server);

        while ($this->getUsedStorage($server, $protectedBackup) + $requiredBytes > $storageLimit) {
            $oldest = $server->backups()
                ->where('is_locked', false)
                ->where(function ($query) {
                    $query->whereNull('completed_at')
                        ->orWhere('is_successful', true);
                })
                ->when(!is_null($protectedBackup), function ($query) use ($protectedBackup) {
                    $query->whereKeyNot($protectedBackup->id);
                })
                ->orderBy('created_at')
                ->first();

            if (!$oldest) {
                return false;
            }

            $this->deleteBackupService->handle($oldest);
        }

        return true;
    }
}
