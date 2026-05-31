import './component/act-free-shipping-bar-info';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('act-free-shipping-bar', {
    // eslint-disable-next-line shopware-admin/no-snippet-import
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    }
});
