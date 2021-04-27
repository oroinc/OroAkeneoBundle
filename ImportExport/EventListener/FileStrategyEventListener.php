<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\EventListener;

use Doctrine\Common\Util\ClassUtils;
use Oro\Bundle\AkeneoBundle\ImportExport\Strategy\ExistingEntityAwareInterface;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\AttachmentBundle\Entity\FileItem;
use Oro\Bundle\EntityBundle\Helper\FieldHelper;
use Oro\Bundle\ImportExportBundle\Event\StrategyEvent;

class FileStrategyEventListener
{
    /** @var FieldHelper */
    private $fieldHelper;

    public function __construct(FieldHelper $fieldHelper)
    {
        $this->fieldHelper = $fieldHelper;
    }

    public function onProcessBefore(StrategyEvent $event)
    {
        $strategy = $event->getStrategy();
        if (!$strategy instanceof ExistingEntityAwareInterface) {
            return;
        }

        $entity = $event->getEntity();
        $existingEntity = $strategy->getExistingEntity($entity);
        if (!$existingEntity) {
            return;
        }

        $itemData = (array)($event->getContext()->getValue('itemData') ?? []);
        $fields = $this->fieldHelper->getRelations(ClassUtils::getClass($event->getEntity()));

        foreach ($fields as $field) {
            if (empty($itemData[$field['name']])) {
                continue;
            }

            if ($this->isFileValue($field)) {
                $existingValue = $this->fieldHelper->getObjectValue($existingEntity, $field['name']);
                if ($existingValue) {
                    $itemData[$field['name']]['uuid'] = $existingValue->getUuid();
                }
            }

            if ($this->isFileItemValue($field)) {
                $existingValues = $this->fieldHelper->getObjectValue($existingEntity, $field['name']);
                $uuids = [];
                /** @var FileItem $existingValue */
                foreach ($existingValues as $key => $existingValue) {
                    if (!$existingValue->getFile()) {
                        continue;
                    }

                    $uuids[$existingValue->getFile()->getOriginalFilename()] = $existingValue->getFile()->getUuid();
                }

                foreach ($itemData[$field['name']] as $key => $data) {
                    if (array_key_exists(basename($data['uri']), $uuids)) {
                        $itemData[$field['name']][$key]['file']['uuid'] = $uuids[basename($data['uri'])];
                    }
                }
            }
        }

        $event->getContext()->setValue('itemData', $itemData);
    }

    private function isFileValue(array $field): bool
    {
        return $this->fieldHelper->isRelation($field) && is_a($field['related_entity_name'], File::class, true);
    }

    private function isFileItemValue(array $field): bool
    {
        return $this->fieldHelper->isRelation($field) && is_a($field['related_entity_name'], FileItem::class, true);
    }
}
