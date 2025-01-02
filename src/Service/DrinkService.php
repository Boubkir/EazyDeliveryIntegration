<?php declare(strict_types=1);

namespace EazyDeliveryIntegration\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DrinkService implements DrinkServiceInterface
{
    public function __construct(
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function loadDrinks(string $categoryName, SalesChannelContext $context): array
    {
        $this->logger->info('[DrinkService] Loading drinks for category: ' . $categoryName);

        $criteria = new Criteria();
        // Beispiel: Falls du die Kategorie in der "categories.name" durchsuchen möchtest:
        $criteria->addFilter(new ContainsFilter('categories.name', $categoryName));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('calculatedPrices');
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $drinks = $this->salesChannelProductRepository
            ->search($criteria, $context)
            ->getEntities();

        $drinkList = [];
        foreach ($drinks as $drink) {
            if ($drink instanceof SalesChannelProductEntity) {
                $calculatedPrice = $drink->getCalculatedPrice();
                $drinkList[] = [
                    'id'    => $drink->getId(),
                    'name'  => $drink->getTranslated()['name'],
                    'price' => $calculatedPrice ? $calculatedPrice->getUnitPrice() : 0.0,
                ];
            }
        }

        $this->logger->info('[DrinkService] Loaded drinks: ' . json_encode($drinkList));

        return $drinkList;
    }
}
