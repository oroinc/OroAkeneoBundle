<?php

namespace Oro\Bundle\DotmailerBundle\Migrations\Data\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\ProductBundle\Entity\ProductImage;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class DisableDAM extends AbstractFixture implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function load(ObjectManager $manager)
    {
        /** @var ConfigManager $configManager */
        $configManager = $this->container->get('oro_entity_config.config_manager');
        $attachmentProvider = $configManager->getProvider('attachment');
        $attachmentConfig = $attachmentProvider->getConfig(ProductImage::class, 'image');
        $attachmentConfig->set('use_dam', false);

        $configManager->persist($attachmentConfig);
        $configManager->flush();
    }
}
