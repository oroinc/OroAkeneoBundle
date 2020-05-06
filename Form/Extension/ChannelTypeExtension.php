<?php

namespace Oro\Bundle\AkeneoBundle\Form\Extension;

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
