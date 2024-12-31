<?php declare(strict_types=1);

namespace EazyDeliveryIntegration\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class OrderCustomizationDataService
{
    private SalesChannelRepository $salesChannelProductRepository;
    private LoggerInterface $logger;

    public function __construct(SalesChannelRepository $salesChannelProductRepository, LoggerInterface $logger)
    {
        $this->salesChannelProductRepository = $salesChannelProductRepository;
        $this->logger = $logger;
    }

    public function loadVariants(string $parentId, SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $parentId));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('translations');
        $criteria->addAssociation('calculatedPrices');
        $criteria->addAssociation('options'); // Lade Optionen

        $variants = $this->salesChannelProductRepository
            ->search($criteria, $context)
            ->getEntities();

        $sizes = [];
        foreach ($variants as $variant) {
            $sizes[] = [
                'id' => $variant->getId(),
                'name' => $variant->getTranslated()['name'],
                'price' => $variant->getCalculatedPrice()->getUnitPrice(),
                'optionId' => $variant->getOptionIds()[0] ?? null, // Nimm die erste Option-ID
            ];
        }

        usort($sizes, static function ($a, $b) {
            return $a['price'] <=> $b['price'];
        });

        return $sizes;
    }

    public function loadExtras(string $categoryName, SalesChannelContext $context, string $defaultSizeId): array
    {
        $this->logger->info('[OrderCustomization] Loading extras for category: ' . $categoryName . ' with size ID: ' . $defaultSizeId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('categories.name', $categoryName));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('children');

        $extras = $this->salesChannelProductRepository
            ->search($criteria, $context)
            ->getEntities();

        $toppings = [];
        foreach ($extras as $extra) {
            $defaultPrice = 0.0;
            $defaultVariant = null;

            foreach ($extra->getChildren() as $child) {
                if ($child instanceof SalesChannelProductEntity) {
                    $sizeId = $child->getId();
                    $price = $child->getCalculatedPrice()->getUnitPrice();

                    if ($sizeId === $defaultSizeId) {
                        $defaultPrice = $price;
                        $defaultVariant = [
                            'id' => $child->getId(),
                            'name' => $extra->getTranslated()['name'],
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

        $this->logger->info('[OrderCustomization] Loaded extras: ' . json_encode($toppings));

        return $toppings;
    }

    public function loadDrinks(string $categoryName, SalesChannelContext $context): array
    {
        $this->logger->info('[OrderCustomization] Loading drinks for category: ' . $categoryName);

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('categories.name', $categoryName));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('calculatedPrices');
        $criteria->addSorting(new FieldSorting('name', 'ASC'));

        $drinks = $this->salesChannelProductRepository
            ->search($criteria, $context)
            ->getEntities();

        $drinkList = [];
        foreach ($drinks as $drink) {
            if ($drink instanceof SalesChannelProductEntity) {
                $calculatedPrice = $drink->getCalculatedPrice();
                $drinkList[] = [
                    'id' => $drink->getId(),
                    'name' => $drink->getTranslated()['name'],
                    'price' => $calculatedPrice ? $calculatedPrice->getUnitPrice() : 0.0,
                ];
            }
        }

        $this->logger->info('[OrderCustomization] Loaded drinks: ' . json_encode($drinkList));

        return $drinkList;
    }

    public function getPriceById(string $id, SalesChannelContext $context): float
    {
        $this->logger->info('[OrderCustomization] Getting price for product ID: ' . $id);

        $criteria = new Criteria([$id]);
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('calculatedPrices');

        $entity = $this->salesChannelProductRepository->search($criteria, $context)->first();

        if ($entity instanceof SalesChannelProductEntity) {
            $calculatedPrice = $entity->getCalculatedPrice();
            if ($calculatedPrice) {
                $this->logger->info('[OrderCustomization] Found price: ' . $calculatedPrice->getUnitPrice());
                return $calculatedPrice->getUnitPrice();
            }
        }

        $this->logger->warning('[OrderCustomization] No price found for product ID: ' . $id);

        return 0.0;
    }

    public function getVariantPriceById(string $productId, string $sizeId, SalesChannelContext $context): float
    {
        $this->logger->info('[OrderCustomization] Getting price for topping product ID: ' . $productId . ' and size product ID: ' . $sizeId);

        // Suche direkt nach der Produkt-ID und prüfe, ob das Produkt aktiv ist
        $criteria = new Criteria([$productId]);
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('calculatedPrices'); // Preisinformationen laden

        $productEntity = $this->salesChannelProductRepository->search($criteria, $context)->first();

        if ($productEntity instanceof SalesChannelProductEntity) {
            $calculatedPrice = $productEntity->getCalculatedPrice();
            if ($calculatedPrice) {
                $this->logger->info('[OrderCustomization] Found price: ' . $calculatedPrice->getUnitPrice());
                return $calculatedPrice->getUnitPrice();
            }
        }

        $this->logger->warning('[OrderCustomization] No price found for topping product ID: ' . $productId);
        return 0.0;
    }


    public function loadExtrasForSize(string $categoryName, string $sizeOptionId, SalesChannelContext $context): array
    {
        $this->logger->info('[OrderCustomization] Loading extras for size option ID: ' . $sizeOptionId . ' in category: ' . $categoryName);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('categories.name', $categoryName));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('children');
        $criteria->addAssociation('options'); // Lade die Optionen

        $extras = $this->salesChannelProductRepository->search($criteria, $context)->getEntities();

        $toppings = [];
        foreach ($extras as $extra) {
            foreach ($extra->getChildren() as $child) {
                if ($child instanceof SalesChannelProductEntity && in_array($sizeOptionId, $child->getOptionIds(), true)) {
                    $toppings[] = [
                        'id' => $child->getId(),
                        'name' => $extra->getTranslated()['name'],
                        'price' => $child->getCalculatedPrice()->getUnitPrice(),
                    ];
                }
            }
        }

        $this->logger->info('[OrderCustomization] Loaded extras for size option ID: ' . $sizeOptionId . ': ' . json_encode($toppings));

        return $toppings;
    }


    public function getOptionIdByVariantId(string $variantId, SalesChannelContext $context): ?string
    {
        $this->logger->info('[OrderCustomization] Fetching optionId for variant ID: ' . $variantId);

        $criteria = new Criteria([$variantId]);
        $criteria->addAssociation('options'); // Lade die Option-IDs

        $variant = $this->salesChannelProductRepository->search($criteria, $context)->first();

        if ($variant instanceof SalesChannelProductEntity) {
            $optionIds = $variant->getOptionIds();
            $this->logger->info('[OrderCustomization] Found optionIds: ' . json_encode($optionIds));

            return $optionIds[0] ?? null; // Nimm die erste Option-ID
        }

        $this->logger->warning('[OrderCustomization] No optionId found for variant ID: ' . $variantId);
        return null;
    }

}
