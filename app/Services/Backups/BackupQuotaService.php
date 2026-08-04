<?php

namespace Pterodactyl\Services\Backups;

use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\Service\Backup\BackupDiskLimitException;

class BackupQuotaService
{
    private const BYTES_PER_MIB = 1024 * 1024;

    /**
     * Ensure there is room under the server disk space limit for a new backup.
     * Does not delete existing backups; blocks creation when the limit is reached.
     *
     * @throws BackupDiskLimitException
     */
    public function ensureStorageIsAvailable(Server $server, bool $override = false): void
    {
        $limit = $this->getStorageLimit($server);
        if ($limit <= 0 || $this->getUsedStorage($server) < $limit) {
            return;
        }

        throw new BackupDiskLimitException($server->disk);
    }

    /**
     * Check whether a completed backup fits under the disk space limit.
     * Never deletes existing backups.
     */
    public function prepareStorageForCompletedBackup(Backup $backup, int $size): bool
    {
        $server = $backup->server;
        $limit = $this->getStorageLimit($server);

        if ($limit <= 0) {
            return true;
        }

        return ($this->getUsedStorage($server, $backup) + $size) <= $limit;
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
        if ($server->disk <= 0) {
            return 0;
        }

        return $server->disk * self::BYTES_PER_MIB;
    }
}
