# Pesepay Payment Gateway Module for WHMCS

### Summary

Pesepay is a payment gateway module designed for WHMCS, a popular web hosting billing and automation platform. The module allows customers to make payments through various payment methods, including Ecocash and Zimswitch for local transactions, as well as Mastercard and Visa for international payments. With a focus on security and seamless integration, Pesepay ensures a reliable and user-friendly payment experience for both merchants and customers.

### Installation

To install the Pesepay gateway module for WHMCS, follow these steps:

1. Download the Pesepay module files from the GitHub repository: [GitHub Repository](https://github.com/gitnyasha/pesepay-whmcs-module
)
2. Upload the entire contents of the pesepay-whmcs-module directory to the modules/gateways/ directory of your WHMCS installation take **note** that the callback folder will aleady be there so you just have to copy the file inside the pesepay-whmcs-module/modules/gateways/callback directory into your whmcs modules/gateways/callback directory.
3. Your directory structure after the installation should look like this:

```bash
modules/gateways/pesepay
modules/gateways/callback/pesepay.php
modules/gateways/pesepay.php
```

Once the files are in their respective locations, you can proceed with the configuration of the Pesepay payment gateway within the WHMCS admin panel.


### Contribution
Contributions to the Pesepay payment gateway module for WHMCS are welcome! If you encounter any issues, have feature requests, or want to improve the module, feel free to create a pull request or raise an issue in the GitHub repository.

GitHub Repository: [gitnyasha](https://github.com/gitnyasha/pesepay-whmcs-module)

Let's work together to make Pesepay even better!
