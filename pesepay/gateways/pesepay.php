<?php

use Codevirtus\Payments\Pesepay;
/**
 * WHMCS Pesepay Payment Gateway Module
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 * @return array
 */
function gatewaymodule_MetaData()
{
    return array(
        'DisplayName' => 'Pesepay Payment Gateway Module',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @return array
 */
function gatewaymodule_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Pesepay',
        ),
        'integrationKey' => array(
            'FriendlyName' => 'Integration Key',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your integration key here',
        ),
        'encryptionKey' => array(
            'FriendlyName' => 'Encryption Key',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter encryption key here',
        ),
    );
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 * @return string
 */
function gatewaymodule_link($params)
{
    // Gateway Configuration Parameters
    $integrationKey = $params['integrationKey'];
    $encryptionKey = $params['encryptionKey'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];
    $email = $params['clientdetails']['email'];

    // System Parameters
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $moduleName = $params['paymentmethod'];

    $url = $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php';

    $pesepay = new Pesepay($integrationKey, $encryptionKey);
    $pesepay->returnUrl = $returnUrl;
    $pesepay->resultUrl = $url;

    $transaction = $pesepay->createTransaction($amount, $currencyCode, "Website hosting payment for invoice #" . $invoiceId . " Time: " . date("h:i:sa") . "", "Invoice #" . $invoiceId . " Time: " . date("h:i:sa"));

    $htmlOutput = "";

    try {

        $response = $pesepay->initiateTransaction($transaction);

        if (!$response->success()) {
            throw new Exception("Pesepay Error Initiating Transaction");
        }

        $svg = base64_encode(file_get_contents(__DIR__ . "/pesepay/pesepaybtn.svg"));

        // Append the form HTML to the output
        $htmlOutput .= "<form style='padding-top: 15px' method='get' action='https://pay.pesepay.com/#/pesepay-payments{$response->redirectUrl()}'>
            <button title='Pay with Pesepay' style='height:55px;background:none;border:none;' type='submit'>
                <img src=\"data:image/svg+xml;base64,{$svg}\" style='max-height:55px;'>
            </button>
        </form>";

    } catch (Exception $ex) {
        $htmlOutput .= "<h6>Error Initiating transaction</h6>";
    }

    return $htmlOutput;
}
