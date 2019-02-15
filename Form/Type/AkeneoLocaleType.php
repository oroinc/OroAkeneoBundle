<?php

namespace Oro\Bundle\AkeneoBundle\Form\Type;

use Oro\Bundle\AkeneoBundle\Entity\AkeneoLocale;
use Oro\Bundle\LocaleBundle\Form\Type\LanguageType;
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
     * @param FormBuilderInterface $builder
     * @param array $options
     *
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
                LanguageType::class,
                [
                    'required' => true,
                    'label' => false,
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]
            );
        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'onPreSetData']);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onPreSubmit']);
    }

    /**
     * @param FormEvent $event
     */
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

    /**
     * @param FormEvent $event
     */
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
     * @param OptionsResolver $resolver
     *
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
