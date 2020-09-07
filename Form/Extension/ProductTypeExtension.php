<?php

namespace Oro\Bundle\AkeneoBundle\Form\Extension;

use Oro\Bundle\EntityBundle\Helper\FieldHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizedFallbackValueCollectionType;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Form\Type\ProductType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * Extends ProductType.
 */
class ProductTypeExtension extends AbstractTypeExtension
{
    /**
     * @var ConfigManager
     */
    private $configManager;

    /**
     * @var FieldHelper
     */
    private $fieldHelper;

    /**
     * ProductTypeExtension constructor.
     */
    public function __construct(ConfigManager $configManager, FieldHelper $fieldHelper)
    {
        $this->configManager = $configManager;
        $this->fieldHelper = $fieldHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return ProductType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $fields = $this->fieldHelper->getRelations(Product::class);
        $importExportProvider = $this->configManager->getProvider('importexport');
        $extendProvider = $this->configManager->getProvider('extend');
        $entityProvider = $this->configManager->getProvider('entity');
        $formProvider = $this->configManager->getProvider('form');

        foreach ($fields as $field) {
            $formConfig = $formProvider->getConfig(Product::class, $field['name']);
            if (!$formConfig->get('is_enabled')) {
                continue;
            }

            $importExportConfig = $importExportProvider->getConfig(Product::class, $field['name']);

            if ('akeneo' !== $importExportConfig->get('source')) {
                continue;
            }

            if (RelationType::MANY_TO_MANY !== $field['type'] && RelationType::TO_MANY !== $field['type']) {
                continue;
            }

            $extendConfig = $extendProvider->getConfig(Product::class, $field['name']);
            if (!$extendConfig->is('state', ExtendScope::STATE_ACTIVE)) {
                continue;
            }

            $fieldLabel = $entityProvider->getConfig(Product::class, $field['name'])->get('label');

            $builder
                ->add(
                    $field['name'],
                    LocalizedFallbackValueCollectionType::class,
                    [
                        'label' => $fieldLabel,
                        'required' => false,
                        'field' => 'string' === $importExportConfig->get('fallback_field') ? 'string' : 'text',
                        'entry_type' => 'string' === $importExportConfig->get('fallback_field')
                            ? TextType::class
                            : TextareaType::class,
                        'constraints' => new Valid(),
                    ]
                );
        }
    }
}
