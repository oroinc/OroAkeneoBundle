<?php declare(strict_types=1);

namespace Oro\Bundle\AkeneoBundle\ProductUnit;

use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\ProductBundle\Provider\ProductUnitsProvider;

final class ImportFromAkeneoDiscovery implements ProductUnitDiscoveryInterface
{
    /** @var ConfigManager */
    protected $configManager;

    /** @var ProductUnitsProvider */
    protected $productUnitsProvider;

    public function __construct(
        ConfigManager $configManager,
        ProductUnitsProvider $productUnitsProvider
    ) {
        $this->productUnitsProvider = $productUnitsProvider;
        $this->configManager = $configManager;
    }

    public function discover(AkeneoSettings $transport, array $importedRecord): array
    {
        $unit = $this->configManager->get('oro_product.default_unit');
        $precision = $this->configManager->get('oro_product.default_unit_precision');

        $unitAttribute = $transport->getProductUnitAttribute();
        $unitPrecisionAttribute = $transport->getProductUnitPrecisionAttribute();

        $availableUnits = $this->productUnitsProvider->getAvailableProductUnits();

        if (isset($importedRecord['values'][$unitAttribute])) {
            $unitData = reset($importedRecord['values'][$unitAttribute]);
            if (isset($unitData['data']) && in_array($unitData['data'], $availableUnits)) {
                $unit = $unitData['data'];
            }
        }

        if (isset($importedRecord['values'][$unitPrecisionAttribute])) {
            $unitPrecisionData = reset($importedRecord['values'][$unitPrecisionAttribute]);
            if (isset($unitPrecisionData['data'])) {
                $precision = (int)$unitPrecisionData['data'];
            }
        }

        return [
            'unit'      => ['code' => $unit],
            'precision' => $precision,
            'sell'      => true,
        ];
    }
}
