<?php declare(strict_types=1);

namespace EazyDeliveryIntegration\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ExtraService implements ExtraServiceInterface
{
    public function __construct(
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function loadExtras(string $categoryName, SalesChannelContext $context, string $defaultSizeId): array
    {
        $this->logger->info("[ExtraService] Loading extras for category: {$categoryName}, size: {$defaultSizeId}");

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('categories.name', $categoryName));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('children');

        $extras = $this->salesChannelProductRepository
            ->search($criteria, $context)
            ->getEntities();

        $toppings = [];
        foreach ($extras as $extra) {
            $defaultVariant = null;

            foreach ($extra->getChildren() as $child) {
                if ($child instanceof SalesChannelProductEntity) {
                    if ($child->getId() === $defaultSizeId) {
                        $price = $child->getCalculatedPrice()->getUnitPrice();
                        $defaultVariant = [
                            'id'    => $child->getId(),
                            'name'  => $extra->getTranslated()['name'],
                            'price' => $price,
                        ];
                        break;
                    }
                }
            }

            if ($defaultVariant) {
                $toppings[] = $defaultVariant;
            }
        }

        $this->logger->info('[ExtraService] Loaded extras: ' . json_encode($toppings));

        return $toppings;
    }

    public function loadExtrasForSize(string $categoryName, string $sizeOptionId, SalesChannelContext $context): array
    {
        $this->logger->info("[ExtraService] Loading extras for size option ID: {$sizeOptionId} in category: {$categoryName}");

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('categories.name', $categoryName));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('children');
        $criteria->addAssociation('options');

        $extras = $this->salesChannelProductRepository->search($criteria, $context)->getEntities();

        $toppings = [];
        foreach ($extras as $extra) {
            foreach ($extra->getChildren() as $child) {
                if ($child instanceof SalesChannelProductEntity
                    && in_array($sizeOptionId, $child->getOptionIds(), true)
                ) {
                    $toppings[] = [
                        'id'    => $child->getId(),
                        'name'  => $extra->getTranslated()['name'],
                        'price' => $child->getCalculatedPrice()->getUnitPrice(),
                    ];
                }
            }
        }

        $this->logger->info('[ExtraService] Loaded extras for size option ID: ' . $sizeOptionId . ': ' . json_encode($toppings));

        return $toppings;
    }
}
