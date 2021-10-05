<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Serializer\Normalizer;

use Oro\Bundle\AkeneoBundle\Integration\AkeneoChannel;
use Oro\Bundle\AttachmentBundle\Entity\FileItem;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;

class FileItemNormalizer implements ContextAwareDenormalizerInterface
{
    /** @var ContextAwareDenormalizerInterface */
    private $fileNormalizer;

    public function __construct(ContextAwareDenormalizerInterface $fileNormalizer)
    {
        $this->fileNormalizer = $fileNormalizer;
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = [])
    {
        return is_a($type, FileItem::class, true)
            && isset($context['channelType'])
            && AkeneoChannel::TYPE === $context['channelType'];
    }

    public function denormalize($data, $type, $format = null, array $context = [])
    {
        $fileItem = new FileItem();
        $file = $this->fileNormalizer->denormalize($data, $type, $format, $context);
        $fileItem->setFile($file);

        return $fileItem;
    }
}
