<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Serializer\Normalizer;

use Oro\Bundle\AkeneoBundle\Integration\AkeneoChannel;
use Oro\Bundle\ProductBundle\ImportExport\Normalizer\ProductImageNormalizer as BaseProductImageNormalizer;

class ProductImageNormalizer extends BaseProductImageNormalizer
{
    public function supportsNormalization($data, $format = null, array $context = [])
    {
        $supports = parent::supportsNormalization($data, $format, $context);
        if ($supports) {
            return ($context['channelType'] ?? null) === AkeneoChannel::TYPE;
        }

        return $supports;
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = [])
    {
        $supports = parent::supportsDenormalization($data, $type, $format, $context);
        if ($supports) {
            return ($context['channelType'] ?? null) === AkeneoChannel::TYPE;
        }

        return $supports;
    }
}
