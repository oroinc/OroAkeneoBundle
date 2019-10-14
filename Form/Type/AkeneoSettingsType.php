<?php

namespace Oro\Bundle\AkeneoBundle\Form\Type;

use Oro\Bundle\AkeneoBundle\Encoder\Crypter;
use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\AkeneoBundle\Integration\AkeneoTransportInterface;
use Oro\Bundle\AkeneoBundle\Settings\DataProvider\SyncProductsDataProviderInterface;
use Oro\Bundle\AkeneoBundle\Validator\Constraints\JsonConstraint;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\FormBundle\Form\Type\OroEncodedPlaceholderPasswordType;
use Oro\Bundle\PricingBundle\Form\Type\PriceListSelectType;
use Oro\Component\Tree\Entity\Repository\NestedTreeRepository;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

class AkeneoSettingsType extends AbstractType implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    const BLOCK_PREFIX = 'oro_akeneo_settings';

    /**
     * @var array
     */
    public $codes = [];
    /**
     * @var TranslatorInterface
     */
    protected $translator;
    /**
     * @var SyncProductsDataProviderInterface
     */
    private $syncProductsDataProvider;
    /**
     * @var AkeneoTransportInterface
     */
    private $akeneoTransport;

    /**
     * @var Crypter
     */
    private $crypter;

    /**
     * @param TranslatorInterface $translator
     * @param SyncProductsDataProviderInterface $syncProductsDataProvider
     * @param AkeneoTransportInterface $akeneoTransport
     * @param Crypter $crypter
     */
    public function __construct(
        TranslatorInterface $translator,
        SyncProductsDataProviderInterface $syncProductsDataProvider,
        AkeneoTransportInterface $akeneoTransport,
        Crypter $crypter
    ) {
        $this->translator = $translator;
        $this->syncProductsDataProvider = $syncProductsDataProvider;
        $this->akeneoTransport = $akeneoTransport;
        $this->crypter = $crypter;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     *
     * @throws ConstraintDefinitionException
     * @throws InvalidOptionsException
     * @throws MissingOptionsException
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'url',
                TextType::class,
                [
                    'label' => 'oro.akeneo.integration.settings.url.label',
                ]
            )
            ->add(
                'username',
                TextType::class,
                [
                    'label' => 'oro.akeneo.integration.settings.username.label',
                ]
            )
            ->add(
                'password',
                OroEncodedPlaceholderPasswordType::class,
                [
                    'label' => 'oro.akeneo.integration.settings.password.label',
                ]
            )
            ->add(
                'clientId',
                TextType::class,
                [
                    'label' => 'oro.akeneo.integration.settings.clientId.label',
                ]
            )
            ->add(
                'secret',
                TextType::class,
                [
                    'label' => 'oro.akeneo.integration.settings.secret.label',
                ]
            )
            ->add(
                'akeneoActiveChannel',
                ChoiceType::class,
                [
                    'choices_as_values' => true,
                    'required'          => true,
                    'label'             => 'oro.akeneo.integration.settings.akeneo_channels.label',
                    'multiple'          => false,
                    'choices'           => [],
                    'constraints'       => new NotBlank(),
                ]
            )
            ->add(
                'syncProducts',
                ChoiceType::class,
                [
                    'choices'           => $this->syncProductsDataProvider->getSyncProducts(),
                    'choices_as_values' => true,
                    'choice_label'      => function ($action) {
                        return $this->translator->trans(
                            sprintf('oro.akeneo.integration.settings.sync_products.%s', $action)
                        );
                    },
                    'label'             => 'oro.akeneo.integration.settings.sync_products.label',
                    'required'          => true,
                ]
            )
            ->add(
                'akeneoActiveCurrencies',
                ChoiceType::class,
                [
                    'choices_as_values' => true,
                    'required'          => false,
                    'label'             => 'oro.akeneo.integration.settings.akeneo_currencies.label',
                    'multiple'          => true,
                    'choices'           => [],
                ]
            )
            ->add(
                'akeneoLocales',
                CollectionType::class,
                [
                    'entry_type'    => AkeneoLocaleType::class,
                    'allow_add'     => true,
                    'by_reference'  => false,
                    'allow_delete'  => true,
                    'entry_options' => [
                        'parent_data' => $this->codes,
                    ],
                ]
            )
            ->add(
                'akeneoLocalesList',
                ChoiceType::class,
                [
                    'choices_as_values' => true,
                    'required'          => false,
                    'label'             => false,
                    'multiple'          => true,
                    'choices'           => [],
                ]
            )
            ->add(
                'alternativeIdentifier',
                TextType::class,
                [
                    'label'    => 'oro.akeneo.integration.settings.alternative_identifier.label',
                    'required' => false,
                ]
            )
            ->add(
                'akeneoAttributesList',
                TextareaType::class,
                [
                    'required' => false,
                    'label'    => 'oro.akeneo.integration.settings.akeneo_attribute_list.label',
                ]
            )
            ->add(
                'akeneoAttributesImageList',
                TextareaType::class,
                [
                    'required' => false,
                    'label'    => 'oro.akeneo.integration.settings.akeneo_attribute_image_list.label',
                ]
            )
            ->add(
                'rootCategory',
                EntityType::class,
                [
                    'class'         => Category::class,
                    'required'      => false,
                    'placeholder'   => 'oro.akeneo.integration.settings.root_category.placeholder',
                    'query_builder' => function (NestedTreeRepository $er) {
                        return $er->getChildrenQueryBuilder()
                            ->orderBy('node.root')
                            ->addOrderBy('node.left')
                            ->andWhere('node.akeneo_code IS NULL');
                    },
                    'choice_label'  => function (Category $category) {
                        $label = $category->getTitle();
                        while ($parentCategory = $category->getParentCategory()) {
                            $label = sprintf('%s / %s', $parentCategory->getTitle(), $label);
                            $category = $parentCategory;
                        }

                        return $label;
                    },
                ]
            )
            ->add(
                'productFilter',
                TextareaType::class,
                [
                    'required'    => false,
                    'label'       => 'oro.akeneo.integration.settings.akeneo_product_filter.label',
                    'constraints' => [
                        new JsonConstraint(),
                    ],
                ]
            )
            ->add(
                'priceList',
                PriceListSelectType::class,
                [
                    'required' => true,
                    'label'    => 'oro.akeneo.integration.settings.price_list.label',
                ]
            )
            ->add(
                'akeneoMergeImageToParent',
                ChoiceType::class,
                [
                    'required' => false,
                    'label'    => 'oro.akeneo.integration.settings.merge_image.label',
                    'multiple' => false,
                    'choices'  => [
                        'Yes' => true,
                        'No'  => false,
                    ],
                ]
            );

        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'onPreSetData']);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onPreSubmit'], 5000);
    }

    /**
     * @param FormEvent $event
     */
    public function onPreSetData(FormEvent $event)
    {
        $form = $event->getForm();
        /** @var AkeneoSettings $data */
        $data = $event->getData();

        if (!$data instanceof AkeneoSettings) {
            return;
        }

        $form->add(
            'akeneoActiveChannel',
            ChoiceType::class,
            [
                'choices_as_values' => true,
                'required'          => true,
                'label'             => 'oro.akeneo.integration.settings.akeneo_channels.label',
                'multiple'          => false,
                'choices'           => $data->getAkeneoChannels(),
                'placeholder'       => 'oro.akeneo.integration.settings.akeneo_channels.placeholder',
            ]
        );

        $form->add(
            'akeneoActiveCurrencies',
            ChoiceType::class,
            [
                'choices_as_values' => true,
                'required'          => false,
                'label'             => 'oro.akeneo.integration.settings.akeneo_currencies.label',
                'multiple'          => true,
                'choices'           => $data->getAkeneoCurrencies(),
            ]
        );

        $form->add(
            'akeneoLocalesList',
            ChoiceType::class,
            [
                'choices_as_values' => true,
                'required'          => false,
                'label'             => false,
                'multiple'          => true,
                'choices'           => $data->getAkeneoLocalesList(),
            ]
        );

        $this->codes = $data->getAkeneoLocalesList();
        $form->add(
            'akeneoLocales',
            CollectionType::class,
            [
                'entry_type'    => AkeneoLocaleType::class,
                'allow_add'     => true,
                'by_reference'  => false,
                'allow_delete'  => true,
                'entry_options' => [
                    'parent_data' => $this->codes,
                ],
            ]
        );
    }

    /**
     * @param FormEvent $event
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function onPreSubmit(FormEvent $event)
    {
        try {
            $data = $event->getData();
            $akeneoSettings = new AkeneoSettings();
            $oldPassword = $this->crypter->getDecryptData($event->getForm()->get('password')->getData());
            $password = $oldPassword ?: $data['password'];

            $password = $this->crypter->getEncryptData($password);

            $akeneoSettings->setUrl($data['url'])
                ->setClientId($data['clientId'])
                ->setSecret($data['secret'])
                ->setUsername($data['username'])
                ->setPassword($password);

            $this->akeneoTransport->init($akeneoSettings, false);

            $transportData = $event->getData();
            $form = $event->getForm();

            $channels = $this->akeneoTransport->getChannels();
            $currencies = $this->akeneoTransport->getMergedCurrencies();

            $form->add(
                'akeneoActiveChannel',
                ChoiceType::class,
                [
                    'choices_as_values' => true,
                    'required'          => false,
                    'label'             => 'oro.akeneo.integration.settings.akeneo_channels.label',
                    'multiple'          => false,
                    'choices'           => $channels,
                ]
            );

            $transportData['akeneoChannels'] = $channels;

            $form->add(
                'akeneoChannels',
                ChoiceType::class,
                [
                    'choices_as_values' => true,
                    'required'          => false,
                    'label'             => 'oro.akeneo.integration.settings.akeneo_channels.label',
                    'multiple'          => true,
                    'choices'           => $channels,
                ]
            );

            $localesList = $this->akeneoTransport->getLocales();
            $transportData['akeneoLocalesList'] = $localesList;
            $form->add(
                'akeneoLocalesList',
                ChoiceType::class,
                [
                    'choices_as_values' => true,
                    'required'          => false,
                    'label'             => false,
                    'multiple'          => true,
                    'choices'           => $localesList,
                ]
            );

            $this->codes = $localesList;

            $form->add(
                'akeneoLocales',
                CollectionType::class,
                [
                    'entry_type'    => AkeneoLocaleType::class,
                    'allow_add'     => true,
                    'by_reference'  => false,
                    'allow_delete'  => true,
                    'entry_options' => [
                        'parent_data' => $this->codes,
                    ],
                ]
            );

            $form->add(
                'akeneoActiveCurrencies',
                ChoiceType::class,
                [
                    'choices_as_values' => true,
                    'required'          => false,
                    'label'             => 'oro.akeneo.integration.settings.akeneo_currencies.label',
                    'multiple'          => true,
                    'choices'           => $currencies,
                ]
            );

            $transportData['akeneoCurrencies'] = $currencies;

            $form->add(
                'akeneoCurrencies',
                ChoiceType::class,
                [
                    'choices_as_values' => true,
                    'required'          => false,
                    'label'             => 'oro.akeneo.integration.settings.akeneo_currencies.label',
                    'multiple'          => true,
                    'choices'           => $currencies,
                ]
            );

            $transportData['akeneoAttributesList'] = str_replace(' ', '', $transportData['akeneoAttributesList']);
            $transportData['akeneoAttributesImageList'] = str_replace(' ', '', $transportData['akeneoAttributesImageList']);

            $event->setData($transportData);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * @param OptionsResolver $resolver
     *
     * @throws AccessException
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => AkeneoSettings::class,
            ]
        );
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return self::BLOCK_PREFIX;
    }
}
