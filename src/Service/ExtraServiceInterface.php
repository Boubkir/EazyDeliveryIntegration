<?php declare(strict_types=1);


// ExtraServiceInterface.php
namespace EazyDeliveryIntegration\Service;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ExtraServiceInterface
{
public function loadExtras(string $categoryName, SalesChannelContext $context, string $defaultSizeId): array;
public function loadExtrasForSize(string $categoryName, string $sizeOptionId, SalesChannelContext $context): array;
}
