<?php declare(strict_types=1);

namespace Act\FreeShippingBar\Subscriber;

use Act\FreeShippingBar\Service\FreeShippingCalculator;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FreeShippingBarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly FreeShippingCalculator $calculator,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutCartPageLoadedEvent::class => 'onCartPageLoaded',
            OffcanvasCartPageLoadedEvent::class => 'onCartPageLoaded',
            CheckoutConfirmPageLoadedEvent::class => 'onCartPageLoaded',
            CheckoutRegisterPageLoadedEvent::class => 'onCartPageLoaded',
        ];
    }

    public function onCartPageLoaded(
        CheckoutCartPageLoadedEvent|OffcanvasCartPageLoadedEvent|CheckoutConfirmPageLoadedEvent|CheckoutRegisterPageLoadedEvent $event
    ): void {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();

        if (!$this->systemConfigService->getBool('ActFreeShippingBar.config.isActive', $salesChannelId)) {
            return;
        }

        $data = $this->calculator->calculate(
            $event->getPage()->getCart(),
            $event->getSalesChannelContext()
        );

        $data['showProgressBar'] = $this->systemConfigService->getBool(
            'ActFreeShippingBar.config.showProgressBar',
            $salesChannelId
        );
        $data['showWhenReached'] = $this->systemConfigService->getBool(
            'ActFreeShippingBar.config.showWhenReached',
            $salesChannelId
        );

        $event->getPage()->addExtension('actFreeShippingBar', new ArrayStruct($data));
    }
}
