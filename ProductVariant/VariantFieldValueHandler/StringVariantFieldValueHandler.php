<?php

namespace Oro\Bundle\AkeneoBundle\ProductVariant\VariantFieldValueHandler;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\ProductVariant\Registry\ProductVariantFieldValueHandlerInterface;
use Oro\Component\DoctrineUtils\ORM\QueryBuilderUtil;

class StringVariantFieldValueHandler implements ProductVariantFieldValueHandlerInterface
{
    const TYPE = 'string';

    /** @var DoctrineHelper */
    private $doctrineHelper;

    /** @var string[][] */
    private $cache = [];

    public function __construct(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    public function getPossibleValues($fieldName): array
    {
        if (array_key_exists($fieldName, $this->cache)) {
            return $this->cache[$fieldName];
        }

        QueryBuilderUtil::checkIdentifier($fieldName);

        $qb = $this->doctrineHelper->getEntityRepository(Product::class)->createQueryBuilder('p');
        $field = sprintf('p.%s', $fieldName);
        $values = $qb
            ->select($field)
            ->distinct()
            ->where($qb->expr()->isNotNull($field))
            ->orderBy($qb->expr()->asc($field))
            ->getQuery()
            ->getScalarResult();

        $values = array_column($values, $fieldName);
        $values = array_combine($values, $values);

        $this->cache[$fieldName] = $values;

        return $values;
    }

    public function getScalarValue($value): mixed
    {
        return (string)$value;
    }

    public function getHumanReadableValue($fieldName, $value): mixed
    {
        return (string)$value;
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}
