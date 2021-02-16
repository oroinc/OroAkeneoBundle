<?php

namespace Oro\Bundle\AkeneoBundle\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Oro\Bundle\AttachmentBundle\Entity\File;

class LoadClassMetadataListener
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        $classMetadata = $eventArgs->getClassMetadata();

        if (is_a($classMetadata->getName(), File::class, true)) {
            $classMetadata->table['indexes']['oro_akeneo_file_parent_index'] = [
                'columns' => ['parent_entity_class', 'parent_entity_id'],
            ];

            $classMetadata->fieldMappings[$classMetadata->getFieldForColumn('parent_entity_class')]['length'] = 255;
        }
    }
}
