<?php declare(strict_types=1);

namespace EazyDeliveryIntegration\Service;

namespace EazyDeliveryIntegration\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class VariantService implements VariantServiceInterface
{
    public function __construct(
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function loadVariants(string $parentId, SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $parentId));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('translations');
        $criteria->addAssociation('calculatedPrices');
        $criteria->addAssociation('options');

        $variants = $this->salesChannelProductRepository
            ->search($criteria, $context)
            ->getEntities();

        $sizes = [];
        foreach ($variants as $variant) {
            $sizes[] = [
                'id'       => $variant->getId(),
                'name'     => $variant->getTranslated()['name'],
                'price'    => $variant->getCalculatedPrice()->getUnitPrice(),
                'optionId' => $variant->getOptionIds()[0] ?? null,
            ];
        }

        // Sortieren nach Preis
        usort($sizes, static fn ($a, $b) => $a['price'] <=> $b['price']);

        return $sizes;
    }

    public function getOptionIdByVariantId(string $variantId, SalesChannelContext $context): ?string
    {
        $this->logger->info('[VariantService] Fetching optionId for variant ID: '.$variantId);

        $criteria = new Criteria([$variantId]);
        $criteria->addAssociation('options');

        $variant = $this->salesChannelProductRepository->search($criteria, $context)->first();

        if (!$variant instanceof SalesChannelProductEntity) {
            $this->logger->warning('[VariantService] No variant found for ID: '.$variantId);
            return null;
        }

        $optionIds = $variant->getOptionIds();

        return $optionIds[0] ?? null;
    }
}
