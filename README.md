# Paymento Cryptocurrency Payment Gateway for OpenCart

## Overview

This plugin integrates Paymento's cryptocurrency payment gateway into OpenCart, allowing e-commerce stores to accept various cryptocurrencies as payment. With this plugin, customers can pay using popular cryptocurrencies and store owner receive cryptocurrencies in their own wallets.
Store owners do not need to set product price in crypto, just set fiat currency and Paymento will handle currency exchanges based on live rate.

## Features

- Accept multiple cryptocurrencies
- Real-time exchange rates
- Seamless integration with OpenCart checkout
- Automatic order status updates
- Configurable risk speed settings

## Requirements

- OpenCart version 4.x
- PHP 7.4 or higher
- cURL extension enabled
- A Paymento merchant account (Sign up at [https://paymento.io](https://paymento.io) if you don't have one) and create your store.

## Installation

1. Download the latest release ZIP file from the [GitHub repository](https://github.com/paymento/paymento-opencart-plugin/blob/main/paymento_gateway.ocmod.zip).
2. Log in to your OpenCart admin panel.
3. Navigate to Extensions > Installer.
4. Upload and Install "paymento_gateway.ocmod.zip".
5. Navigate to Extensions > Extensions > Choose "Payments" from dropdown menu.
7. Find "Paymento Cryptocurrency Gateway" in the list and click the "Install" button.

## Configuration

1. After installation, click the "Edit" button next to the Paymento Cryptocurrency Gateway.
2. Enter your Paymento API Key and Secret Key (obtain these from your Paymento merchant dashboard).
3. Configure the following settings:
   - Status: Enable or disable the payment method
   - Title: The name that appears to customers during checkout
   - Risk Speed: Choose between different transaction speed options
   - Order Status: Set the default status for new orders
   - Geo Zone: Limit the payment method to specific geographical zones if needed
4. Save the changes.

## Usage

Once installed and configured, Paymento will appear as a payment option during the checkout process. Customers can select it to pay with their preferred cryptocurrency.


## Troubleshooting

- Enable debug mode in the plugin settings to log detailed information.
- Check the OpenCart error logs for any issues.
- Ensure your server meets all the requirements listed above.

## Support

For issues related to the plugin, please [open an issue](https://github.com/paymento/paymento-opencart-plugin/issues) on GitHub.

For account-related questions or issues with the Paymento service, please contact Paymento support directly.

## Contributing

Contributions are welcome! Please fork the repository and submit a pull request with your improvements.

## License

This plugin is released under the [MIT License](LICENSE).

## Disclaimer

This plugin is provided as-is. While we strive to maintain and improve it, please use it at your own risk. Always test thoroughly in a staging environment before deploying to production.
