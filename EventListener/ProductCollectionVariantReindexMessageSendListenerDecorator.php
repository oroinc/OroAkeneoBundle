<?php

namespace Oro\Bundle\AkeneoBundle\EventListener;

use Oro\Bundle\ProductBundle\EventListener\ProductCollectionVariantReindexMessageSendListener as BaseListener;
use Oro\Bundle\SegmentBundle\Entity\Segment;

/**
 * Postpone segments updates
 */
class ProductCollectionVariantReindexMessageSendListenerDecorator extends BaseListener implements
    AdditionalOptionalListenerInterface
{
    use AdditionalOptionalListenerTrait;

    /**
     * @var BaseListener
     */
    protected $innerListener;

    public function __construct(BaseListener $innerListener)
    {
        $this->innerListener = $innerListener;
    }

    public function postFlush(): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerListener->postFlush();
    }

    /**
     * @param bool $isFull
     */
    public function scheduleSegment(Segment $segment, $isFull = false): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerListener->scheduleSegment($segment, $isFull);
    }

    public function scheduleAdditionalProductsBySegment(Segment $segment, array $additionalProducts = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerListener->scheduleAdditionalProductsBySegment($segment, $additionalProducts);
    }

    public function scheduleMessageBySegmentDefinition(Segment $segment): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->innerListener->scheduleMessageBySegmentDefinition($segment);
    }
}
