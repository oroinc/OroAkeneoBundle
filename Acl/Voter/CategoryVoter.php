<?php

namespace Oro\Bundle\AkeneoBundle\Acl\Voter;

use Oro\Bundle\AkeneoBundle\Integration\AkeneoChannel;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\SecurityBundle\Acl\Voter\AbstractEntityVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class CategoryVoter extends AbstractEntityVoter
{
    const ATTRIBUTE_EDIT = 'EDIT';

    /**
     * @var Category
     */
    protected $object;

    /**
     * @var array
     */
    protected $supportedAttributes = [self::ATTRIBUTE_EDIT];

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $object, array $attributes)
    {
        $this->object = $object;

        return parent::vote($token, $object, $attributes);
    }

    /**
     * {@inheritdoc}
     */
    protected function getPermissionForAttribute($class, $identifier, $attribute)
    {
        if (
            is_a($this->object, $this->className, true)
            && null !== $this->object->getChannel()
            && AkeneoChannel::TYPE === $this->object->getChannel()->getType()
            && true === $this->object->getChannel()->getTransport()->isAclVoterEnabled()
        ) {
            return self::ACCESS_DENIED;
        }

        return self::ACCESS_ABSTAIN;
    }
}
