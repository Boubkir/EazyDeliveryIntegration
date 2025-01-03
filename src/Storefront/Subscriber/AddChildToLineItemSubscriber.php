<?php declare(strict_types=1);

namespace EazyDeliveryIntegration\Storefront\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddChildToLineItemSubscriber implements EventSubscriberInterface
{
    private CartService $cartService;
    private LoggerInterface $logger;

    public function __construct(CartService $cartService, LoggerInterface $logger)
    {
        $this->cartService = $cartService;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterLineItemAddedEvent::class => 'onLineItemAdded',
        ];
    }

    public function onLineItemAdded(AfterLineItemAddedEvent $event): void
    {
        $lineItems = $event->getLineItems(); // Alle hinzugefügten Line-Items
        $salesChannelContext = $event->getSalesChannelContext();

        foreach ($lineItems as $lineItem) {
            // Logge die Parent-Line-Item-Daten
            $this->logger->debug('[AddChildToLineItemSubscriber] Parent LineItem ID: ' . $lineItem->getId());
            $this->logger->debug('[AddChildToLineItemSubscriber] Parent LineItem Referenced ID: ' . $lineItem->getReferencedId());

            if ($lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
                foreach ($lineItem->getChildren() as $child) {
                    // Logge die vorhandenen Kinder
                    $this->logger->debug('[AddChildToLineItemSubscriber] Existing Child LineItem Referenced ID: ' . $child->getReferencedId());

                    // Beispiel für ein neues Child-Line-Item erstellen
                    $childLineItem = new LineItem(
                        $child->getReferencedId(),
                        LineItem::PRODUCT_LINE_ITEM_TYPE,
                        $child->getReferencedId()
                    );

                    $childLineItem->setQuantity(1);
                    $childLineItem->setRemovable(true);
                    $childLineItem->setStackable(true);

                    // Logge die neuen Kinder-Daten
                    $this->logger->debug('[AddChildToLineItemSubscriber] Created Child LineItem ID: ' . $childLineItem->getId());
                    $this->logger->debug('[AddChildToLineItemSubscriber] Child LineItem Referenced ID: ' . $childLineItem->getReferencedId());

                    // Füge das neue Kind hinzu
                    $lineItem->addChild($childLineItem);
                    $this->logger->debug('[AddChildToLineItemSubscriber] Added New Child LineItem to Parent ID: ' . $lineItem->getId());
                }
            }
        }

        // Aktualisiere den Warenkorb
        $this->cartService->recalculate($event->getCart(), $salesChannelContext);
    }

}
