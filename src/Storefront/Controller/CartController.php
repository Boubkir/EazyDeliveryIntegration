<?php declare(strict_types=1);

namespace EazyDeliveryIntegration\Storefront\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
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
        $this->logger->debug('[CartController] Current cart fetched.');

        try {
            foreach ($items as $item) {
                $lineItem = $this->createLineItem($item, $context);

                $this->logger->debug('[CartController] Adding line item to cart: ' . json_encode($item));
                $this->cartService->add($cart, $lineItem, $context);
                $this->logger->info('[CartController] Successfully added item with ID: ' . $item['id']);
            }

            $this->cartService->recalculate($cart, $context);
            $this->logger->debug('[CartController] Cart recalculated.');

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('[CartController] Error adding items to cart: ' . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function createLineItem(array $itemData, SalesChannelContext $context): LineItem
    {
        $this->logger->debug('[CartController] Creating LineItem for product ID: ' . ($itemData['id'] ?? 'N/A'));

        $lineItem = $this->lineItemFactoryRegistry->create([
            'type' => 'product',
            'referencedId' => $itemData['id'],
            'quantity' => $itemData['quantity'] ?? 1,
        ], $context);

        $lineItem->setRemovable(true);
        $lineItem->setStackable(true);

        $totalChildPrice = 0.0;

        if (!empty($itemData['children'])) {
            foreach ($itemData['children'] as $child) {
                $this->logger->debug('[CartController] Creating child LineItem for ID: ' . $child['id']);

                $childLineItem = $this->lineItemFactoryRegistry->create([
                    'type' => 'product',
                    'referencedId' => $child['id'],
                    'quantity' => $child['quantity'] ?? 1,
                ], $context);

                $childLineItem->setRemovable(true);
                $childLineItem->setStackable(true);

                $childPrice = $childLineItem->getPrice() ? $childLineItem->getPrice()->getTotalPrice() : 0;
                $totalChildPrice += $childPrice;

                $lineItem->addChild($childLineItem);
                $this->logger->debug('[CartController] Added child LineItem with ID: ' . $childLineItem->getId() . ' to parent LineItem: ' . $lineItem->getId());
            }
        }

        $parentPrice = $lineItem->getPrice() ? $lineItem->getPrice()->getTotalPrice() : 0;
        $lineItem->setPrice(new CalculatedPrice(
            $parentPrice + $totalChildPrice,
            $parentPrice + $totalChildPrice,
            $lineItem->getPrice()->getCalculatedTaxes(),
            $lineItem->getPrice()->getTaxRules()
        ));

        $this->logger->debug('[CartController] Final parent LineItem price: ' . ($parentPrice + $totalChildPrice));

        return $lineItem;
    }
}
