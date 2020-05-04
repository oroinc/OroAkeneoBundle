<?php

namespace Oro\Bundle\AkeneoBundle\Client\Api;

use Akeneo\Pim\ApiClient\Client\ResourceClientInterface;
use Akeneo\Pim\ApiClient\FileSystem\FileSystemInterface;
use Akeneo\Pim\ApiClient\Pagination\PageFactoryInterface;
use Akeneo\Pim\ApiClient\Pagination\ResourceCursorFactoryInterface;

interface ApiAwareInterface
{
    /**
     * @param ResourceClientInterface $resourceClient
     * @return ApiAwareInterface
     */
    public function setResourceClient(ResourceClientInterface $resourceClient): ApiAwareInterface;

    /**
     * @param PageFactoryInterface $pageFactory
     * @return ApiAwareInterface
     */
    public function setPageFactory(PageFactoryInterface $pageFactory): ApiAwareInterface;

    /**
     * @param ResourceCursorFactoryInterface $cursorFactory
     * @return ApiAwareInterface
     */
    public function setCursorFactory(ResourceCursorFactoryInterface $cursorFactory): ApiAwareInterface;

    /**
     * @param FileSystemInterface $fileSystem
     * @return ApiAwareInterface
     */
    public function setFileSystem(FileSystemInterface $fileSystem): ApiAwareInterface;
}
