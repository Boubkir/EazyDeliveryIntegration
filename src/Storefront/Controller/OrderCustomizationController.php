<?php declare(strict_types=1);

namespace EazyDeliveryIntegration\Storefront\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront'],'XmlHttpRequest' => true])]
class OrderCustomizationController extends StorefrontController
{
    #[Route(
        path: '/widgets/order-customization/{productId}',
        name: 'frontend.order.customization',
        methods: ['GET']
    )]
    public function showCustomizationOptions(string $productId, Request $request, SalesChannelContext $context): Response
    {
        $pizzaSizes = [
            'Small' => '5,00 €',
            'Medium' => '7,50 €',
            'Large' => '10,00 €',
        ];

        $toppings = [
            'Olives' => '1,00 €',
            'Cheese' => '1,50 €',
            'Ham' => '2,00 €',
        ];

        $drinks = [
            'Coke' => '2,50 €',
            'Water' => '1,50 €',
            'Beer' => '3,00 €',
        ];

        return $this->renderStorefront('@EazyDeliveryTheme/storefront/page/order-customization.html.twig', [
            'productId' => $productId,
            'pizzaSizes' => $pizzaSizes,
            'toppings' => $toppings,
            'drinks' => $drinks,
        ]);
    }
}
