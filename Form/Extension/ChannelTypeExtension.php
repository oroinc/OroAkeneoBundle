<?php

namespace Oro\Bundle\AkeneoBundle\Form\Extension;

use Oro\Bundle\AkeneoBundle\Integration\AkeneoChannel;
use Oro\Bundle\FormBundle\Utils\FormUtils;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Form\Type\ChannelType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class ChannelTypeExtension extends AbstractTypeExtension
{
    /**
     * @var array
     */
    protected $mandatoryConnectors = [];

    /**
     * @var array
     */
    protected $connectorsOrder = [
        'category',
        'attribute',
        'attribute_family',
        'product',
    ];

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return ChannelType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) {
                if (
                    false === $event->getData() instanceof Channel ||
                    AkeneoChannel::TYPE !== $event->getData()->getType()
                ) {
                    return;
                }

                $form = $event->getForm();

                FormUtils::replaceField(
                    $form,
                    'connectors',
                    [
                        'choice_attr' => function ($choiceValue) {
                            if (false === in_array($choiceValue, $this->mandatoryConnectors)) {
                                return [];
                            }

                            return ['checked' => true, 'disabled' => true];
                        },
                    ]
                );
            }
        );

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) {
                $data = $event->getData();

                if (false === isset($data['transportType']) || AkeneoChannel::TYPE !== $data['transportType']) {
                    return;
                }

                if (false === isset($data['connectors'])) {
                    $data['connectors'] = [];
                }

                $data['connectors'] = array_merge($data['connectors'], $this->mandatoryConnectors);
                $event->setData($data);
            }
        );

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var Channel $data */
                $data = $event->getData();
                $connectors = $data->getConnectors();
                usort(
                    $connectors,
                    function (string $a, string $b) {
                        $a = array_search($a, $this->connectorsOrder);
                        $b = array_search($b, $this->connectorsOrder);

                        if ($a === $b) {
                            return 0;
                        }

                        return ($a < $b) ? -1 : 1;
                    }
                );
                $data->setConnectors($connectors);
            }
        );
    }
}
