<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Reader;

use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\IntegrationBundle\Entity\Channel;

class ProductImageReader extends IteratorBasedReader
{
    /** @var DoctrineHelper */
    private $doctrineHelper;

    /**
     * @var AkeneoSettings
     */
    private $transport;

    /**
     * @var array
     */
    private $attributesImageFilter = [];

    public function setDoctrineHelper(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    protected function initializeFromContext(ContextInterface $context)
    {
        parent::initializeFromContext($context);

        $this->initAttributesImageList();

        $items = $this->stepExecution
                ->getJobExecution()
                ->getExecutionContext()
                ->get('items') ?? [];

        $images = [];
        foreach ($items as &$item) {
            foreach ($item['values'] as $code => &$values) {
                if (empty($this->attributesImageFilter) || in_array($code, $this->attributesImageFilter)) {
                    foreach ($values as $value) {
                        if ('pim_catalog_image' !== $value['type'] || empty($value['data'])) {
                            continue;
                        }

                        $images[] = [
                            'SKU'  => $item['identifier'] ?? $item['code'],
                            'Name' => basename($value['data']),
                        ];

                        if ($this->getTransport()->isAkeneoMergeImageToParent() && !empty($item['parent'])) {
                            $images[] = [
                                'SKU'  => $item['parent'],
                                'Name' => basename($value['data']),
                            ];
                        }
                    }
                }
            }
        }

        $this->stepExecution->setReadCount(count($images));

        $this->setSourceIterator(new \ArrayIterator($images));
    }

    protected function initAttributesImageList()
    {
        $this->attributesImageFilter = [];
        $list = $this->getTransport()->getAkeneoAttributesImageList();
        if (!empty($list)) {
            $this->attributesImageFilter = explode(';', $list);
        }
    }

    /**
     * @return AkeneoSettings
     */
    private function getTransport(): ?AkeneoSettings
    {
        if (!$this->transport) {
            if (!$this->getContext() || false === $this->getContext()->hasOption('channel')) {
                return null;
            }

            $channelId = $this->getContext()->getOption('channel');
            $channel = $this->doctrineHelper->getEntityRepositoryForClass(Channel::class)->find($channelId);

            if (!$channel) {
                return null;
            }

            $this->transport = $channel->getTransport();
        }

        return $this->transport;
    }
}
