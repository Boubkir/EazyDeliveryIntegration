<?php declare(strict_types=1);

namespace EazyDeliveryIntegration\Service;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface PriceServiceInterface
{
    public function getPriceById(string $id, SalesChannelContext $context): float;

    /**
     * Wenn du verschiedene Größen (Size-IDs) hast und den Preis
     * abhängig vom Topping-Produkt UND der Size-Option berechnest.
     */
    public function getVariantPriceById(string $productId, string $sizeId, SalesChannelContext $context): float;
}
