import template from './act-free-shipping-bar-info.html.twig';
import './act-free-shipping-bar-info.scss';

const { Component } = Shopware;

const HOW_TO_ITEM_COUNT = 5;

Component.register('act-free-shipping-bar-info', {
    template,

    computed: {
        howToItems() {
            return Array.from(
                { length: HOW_TO_ITEM_COUNT },
                (_, index) => `actFreeShippingBar.settings.info.howTo.item${index + 1}`,
            );
        },
    },
});
