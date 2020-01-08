<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\Serializer\Normalizer;

use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroup;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroupRelation;
use Oro\Bundle\ImportExportBundle\Serializer\Normalizer\ConfigurableEntityNormalizer;

class AttributeRelationNormalizer extends ConfigurableEntityNormalizer
{
    const FIELD_NAME = 'attributeRelations';

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        $result = parent::denormalize($data, $class, $format, $context);

        if ($result instanceof AttributeGroup && array_key_exists(self::FIELD_NAME, $data)) {
            foreach ($data[self::FIELD_NAME] as $item) {
                if (false === isset($item['entityConfigFieldId'])) {
                    continue;
                }

                $relation = new AttributeGroupRelation();
                $relation->setEntityConfigFieldId((int)$item['entityConfigFieldId']);
                $result->addAttributeRelation($relation);
            }
        }

        return $result;
    }
}
