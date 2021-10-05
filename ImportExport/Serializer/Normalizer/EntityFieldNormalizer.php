<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Serializer\Normalizer;

use Oro\Bundle\AkeneoBundle\Integration\AkeneoChannel;
use Oro\Bundle\EntityConfigBundle\ImportExport\Serializer\EntityFieldNormalizer as BaseEntityFieldNormalizer;

class EntityFieldNormalizer extends BaseEntityFieldNormalizer
{
    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return is_array($data)
            && is_a($type, 'Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel', true)
            && true === isset($context['channelType'])
            && AkeneoChannel::TYPE === $context['channelType'];
    }

    /**
     * @return array
     */
    protected function getEnumConfig()
    {
        return [
            'id' => [
                self::CONFIG_TYPE => self::TYPE_STRING,
            ],
            'label' => [
                self::CONFIG_TYPE => self::TYPE_STRING,
            ],
            'is_default' => [
                self::CONFIG_TYPE => self::TYPE_BOOLEAN,
            ],
        ];
    }
}
