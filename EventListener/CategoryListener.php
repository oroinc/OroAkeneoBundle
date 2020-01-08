<?php

namespace Oro\Bundle\AkeneoBundle\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\CatalogBundle\EventListener\ORM\CategoryListener as BaseCategoryListener;
use Oro\Bundle\PlatformBundle\EventListener\OptionalListenerInterface;
use Oro\Bundle\PlatformBundle\EventListener\OptionalListenerTrait;

class CategoryListener implements OptionalListenerInterface
{
    use OptionalListenerTrait;

    /** @var BaseCategoryListener */
    private $categoryListener;

    public function __construct(BaseCategoryListener $categoryListener)
    {
        $this->categoryListener = $categoryListener;
    }

    public function postPersist(Category $category)
    {
        if (!$this->enabled) {
            return;
        }

        call_user_func_array([$this->categoryListener, 'postPersist'], func_get_args());
    }

    public function preUpdate(Category $category, PreUpdateEventArgs $args)
    {
        if (!$this->enabled) {
            return;
        }

        call_user_func_array([$this->categoryListener, 'preUpdate'], func_get_args());
    }

    public function onFlush(OnFlushEventArgs $event)
    {
        if (!$this->enabled) {
            return;
        }

        if (!method_exists($this->categoryListener, 'onFlush')) {
            return;
        }

        call_user_func_array([$this->categoryListener, 'onFlush'], func_get_args());
    }
}
