<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Serializer\Normalizer;

use Oro\Bundle\AkeneoBundle\Integration\AkeneoChannel;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\ImportExportBundle\Serializer\Normalizer\DenormalizerInterface;
use Oro\Bundle\ProductBundle\Entity\ProductImage;

class FileNormalizer implements DenormalizerInterface
{
    /** @var DenormalizerInterface */
    private $fileNormalizer;

    public function __construct(DenormalizerInterface $fileNormalizer)
    {
        $this->fileNormalizer = $fileNormalizer;
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = [])
    {
        return is_a($type, File::class, true)
            && AkeneoChannel::TYPE === ($context['channelType'] ?? null)
            && ProductImage::class !== ($context['entityName'] ?? null);
    }

    public function denormalize($data, $type, $format = null, array $context = [])
    {
        $file = $this->fileNormalizer->denormalize($data, $type, $format, $context);
        if ($file instanceof File && !$file->getOriginalFilename()) {
            $file->setOriginalFilename(basename($data['uri']));
        }

        return $file;
    }
}
