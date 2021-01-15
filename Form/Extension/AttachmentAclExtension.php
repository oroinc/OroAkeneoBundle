<?php

namespace Oro\Bundle\AkeneoBundle\Form\Extension;

use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Form\Type\ConfigType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Allow `acl_protected` changes for Akeneo fields
 */
class AttachmentAclExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $configModel = $options['config_model'];
        if ($configModel instanceof FieldConfigModel) {
            $data = $builder->getData();
            if (($data['importexport']['source'] ?? null) === 'akeneo') {
                $builder->get('attachment')->get('acl_protected')->setDisabled(false);
            }
        }
    }

    public static function getExtendedTypes(): iterable
    {
        return [ConfigType::class];
    }
}
