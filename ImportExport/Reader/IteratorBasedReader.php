<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Reader;

use Oro\Bundle\ImportExportBundle\Exception\LogicException;
use Oro\Bundle\ImportExportBundle\Reader\AbstractReader;

abstract class IteratorBasedReader extends AbstractReader
{
    /**
     * @var \Iterator
     */
    protected $sourceIterator;

    /**
     * @var bool
     */
    protected $rewound = false;

    /**
     * {@inheritdoc}
     */
    public function read()
    {
        if (null === $this->getSourceIterator()) {
            throw new LogicException('Reader must be configured with source');
        }
        if (!$this->rewound) {
            $this->sourceIterator->rewind();
            $this->rewound = true;
        }

        $result = null;
        if ($this->sourceIterator->valid()) {
            $result = $this->sourceIterator->current();
            $this->sourceIterator->next();
        }

        return $result;
    }

    /**
     * Getter for iterator
     *
     * @return \Iterator|null
     */
    public function getSourceIterator()
    {
        return $this->sourceIterator;
    }

    /**
     * Setter for iterator
     *
     * @param \Iterator $sourceIterator
     */
    public function setSourceIterator(\Iterator $sourceIterator = null)
    {
        $this->sourceIterator = $sourceIterator;
        $this->rewound = false;
    }
}
