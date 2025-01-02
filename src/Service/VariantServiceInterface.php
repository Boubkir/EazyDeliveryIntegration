<?php declare(strict_types=1);


namespace EazyDeliveryIntegration\Service;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface VariantServiceInterface
{
public function loadVariants(string $parentId, SalesChannelContext $context): array;

public function getOptionIdByVariantId(string $variantId, SalesChannelContext $context): ?string;
}
