# Prestashop - LM - Auto Calculate Product Pack Price

## Overview
The PrestaShop module `lm_autopricepack` is designed to automate the updating of pack prices. It automatically recalculates the price of a product pack whenever the price of an item within the pack changes. This ensures that the pack price always reflects the current prices of its components.

## Purpose
- **Automation**: Saves administrators from having to manually recalculate pack prices every time a product price changes, which can be particularly useful in stores with a large number of packs or frequent price changes.
- **Accuracy**: Ensures that pack prices are always correct and up-to-date, reducing pricing errors.

## Installation Instructions
To install the module, follow these steps:
1. [Download a .zip release](https://github.com/martinloic/lm_account_transfer/releases) and install it like any other module.

Or

1. Copy the module files to the `modules` directory of your PrestaShop installation
2. Go to the PrestaShop admin panel.
3. Navigate to the `Modules` section and search for `LM Auto Calculate Product Pack Price`.
4. Click on `Install`.

## Uninstallation Instructions
To uninstall the module, follow these steps:
1. Go to the PrestaShop admin panel.
2. Navigate to the `Modules` section and search for `LM Auto Calculate Product Pack Price`.
3. Click on `Uninstall`.

## Usage
Once installed, the module will automatically update pack prices whenever a product price is changed. No additional configuration is necessary.

You can enable/disable two things :
- **Update pack price when child product is updated** : If this option is enabled, the module will auto calculate pack price if any of its child products are updated.
- **Update pack price when pack is updated** : If this option is enabled, the module will auto calculate pack price when the pack itself is updated.

*Both of these options are enabled by default*
