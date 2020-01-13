<?php

namespace Oro\Bundle\AkeneoBundle\Form\Type;

use Oro\Bundle\AkeneoBundle\Entity\AkeneoLocale;
use Oro\Bundle\FormBundle\Form\Type\OroChoiceType;
use Oro\Bundle\LocaleBundle\Manager\LocalizationManager;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

class AkeneoLocaleType extends AbstractType implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    const BLOCK_PREFIX = 'oro_akeneo_locale';

    /**
     * @var array
     */
    public $codes = [];

    /**
     * @var LocalizationManager
     */
    private $localizationManager;

    public function __construct(LocalizationManager $localizationManager)
    {
        $this->localizationManager = $localizationManager;
    }

    /**
     * @throws ConstraintDefinitionException
     * @throws InvalidOptionsException
     * @throws MissingOptionsException
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->codes = $options['parent_data'];

        $builder
            ->add(
                'code',
                ChoiceType::class,
                [
                    'choices' => $this->codes,
                    'choices_as_values' => true,
                    'label' => false,
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]
            )
            ->add(
                'locale',
                OroChoiceType::class,
                [
                    'required' => true,
                    'label' => false,
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'choices' => $this->getChoices(),
                ]
            );
        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'onPreSetData']);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onPreSubmit']);
    }

    private function getChoices()
    {
        $choices = [];
        $localizations = $this->localizationManager->getLocalizations();
        foreach ($localizations as $localization) {
            $choices[$localization->getName()] = $localization->getLanguageCode();
        }

        return $choices;
    }

    public function onPreSetData(FormEvent $event)
    {
        $form = $event->getForm();

        /** @var AkeneoLocale $data */
        $data = $event->getData();

        if (!$data instanceof AkeneoLocale) {
            return;
        }

        $form
            ->add(
                'code',
                ChoiceType::class,
                [
                    'choices' => $this->codes,
                    'choices_as_values' => true,
                ]
            );
    }

    public function onPreSubmit(FormEvent $event)
    {
        try {
            $transportData = $event->getData();
            $form = $event->getForm();

            $form
                ->add(
                    'code',
                    ChoiceType::class,
                    [
                        'choices' => $this->codes,
                        'choices_as_values' => true,
                    ]
                );

            $event->setData($transportData);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * @throws AccessException
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => AkeneoLocale::class,
                'parent_data' => [],
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
