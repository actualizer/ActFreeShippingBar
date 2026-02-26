<?php declare(strict_types=1);

namespace Act\FreeShippingBar\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class FreeShippingCalculator
{
    public function __construct(
        private readonly EntityRepository $shippingMethodRepository,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * @return array{freeShippingPossible: bool, threshold: float, currentValue: float, remaining: float, reached: bool, percentage: float}
     */
    public function calculate(Cart $cart, SalesChannelContext $context): array
    {
        $default = [
            'freeShippingPossible' => false,
            'threshold' => 0.0,
            'currentValue' => 0.0,
            'remaining' => 0.0,
            'reached' => false,
            'percentage' => 0.0,
        ];

        // Hide bar if shipping is already free (all items have free-delivery flag)
        if ($this->isShippingAlreadyFree($cart)) {
            return $default;
        }

        $shippingMethodId = $context->getShippingMethod()->getId();

        $criteria = new Criteria([$shippingMethodId]);
        $criteria->addAssociation('prices');

        $shippingMethod = $this->shippingMethodRepository->search(
            $criteria,
            $context->getContext()
        )->first();

        if (!$shippingMethod || !$shippingMethod->getPrices()) {
            return $default;
        }

        $threshold = $this->findFreeShippingThreshold(
            $shippingMethod->getPrices(),
            $context
        );

        if ($threshold === null) {
            return $default;
        }

        $currentValue = $this->getProductCartValue($cart);

        $remaining = max(0.0, $threshold - $currentValue);
        $reached = $currentValue >= $threshold;
        $percentage = $threshold > 0 ? min(100.0, ($currentValue / $threshold) * 100) : 0.0;

        return [
            'freeShippingPossible' => true,
            'threshold' => $threshold,
            'currentValue' => $currentValue,
            'remaining' => $remaining,
            'reached' => $reached,
            'percentage' => $percentage,
        ];
    }

    /**
     * Mirrors DeliveryCalculator logic: iterate context rule IDs, filter prices,
     * find a CALCULATION_BY_PRICE entry where currencyPrice = 0 (free shipping).
     * The quantityStart of that entry is the threshold.
     */
    private function findFreeShippingThreshold(
        ShippingMethodPriceCollection $allPrices,
        SalesChannelContext $context,
    ): ?float {
        // Try each active rule in order (same priority as DeliveryCalculator)
        foreach ($context->getRuleIds() as $ruleId) {
            $rulePrices = $allPrices->filterByProperty('ruleId', $ruleId);
            $threshold = $this->findThresholdInPrices($rulePrices, $context);
            if ($threshold !== null) {
                return $threshold;
            }
        }

        // Fallback: prices without a rule (default prices)
        $defaultPrices = $allPrices->filterByProperty('ruleId', null);

        return $this->findThresholdInPrices($defaultPrices, $context);
    }

    private function findThresholdInPrices(
        ShippingMethodPriceCollection $prices,
        SalesChannelContext $context,
    ): ?float {
        foreach ($prices as $shippingPrice) {
            if ($shippingPrice->getCalculation() !== DeliveryCalculator::CALCULATION_BY_PRICE) {
                continue;
            }

            $currencyPriceCollection = $shippingPrice->getCurrencyPrice();
            if (!$currencyPriceCollection) {
                continue;
            }

            $resolvedPrice = $this->resolveCurrencyPrice($currencyPriceCollection, $context);
            if ($resolvedPrice === null) {
                continue;
            }

            // Free shipping = price of 0
            if (abs($resolvedPrice) < 0.001) {
                $quantityStart = $shippingPrice->getQuantityStart();
                if ($quantityStart !== null && $quantityStart > 0) {
                    return $quantityStart;
                }
            }
        }

        return null;
    }

    /**
     * Resolve the shipping price for the current currency, respecting gross/net tax state.
     * Mirrors DeliveryCalculator::getCurrencyPrice() + getPriceForTaxState().
     */
    private function resolveCurrencyPrice(PriceCollection $priceCollection, SalesChannelContext $context): ?float
    {
        /** @var Price|null $price */
        $price = $priceCollection->getCurrencyPrice($context->getCurrencyId());
        if (!$price) {
            return null;
        }

        $value = $context->getTaxState() === CartPrice::TAX_STATE_GROSS
            ? $price->getGross()
            : $price->getNet();

        if ($price->getCurrencyId() === Defaults::CURRENCY) {
            $value *= $context->getContext()->getCurrencyFactor();
        }

        return $value;
    }

    /**
     * Check if all deliveries already have zero shipping costs
     * (e.g. because all items have the free-delivery product flag).
     */
    private function isShippingAlreadyFree(Cart $cart): bool
    {
        $deliveries = $cart->getDeliveries();
        if ($deliveries->count() === 0) {
            return false;
        }

        foreach ($deliveries as $delivery) {
            if ($delivery->getShippingCosts()->getTotalPrice() > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate cart value from product line items only, excluding free-delivery items.
     * Mirrors Shopware's getWithoutDeliveryFree() logic from DeliveryCalculator.
     * Also excludes surcharges (logistic-surcharge, cod-fee), promotions, etc.
     */
    private function getProductCartValue(Cart $cart): float
    {
        $total = 0.0;

        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $deliveryInfo = $lineItem->getDeliveryInformation();
            if ($deliveryInfo && $deliveryInfo->getFreeDelivery()) {
                continue;
            }

            $price = $lineItem->getPrice();
            if ($price) {
                $total += $price->getTotalPrice();
            }
        }

        return $total;
    }
}
