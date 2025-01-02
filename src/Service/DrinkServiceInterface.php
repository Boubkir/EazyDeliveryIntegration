<?php declare(strict_types=1);

namespace EazyDeliveryIntegration\Service;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface DrinkServiceInterface
{
    public function loadDrinks(string $categoryName, SalesChannelContext $context): array;
}
