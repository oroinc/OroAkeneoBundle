<?php

namespace Oro\Bundle\AkeneoBundle\EventListener;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Event\OnClearEventArgs;
use Oro\Bundle\SecurityBundle\Authentication\Token\OrganizationAwareTokenInterface;
use Oro\Bundle\SecurityBundle\EventListener\RefreshContextListener as BaseListener;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RefreshContextListener extends BaseListener
{
    protected function checkUser(OnClearEventArgs $event, TokenInterface $token): void
    {
        $user = $token->getUser();
        if (!is_object($user)) {
            return;
        }
        $userClass = ClassUtils::getClass($user);
        if ($event->getEntityClass() && $event->getEntityClass() !== $userClass) {
            return;
        }
        $em = $this->doctrine->getManagerForClass($userClass);
        $user = $this->refreshEntity($user, $userClass, $em);
        if ($user) {
            $token->setUser($user);
        } else {
            $this->securityTokenStorage->setToken(null);
        }
    }

    protected function checkOrganization(OnClearEventArgs $event, OrganizationAwareTokenInterface $token): void
    {
        $organization = $token->getOrganization();
        if (!is_object($organization)) {
            return;
        }
        $organizationClass = ClassUtils::getClass($organization);
        if ($event->getEntityClass() && $event->getEntityClass() !== $organizationClass) {
            return;
        }
        $em = $this->doctrine->getManagerForClass($organizationClass);
        $organization = $this->refreshEntity($organization, $organizationClass, $em);
        if (!$organization) {
            return;
        }
        $token->setOrganization($organization);
    }
}
