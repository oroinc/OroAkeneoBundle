<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Iterator;

use Akeneo\Pim\ApiClient\Pagination\ResourceCursorInterface;
use Oro\Bundle\AkeneoBundle\Integration\AkeneoPimExtendableClientInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

abstract class AbstractIterator implements \Iterator
{
    use LoggerAwareTrait;

    const PAGE_SIZE = 100;

    /**
     * @var ResourceCursorInterface
     */
    protected $resourceCursor;

    /**
     * @var AkeneoPimExtendableClientInterface
     */
    protected $client;

    /**
     * AttributeIterator constructor.
     */
    public function __construct(
        ResourceCursorInterface $resourceCursor,
        AkeneoPimExtendableClientInterface $client,
        LoggerInterface $logger
    ) {
        $this->resourceCursor = $resourceCursor;
        $this->client = $client;

        $this->setLogger($logger);
    }

    /**
     * {@inheritdoc}
     */
    final public function current()
    {
        return $this->doCurrent();
    }

    /**
     * current() login.
     */
    abstract public function doCurrent();

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->resourceCursor->next();
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->resourceCursor->key();
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return $this->resourceCursor->valid();
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->resourceCursor->rewind();
    }
}
