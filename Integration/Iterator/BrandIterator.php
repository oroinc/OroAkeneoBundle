<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Iterator;

use Akeneo\Pim\ApiClient\Pagination\ResourceCursorInterface;
use Oro\Bundle\AkeneoBundle\Integration\AkeneoPimExtendableClientInterface;
use Oro\Bundle\AkeneoBundle\Integration\AkeneoTransportInterface;
use Psr\Log\LoggerInterface;

class BrandIterator extends AbstractIterator
{
    /** @var AkeneoTransportInterface */
    private $akeneoTransport;

    public function __construct(
        ResourceCursorInterface $resourceCursor,
        AkeneoPimExtendableClientInterface $client,
        LoggerInterface $logger,
        AkeneoTransportInterface $akeneoTransport
    ) {
        parent::__construct($resourceCursor, $client, $logger);

        $this->akeneoTransport = $akeneoTransport;
    }

    public function doCurrent()
    {
        $brand = $this->resourceCursor->current();

        $this->downloadImagesAndFiles($brand);

        return $brand;
    }

    private function downloadImagesAndFiles(array &$brand): void
    {
        foreach ($brand['values'] as $values) {
            foreach ($values as $value) {
                if (!empty($value['_links']['download']['href'])) {
                    $this->akeneoTransport->downloadAndSaveReferenceEntityMediaFile($value['data']);
                }
            }
        }
    }
}
