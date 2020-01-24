<?php

namespace Oro\Bundle\AkeneoBundle\ImportExport\DataConverter;

use Oro\Bundle\AkeneoBundle\Tools\Generator;
use Oro\Bundle\EntityConfigBundle\Generator\SlugGenerator;
use Oro\Bundle\ImportExportBundle\Context\ContextAwareInterface;
use Oro\Bundle\LocaleBundle\ImportExport\DataConverter\LocalizedFallbackValueAwareDataConverter;

class CategoryDataConverter extends LocalizedFallbackValueAwareDataConverter implements ContextAwareInterface
{
    use AkeneoIntegrationTrait;

    /**
     * @var SlugGenerator
     */
    protected $slugGenerator;

    public function setSlugGenerator(SlugGenerator $slugGenerator)
    {
        $this->slugGenerator = $slugGenerator;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToImportFormat(array $importedRecord, $skipNullValues = true)
    {
        $this->setTitles($importedRecord);
        $this->setSlugs($importedRecord);
        $this->setRootCategory($importedRecord);

        $importedRecord['channel:id'] = $this->getContext()->getOption('channel');

        return parent::convertToImportFormat($importedRecord, $skipNullValues);
    }

    /**
     * Set titles with locales mapping from settings.
     */
    private function setTitles(array &$importedRecord)
    {
        $defaultLocalization = $this->getDefaultLocalization();
        $defaultLocale = $this->getTransport()->getMappedAkeneoLocale($defaultLocalization->getLanguageCode());

        $importedRecord['titles'] = [
            'default' => [
                'fallback' => null,
                'string' => $importedRecord['labels'][$defaultLocale] ??
                    Generator::generateLabel($importedRecord['code']),
            ],
        ];

        foreach ($this->getTransport()->getAkeneoLocales() as $akeneoLocale) {
            foreach ($this->getLocalizations($akeneoLocale->getLocale()) as $localization) {
                if (!$localization || $defaultLocalization->getLanguageCode() === $localization->getLanguageCode()) {
                    continue;
                }

                $value = $importedRecord['labels'][$akeneoLocale->getCode()] ?? null;
                $importedRecord['titles'][$localization->getName()] = ['fallback' => null, 'string' => $value];
            }
        }
    }

    private function setSlugs(array &$importedRecord)
    {
        $importedRecord['slugPrototypes'] = $importedRecord['titles'] ?? [];
        foreach ($importedRecord['slugPrototypes'] as &$slugPrototype) {
            $slugPrototype['string'] = $this->slugGenerator->slugify($slugPrototype['string']);
        }
    }

    /**
     * Check root category setting from akeneo settings.
     */
    private function setRootCategory(array &$importedRecord)
    {
        if ($importedRecord['parent']) {
            $importedRecord['parentCategory:channel:id'] = $this->getContext()->getOption('channel');
            $importedRecord['parentCategory:akeneo_code'] = $importedRecord['parent'];

            return;
        }

        if (!$this->getTransport()->getRootCategory()) {
            return;
        }

        $importedRecord['parentCategory:id'] = $this->getTransport()->getRootCategory()->getId();
    }

    /**
     * {@inheritdoc}
     */
    protected function getHeaderConversionRules()
    {
        return [
            'titles' => 'titles',
            'code' => 'akeneo_code',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getBackendHeader()
    {
        throw new \Exception('Normalization is not implemented!');
    }
}
