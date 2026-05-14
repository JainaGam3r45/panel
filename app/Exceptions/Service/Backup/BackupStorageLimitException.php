<?php

namespace Pterodactyl\Exceptions\Service\Backup;

use Pterodactyl\Exceptions\DisplayException;

class BackupStorageLimitException extends DisplayException
{
    /**
     * BackupStorageLimitException constructor.
     */
    public function __construct(int $storageLimit)
    {
        parent::__construct(
            sprintf('Cannot create a new backup, this server has reached its backup storage limit of %d MiB.', $storageLimit)
        );
    }
}
