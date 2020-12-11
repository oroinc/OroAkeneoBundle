<?php

namespace Oro\Bundle\AkeneoBundle\Manager;

use Oro\Bundle\AkeneoBundle\Integration\AkeneoFileManager;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\AttachmentBundle\Manager\FileManager as BaseManager;

class FileManager extends BaseManager
{
    /** @var AkeneoFileManager */
    private $akeneoFileManager;

    public function setFileFromPath(File $file, string $path): void
    {
        $this->akeneoFileManager->download($file, $path);

        parent::setFileFromPath($file, $path);
    }

    public function setAkeneoFileManager(AkeneoFileManager $akeneoFileManager): void
    {
        $this->akeneoFileManager = $akeneoFileManager;
    }
}
