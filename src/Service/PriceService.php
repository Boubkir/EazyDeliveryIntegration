<?php declare(strict_types=1);

namespace EazyDeliveryIntegration\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PriceService implements PriceServiceInterface
{
    public function __construct(
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getPriceById(string $id, SalesChannelContext $context): float
    {
        $this->logger->info('[PriceService] Getting price for product ID: ' . $id);

        $criteria = new Criteria([$id]);
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('calculatedPrices');

        $entity = $this->salesChannelProductRepository->search($criteria, $context)->first();

        if ($entity instanceof SalesChannelProductEntity) {
            $calculatedPrice = $entity->getCalculatedPrice();
            if ($calculatedPrice) {
                $this->logger->info('[PriceService] Found price: ' . $calculatedPrice->getUnitPrice());
                return $calculatedPrice->getUnitPrice();
            }
        }

        $this->logger->warning('[PriceService] No price found for product ID: ' . $id);
        return 0.0;
    }

    public function getVariantPriceById(string $productId, string $sizeId, SalesChannelContext $context): float
    {
        $this->logger->info(
            '[PriceService] Getting price for topping product ID: '
            . $productId . ' and size product ID: ' . $sizeId
        );

        // Suche direkt nach der Produkt-ID und prüfe, ob das Produkt aktiv ist
        $criteria = new Criteria([$productId]);
        $criteria->addFilter(new EqualsFilter('active', true));
        // Wichtig: Für die Preisberechnung brauchen wir calculatedPrices
        $criteria->addAssociation('calculatedPrices');

        $productEntity = $this->salesChannelProductRepository->search($criteria, $context)->first();

        if ($productEntity instanceof SalesChannelProductEntity) {
            $calculatedPrice = $productEntity->getCalculatedPrice();
            if ($calculatedPrice) {
                $this->logger->info('[PriceService] Found price: ' . $calculatedPrice->getUnitPrice());
                return $calculatedPrice->getUnitPrice();
            }
        }

        $this->logger->warning('[PriceService] No price found for topping product ID: ' . $productId);
        return 0.0;
    }
}
