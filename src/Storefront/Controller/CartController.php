<?php declare(strict_types=1);

namespace EazyDeliveryIntegration\Storefront\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'XmlHttpRequest' => true])]
class CartController extends StorefrontController
{
    private CartService $cartService;
    private LineItemFactoryRegistry $lineItemFactoryRegistry;
    private LoggerInterface $logger;

    public function __construct(
        CartService $cartService,
        LineItemFactoryRegistry $lineItemFactoryRegistry,
        LoggerInterface $logger
    ) {
        $this->cartService = $cartService;
        $this->lineItemFactoryRegistry = $lineItemFactoryRegistry;
        $this->logger = $logger;
    }

    #[Route(path: '/widgets/order-customization/add-to-cart', name: 'frontend.order.customization.add_to_cart', methods: ['POST'])]
    public function addToCart(Request $request, SalesChannelContext $context): JsonResponse
    {
        $this->logger->debug('[CartController] Adding items to cart.');

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->logger->debug('[CartController] Received data: ' . json_encode($data));
        } catch (\JsonException $e) {
            $this->logger->error('[CartController] JSON parsing error: ' . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON data'], 400);
        }

        $items = $data['items'] ?? [];
        if (empty($items)) {
            $this->logger->warning('[CartController] No items provided in request.');
            return new JsonResponse(['success' => false, 'message' => 'No items provided'], 400);
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);

        try {
            foreach ($items as $item) {
                $lineItem = $this->createLineItem($item, $context);
                $this->cartService->add($cart, $lineItem, $context);
            }

            $this->cartService->recalculate($cart, $context);

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('[CartController] Error adding items to cart: ' . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function createLineItem(array $itemData, SalesChannelContext $context): LineItem
    {
        $parentLineItem = new LineItem(
            $itemData['id'],
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            $itemData['id'],
            $itemData['quantity'] ?? 1
        );

        $parentLineItem->setRemovable(true);
        $parentLineItem->setStackable(true);

        $totalChildPrice = 0;

        if (!empty($itemData['children'])) {
            foreach ($itemData['children'] as $child) {
                $childLineItem = new LineItem(
                    $child['id'],
                    LineItem::PRODUCT_LINE_ITEM_TYPE,
                    $child['id'],
                    $child['quantity'] ?? 1
                );

                $childLineItem->setRemovable(true);
                $childLineItem->setStackable(true);

                // Preis des Child-Items (z. B. aus einer Datenbank oder API abrufen)
                $childPrice = $child['price'] ?? 0; // Preis aus den übergebenen Daten
                $totalChildPrice += $childPrice * ($child['quantity'] ?? 1);

                $childLineItem->setPrice(new \Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice(
                    $childPrice,
                    $childPrice * ($child['quantity'] ?? 1),
                    new \Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection(),
                    new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection()
                ));

                $parentLineItem->addChild($childLineItem);
            }
        }

        // Parent-Preis berechnen
        $parentPrice = $itemData['price'] ?? 0; // Preis des Parents aus den übergebenen Daten
        $parentTotalPrice = $parentPrice + $totalChildPrice;

        $parentLineItem->setPrice(new \Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice(
            $parentPrice,
            $parentTotalPrice,
            new \Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection(),
            new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection()
        ));

        return $parentLineItem;
    }

}
