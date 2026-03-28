import './component/act-free-shipping-bar-info';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('act-free-shipping-bar', {
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    }
});
