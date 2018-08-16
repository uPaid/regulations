<?php

namespace Upaid\Regulations\Services;


class CommandService
{

    const BACKUP_DIRECTORY = 'storage/regulations_backup';
    const REGULATIONS_DIRECTORY = 'storage/regulations';

    /**
     * Gets full path to file in application.
     *
     * @param $fileName
     *
     * @return null|string
     */
    public function getFilePath($fileName)
    {
        return 'storage/regulations/'. $fileName;
    }

    /**
     * Create backup directory if not exist
     *
     * @return bool
     */
    function makeBcDir()
    {
        $ret = @mkdir(self::BACKUP_DIRECTORY);
        return $ret === true || is_dir(self::BACKUP_DIRECTORY);
    }
}


