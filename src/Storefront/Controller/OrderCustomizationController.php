<?php declare(strict_types=1);

namespace EazyDeliveryIntegration\Storefront\Controller;

use EazyDeliveryIntegration\Service\DrinkServiceInterface;
use EazyDeliveryIntegration\Service\ExtraServiceInterface;
use EazyDeliveryIntegration\Service\PriceServiceInterface;
use EazyDeliveryIntegration\Service\VariantServiceInterface;
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
    public function __construct(
        private readonly VariantServiceInterface $variantService,
        private readonly ExtraServiceInterface $extraService,
        private readonly DrinkServiceInterface $drinkService,
        private readonly PriceServiceInterface $priceService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(
        path: '/widgets/order-customization/{productId}',
        name: 'frontend.order.customization',
        methods: ['GET']
    )]
    public function showCustomizationOptions(string $productId, SalesChannelContext $context): Response
    {
        $this->logger->info('[OrderCustomization] Showing customization options for product ID: ' . $productId);

        // Lade alle Varianten (z. B. verschiedene Pizza-Größen)
        $sizes = $this->variantService->loadVariants($productId, $context);
        if (empty($sizes)) {
            throw $this->createNotFoundException('No variants found for this product.');
        }

        // Extrahiere die optionId der ersten Variante (für das Laden der Toppings pro Größe)
        $optionId = $this->variantService->getOptionIdByVariantId($sizes[0]['id'], $context);

        // Lade die verfügbaren Toppings (z. B. "Extra Zutaten") für diese Option
        $toppings = $this->extraService->loadExtrasForSize('Extra Zutaten', $optionId, $context);

        // Lade die verfügbaren Getränke (z. B. aus Kategorie "Getränke")
        $drinks = $this->drinkService->loadDrinks('Getränke', $context);

        return $this->renderStorefront('@EazyDeliveryTheme/storefront/page/order-customization.html.twig', [
            'productId' => $productId,
            'sizes'     => $sizes,
            'toppings'  => $toppings,
            'drinks'    => $drinks,
            'basePrice' => $sizes[0]['price'],
        ]);
    }

    #[Route(
        path: '/widgets/order-customization/calculate-price',
        name: 'frontend.order.customization.calculate_price',
        methods: ['POST']
    )]
    public function calculatePrice(Request $request, SalesChannelContext $context): JsonResponse
    {
        $this->logger->info('[OrderCustomization] Calculating price from request data.');

        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $sizeId          = $data['sizeId']     ?? null;
        $selectedToppings = $data['toppings']  ?? [];
        $drinkId         = $data['drink']      ?? null;

        if (!$sizeId) {
            return new JsonResponse(['error' => 'Pizza size ID is required'], Response::HTTP_BAD_REQUEST);
        }

        // Basispreis der gewählten Größe
        $basePrice = $this->priceService->getPriceById($sizeId, $context);

        // Preise aller ausgewählten Toppings aufsummieren
        $toppingPrice = array_reduce($selectedToppings, function ($carry, $toppingId) use ($sizeId, $context) {
            return $carry + $this->priceService->getVariantPriceById($toppingId, $sizeId, $context);
        }, 0.0);

        // Preis eines ggf. ausgewählten Getränks
        $drinkPrice = $drinkId
            ? $this->priceService->getPriceById($drinkId, $context)
            : 0.0;

        // Gesamtpreis
        $totalPrice = $basePrice + $toppingPrice + $drinkPrice;

        $this->logger->info('[OrderCustomization] Total price calculated: ' . $totalPrice);

        return new JsonResponse([
            'totalPrice' => $totalPrice,
        ]);
    }

    #[Route(
        path: '/widgets/order-customization/extras/{sizeId}',
        name: 'frontend.order.customization.load_extras',
        methods: ['GET']
    )]
    public function loadExtras(string $sizeId, SalesChannelContext $context): JsonResponse
    {
        $extras = $this->extraService->loadExtrasForSize('Extra Zutaten', $sizeId, $context);
        return new JsonResponse(['extras' => $extras]);
    }
}
