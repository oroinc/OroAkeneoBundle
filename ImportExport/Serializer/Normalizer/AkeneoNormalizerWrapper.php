<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Serializer\Normalizer;

use Oro\Bundle\AkeneoBundle\Integration\AkeneoChannel;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;

class AkeneoNormalizerWrapper implements ContextAwareDenormalizerInterface
{
    /** @var ContextAwareDenormalizerInterface */
    private $fileNormalizer;

    public function __construct(ContextAwareDenormalizerInterface $fileNormalizer)
    {
        $this->fileNormalizer = $fileNormalizer;
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = [])
    {
        $supports = $this->fileNormalizer->supportsDenormalization($data, $type, $format, $context);
        if ($supports) {
            return AkeneoChannel::TYPE === ($context['channelType'] ?? null);
        }

        return $supports;
    }

    public function denormalize($data, $type, $format = null, array $context = [])
    {
        return $this->fileNormalizer->denormalize($data, $type, $format, $context);
    }
}
