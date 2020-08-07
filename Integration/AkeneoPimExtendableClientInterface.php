<?php

namespace Oro\Bundle\AkeneoBundle\Integration;

use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityApiInterface;
use Oro\Bundle\AkeneoBundle\Integration\Api\ReferenceEntityRecordApiInterface;

interface AkeneoPimExtendableClientInterface
{
    public function getReferenceEntityApi(): ReferenceEntityApiInterface;

    public function setReferenceEntityApi(ReferenceEntityApiInterface $referenceEntityApi): void;

    public function getReferenceEntityRecordApi(): ReferenceEntityRecordApiInterface;

    public function setReferenceEntityRecordApi(ReferenceEntityRecordApiInterface $referenceEntityRecordApi): void;
}
