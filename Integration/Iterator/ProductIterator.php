<?php

namespace Oro\Bundle\AkeneoBundle\Integration\Iterator;

use Akeneo\Pim\ApiClient\Exception\NotFoundHttpException;
use Akeneo\Pim\ApiClient\Pagination\ResourceCursorInterface;
use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClientInterface;
use Gaufrette\Filesystem;
use Psr\Log\LoggerInterface;

class ProductIterator extends AbstractIterator
{
    /**
     * @var bool
     */
    private $attributesInitialized = false;

    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var bool
     */
    private $familyVariantsInitialized = false;

    /**
     * @var array
     */
    private $familyVariants = [];

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string|null
     */
    private $alternativeAttribute;

    /**
     * AttributeIterator constructor.
     *
     * @param ResourceCursorInterface $resourceCursor
     * @param AkeneoPimEnterpriseClientInterface $client
     * @param LoggerInterface $logger
     * @param Filesystem $filesystem
     * @param string|null $alternativeAttribute
     */
    public function __construct(
        ResourceCursorInterface $resourceCursor,
        AkeneoPimEnterpriseClientInterface $client,
        LoggerInterface $logger,
        Filesystem $filesystem,
        ?string $alternativeAttribute = null
    ) {
        parent::__construct($resourceCursor, $client, $logger);
        $this->filesystem = $filesystem;
        $this->alternativeAttribute = $alternativeAttribute;
    }

    /**
     * {@inheritdoc}
     */
    public function doCurrent()
    {
        $product = $this->resourceCursor->current();

        $this->setAlternativeIdentifier($product);
        $this->setValueAttributeTypes($product);
        $this->setFamilyVariant($product);

        return $product;
    }

    /**
     * Switch the product code (intern identifier in Akeneo) value
     * with an other attribute to allow to map it differently
     *
     * @param array $product
     */
    protected function setAlternativeIdentifier(array &$product)
    {
        if (null === $this->alternativeAttribute) return;

        list($altAttribute, $identifier) = explode(':', $this->alternativeAttribute);

        if (!empty($altAttribute)
            && isset($product['values'][$altAttribute])
            && isset($product['identifier'])
        ) {

            if (isset($product['values'][$altAttribute][0]['data'])) {
                if (null !== $identifier) {
                    $product[$identifier] = $product['identifier'];
                }

                $product['identifier'] = $product['values'][$altAttribute][0]['data'];
            }
        }
    }

    /**
     * Set attribute types for product values.
     *
     * @param array $product
     */
    protected function setValueAttributeTypes(array &$product)
    {
        if (false === $this->attributesInitialized) {
            foreach ($this->client->getAttributeApi()->all(self::PAGE_SIZE) as $attribute) {
                $this->attributes[$attribute['code']] = $attribute;
            }
            $this->attributesInitialized = true;
        }

        foreach ($product['values'] as $code => $values) {
            if (isset($this->attributes[$code])) {
                foreach ($values as $key => $value) {
                    $product['values'][$code][$key]['type'] = $this->attributes[$code]['type'];
                    $this->processImageType($product['values'][$code][$key]);
                    $this->processFileType($product['values'][$code][$key]);
                }
            }
        }
    }

    /**
     * Set family variant from API.
     *
     * @param array $model
     */
    private function setFamilyVariant(array &$model)
    {
        if (false === $this->familyVariantsInitialized) {
            foreach ($this->client->getFamilyApi()->all(self::PAGE_SIZE) as $family) {
                foreach ($this->client->getFamilyVariantApi()->all($family['code'], self::PAGE_SIZE) as $variant) {
                    $variant['family'] = $family['code'];
                    $this->familyVariants[$variant['code']] = $variant;
                }
            }
            $this->familyVariantsInitialized = true;
        }

        if (empty($model['family_variant'])) {
            return;
        }

        if (isset($this->familyVariants[$model['family_variant']])) {
            $model['family_variant'] = $this->familyVariants[$model['family_variant']];
        }
    }

    /**
     * Download images if necessary.
     *
     * @param array $value
     */
    protected function processImageType($value)
    {
        if ('pim_catalog_image' !== $value['type'] || empty($value['data'])) {
            return;
        }

        $path = $this->getFilePath('product_images', $value['data']);

        if ($this->filesystem->has($path)) {
            return;
        }

        $content = $this->client->getProductMediaFileApi()->download($value['data'])->getContents();
        $this->filesystem->write($path, $content);
    }

    /**
     * @param string $type
     * @param string $code
     *
     * @return string
     */
    protected function getFilePath(string $type, string $code): string
    {
        return sprintf('%s/%s', $type, basename($code));
    }

    /**
     * Download images if necessary.
     *
     * @param array $value
     */
    protected function processFileType($value)
    {
        if ('pim_catalog_file' !== $value['type'] || empty($value['data'])) {
            return;
        }

        $path = $this->getFilePath('attachments', $value['data']);

        if ($this->filesystem->has($path)) {
            return;
        }

        $content = $this->client->getProductMediaFileApi()->download($value['data'])->getContents();
        $this->filesystem->write($path, $content);
    }
}
