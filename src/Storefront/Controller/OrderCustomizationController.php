<?php declare(strict_types=1);

namespace EazyDeliveryIntegration\Storefront\Controller;

use EazyDeliveryIntegration\Service\OrderCustomizationDataService;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'XmlHttpRequest' => true])]
class OrderCustomizationController extends StorefrontController
{
    private OrderCustomizationDataService $customizationDataService;
    private LoggerInterface $logger;

    public function __construct(OrderCustomizationDataService $customizationDataService, LoggerInterface $logger)
    {
        $this->customizationDataService = $customizationDataService;
        $this->logger = $logger;
    }

    #[Route(
        path: '/widgets/order-customization/{productId}',
        name: 'frontend.order.customization',
        methods: ['GET']
    )]
    public function showCustomizationOptions(string $productId, SalesChannelContext $context): Response
    {
        $this->logger->info('[OrderCustomization] Showing customization options for product ID: ' . $productId);

        $sizes = $this->customizationDataService->loadVariants($productId, $context);

        if (empty($sizes)) {
            throw $this->createNotFoundException('No variants found for this product.');
        }

        // Extrahiere die optionId aus dem ersten Size-Variant
        $optionId = $this->customizationDataService->getOptionIdByVariantId($sizes[0]['id'], $context);

        $toppings = $this->customizationDataService->loadExtrasForSize('Extra Zutaten', $optionId, $context);
        $drinks = $this->customizationDataService->loadDrinks('Getränke', $context);

        return $this->renderStorefront('@EazyDeliveryTheme/storefront/page/order-customization.html.twig', [
            'productId' => $productId,
            'sizes' => $sizes,
            'toppings' => $toppings,
            'drinks' => $drinks,
            'basePrice' => $sizes[0]['price'],
        ]);
    }


    #[Route('/widgets/order-customization/calculate-price', name: 'frontend.order.customization.calculate_price', methods: ['POST'])]
    public function calculatePrice(Request $request, SalesChannelContext $context): JsonResponse
    {
        $this->logger->info('[OrderCustomization] Calculating price from request data.');

        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $sizeId = $data['sizeId'] ?? null;
        $selectedToppings = $data['toppings'] ?? [];
        $drinkId = $data['drink'] ?? null;

        if (!$sizeId) {
            return new JsonResponse(['error' => 'Pizza size ID is required'], Response::HTTP_BAD_REQUEST);
        }

        // Preis der Basisgröße
        $basePrice = $this->customizationDataService->getPriceById($sizeId, $context);

        // Preise der Toppings summieren
        $toppingPrice = array_reduce($selectedToppings, function ($carry, $toppingId) use ($sizeId, $context) {
            return $carry + $this->customizationDataService->getVariantPriceById($toppingId, $sizeId, $context);
        }, 0.0);

        // Preis des Getränks
        $drinkPrice = $drinkId ? $this->customizationDataService->getPriceById($drinkId, $context) : 0.0;

        // Gesamtpreis berechnen
        $totalPrice = $basePrice + $toppingPrice + $drinkPrice;

        $this->logger->info('[OrderCustomization] Total price calculated: ' . $totalPrice);

        return new JsonResponse([
            'totalPrice' => $totalPrice,
        ]);
    }


    #[Route('/widgets/order-customization/extras/{sizeId}', name: 'frontend.order.customization.load_extras', methods: ['GET'])]
    public function loadExtras(string $sizeId, SalesChannelContext $context): JsonResponse
    {
        $extras = $this->customizationDataService->loadExtrasForSize('Extra Zutaten', $sizeId, $context);

        return new JsonResponse(['extras' => $extras]);
    }



}
