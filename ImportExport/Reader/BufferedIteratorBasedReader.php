<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Reader;

use Oro\Bundle\ImportExportBundle\Exception\LogicException;

class BufferedIteratorBasedReader extends IteratorBasedReader
{
    /** @var int */
    private $currentId;

    public function read()
    {
        if (null === $this->getSourceIterator()) {
            throw new LogicException('Reader must be configured with source');
        }

        if (!$this->rewound) {
            $this->sourceIterator->rewind();
            $this->rewound = true;
        }

        if ($this->sourceIterator->valid()) {
            $current = $this->sourceIterator->current();
            if ($current->getId() !== $this->currentId) {
                $this->currentId = $current->getId();

                return $current;
            }
        }

        $this->sourceIterator->next();

        if ($this->sourceIterator->valid()) {
            $current = $this->sourceIterator->current();
            $this->currentId = $current->getId();

            return $current;
        }

        return null;
    }
}
