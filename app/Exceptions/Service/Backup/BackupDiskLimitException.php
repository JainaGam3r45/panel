<?php

namespace Pterodactyl\Exceptions\Service\Backup;

use Pterodactyl\Exceptions\DisplayException;

class BackupDiskLimitException extends DisplayException
{
    /**
     * BackupDiskLimitException constructor.
     */
    public function __construct(int $diskLimit)
    {
        parent::__construct(
            sprintf('Cannot create a new backup, this server has reached its disk space limit of %d MiB for backup storage.', $diskLimit)
        );
    }
}
