# ActFreeShippingBar - Shopware Plugin

A Shopware 6 plugin that displays a free shipping progress bar in the offcanvas cart and checkout summary, showing customers how much more they need to spend to qualify for free shipping.

## Features

- Free shipping progress bar in the offcanvas (mini) cart and checkout summary
- Automatically detects the free shipping threshold from the active shipping method's price configuration
- Calculates remaining amount based on product line items only (excludes surcharges, promotions, and free-delivery items)
- Configurable progress bar visibility
- Optional success message when the free shipping threshold is reached
- Respects currency settings (gross/net) and currency factor
- Hides the bar when all items already have the free-delivery flag
- Multi-language support (German & English)
- Admin configuration to enable/disable the feature
- Compatible with Shopware 6.6.10 - 6.7.x

## Requirements

- Shopware 6.6.10 or higher (up to 6.7.x)
- PHP 8.3 or higher

## Installation

1. Download or clone this plugin into your `custom/plugins/` directory
2. Install and activate the plugin via CLI:
   ```bash
   bin/console plugin:refresh
   bin/console plugin:install --activate ActFreeShippingBar
   bin/console cache:clear
   ```

## Configuration

1. Go to Admin Panel → Settings → System → Plugins
2. Find "Actualize: Free Shipping Progress Bar" and click on the three dots
3. Click "Config" to access plugin settings

### Available Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Active | Enable/disable the free shipping bar | Enabled |
| Show progress bar | Display a visual progress bar below the message | Enabled |
| Show message when free shipping reached | Display a success message when the threshold is met | Enabled |

## How it works

1. **Threshold Detection**: The plugin reads the active shipping method's price matrix and finds the price tier where shipping cost is 0 (calculation by cart price). The `quantityStart` of that tier becomes the free shipping threshold.
2. **Cart Value Calculation**: Only product line items count toward the threshold. Items with the free-delivery flag, surcharges, and promotions are excluded.
3. **Progress Display**: A message shows the remaining amount (e.g. "Only €15.00 left for free shipping!") along with an optional progress bar.
4. **Free Shipping Reached**: When the cart value meets or exceeds the threshold, a success message is displayed (configurable).
5. **Already Free**: If all items in the cart have the free-delivery flag (shipping costs are already zero), the bar is hidden entirely.

## Technical Details

### Events Used
- `CheckoutCartPageLoadedEvent` - Cart page
- `OffcanvasCartPageLoadedEvent` - Offcanvas (mini) cart
- `CheckoutConfirmPageLoadedEvent` - Checkout confirmation page

### Template Extensions
- `offcanvas-cart-summary.html.twig` - Adds the bar to the offcanvas cart summary
- `summary-shipping.html.twig` - Adds the bar to the checkout shipping summary

### Services
- `FreeShippingCalculator` - Core service that reads shipping method prices, resolves currency-aware thresholds, and calculates progress data

### Page Extension Data
The plugin adds an `actFreeShippingBar` extension to the page with the following data:

| Key | Type | Description |
|-----|------|-------------|
| `freeShippingPossible` | bool | Whether the shipping method has a free tier |
| `threshold` | float | Cart value needed for free shipping |
| `currentValue` | float | Current product cart value |
| `remaining` | float | Amount still needed |
| `reached` | bool | Whether free shipping has been reached |
| `percentage` | float | Progress percentage (0-100) |
| `showProgressBar` | bool | Config: show the progress bar |
| `showWhenReached` | bool | Config: show message when reached |

## Translations

The plugin includes translations for:
- **German (de-DE)**: "Noch X bis zum kostenlosen Versand!" / "Sie erhalten kostenlosen Versand!"
- **English (en-GB)**: "Only X left for free shipping!" / "You qualify for free shipping!"

## File Structure

```
ActFreeShippingBar/
├── composer.json
├── README.md
└── src/
    ├── ActFreeShippingBar.php
    ├── Service/
    │   └── FreeShippingCalculator.php
    ├── Subscriber/
    │   └── FreeShippingBarSubscriber.php
    └── Resources/
        ├── config/
        │   ├── config.xml
        │   ├── plugin.png
        │   └── services.xml
        ├── snippet/
        │   ├── de_DE/
        │   │   └── storefront.de-DE.json
        │   └── en_GB/
        │       └── storefront.en-GB.json
        ├── views/
        │   └── storefront/
        │       ├── component/
        │       │   └── checkout/
        │       │       └── offcanvas-cart-summary.html.twig
        │       └── page/
        │           └── checkout/
        │               └── summary/
        │                   └── summary-shipping.html.twig
        └── app/
            └── storefront/
                └── src/
                    └── scss/
                        └── base.scss
```

## Development

### Building/Testing
After making changes to templates, translations, or SCSS:
```bash
bin/console cache:clear
bin/console theme:compile
```

### Debugging
The plugin respects Shopware's logging configuration. Check your log files for any shipping calculation errors.

## Known Issues & Behavior Notes

- **Free-delivery items are excluded from the cart value calculation**: Products with the "free delivery" flag enabled in Shopware do not count toward the free shipping threshold. This mirrors Shopware's own `DeliveryCalculator` behavior — these items are also excluded by Shopware when determining shipping costs. As a result, a customer may have a high cart total but still see a remaining amount on the progress bar if most items are flagged as free-delivery. Example: With a €50 threshold, a cart containing a €40 free-delivery item and a €20 normal item shows the bar as reached (€20 ≥ threshold is not met in this case — only the €20 counts), so the bar would show €30 remaining.
- **Only "calculation by price" shipping methods are supported**: The plugin detects the free shipping threshold by looking for a price tier with €0 shipping cost in the shipping method's price matrix (calculation type: "by cart price"). Shipping methods using other calculation types (by weight, by line item count, etc.) or flat-rate methods without a free tier will simply not show the bar — this is expected behavior.
- **Shipping methods without price configuration**: If a shipping method has no price matrix at all, the bar is correctly hidden (`freeShippingPossible` = false).

## Compatibility

- **Shopware Version**: 6.6.10 - 6.7.x
- **PHP Version**: 8.3+
- **Template Compatibility**: Uses Shopware 6.6+ template structure

## Support

For issues and feature requests, please use the GitHub issue tracker.

## License

This plugin is licensed under the MIT License.

## Credits

Developed by Actualize

---

Made with ❤️ for the Shopware Community
