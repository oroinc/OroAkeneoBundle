<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Serializer\Normalizer;

use Oro\Bundle\AkeneoBundle\Integration\AkeneoChannel;
use Oro\Bundle\ImportExportBundle\Serializer\Normalizer\DenormalizerInterface;
use Oro\Bundle\ProductBundle\Entity\Brand;

class BrandNormalizer implements DenormalizerInterface
{
    public function supportsDenormalization($data, $type, $format = null, array $context = [])
    {
        return is_a($type, Brand::class, true) &&
            isset($context['channelType']) &&
            AkeneoChannel::TYPE === $context['channelType'];
    }

    public function denormalize($data, $type, $format = null, array $context = [])
    {
        $brand = new Brand();
        $brand->setAkeneoCode($data);

        return $brand;
    }
}
