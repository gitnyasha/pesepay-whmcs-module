<?php

use Codevirtus\Payments\Pesepay;

/**
 * WHMCS Pesepay Payment Gateway Module
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/pesepay/lib/autoloader.php';

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 * @return array
 */
function pesepay_MetaData()
{
    return array(
        'DisplayName' => 'Pesepay',
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
function pesepay_config()
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
        'sandbox' => array(
            'FriendlyName' => 'Sandbox Mode',
            'Type' => 'yesno',
            'Description' => 'Enable to send transactions to the Pesepay sandbox environment (https://api.test.sandbox.pesepay.com). Make sure your sandbox integration and encryption keys are entered above.',
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
function pesepay_link($params)
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

    // Sandbox Configuration Parameter
    $isSandbox = !empty($params['sandbox']) && $params['sandbox'] != 'off';

    $url = $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php';

    // Sign the invoice, amount and currency into the result URL. The callback
    // rejects anything that does not carry a valid token, so a forged post
    // (e.g. replaying another payment's reference number) cannot credit a
    // different invoice without the encryption key.
    $tokenPayload = $invoiceId . '|' . $amount . '|' . $currencyCode;
    $token = hash_hmac('sha256', $tokenPayload, $encryptionKey);
    $resultUrl = $url . '?invoiceid=' . urlencode($invoiceId)
        . '&amount=' . urlencode($amount)
        . '&currency=' . urlencode($currencyCode)
        . '&token=' . $token;

    $pesepay = new Pesepay($integrationKey, $encryptionKey, $isSandbox);
    $pesepay->returnUrl = $returnUrl;
    $pesepay->resultUrl = $resultUrl;

    $transaction = $pesepay->createTransaction($amount, $currencyCode, "Payment for invoice " . $invoiceId, "Invoice #" . $invoiceId . " Time: " . date("h:i:sa"));

    $htmlOutput = "";

    try {

        $response = $pesepay->initiateTransaction($transaction);


        if ($response instanceof \Codevirtus\Payments\ErrorResponse) {

            $htmlOutput .= "<pre>Pesepay Error:\n" .
                htmlspecialchars(print_r($response, true)) .
                "</pre>";

            return $htmlOutput;
        }


        if (!$response->success()) {

            $htmlOutput .= "<pre>Pesepay Response:\n" .
                htmlspecialchars(print_r($response->rawData(), true)) .
                "</pre>";

            return $htmlOutput;
        }


        $svg = base64_encode(file_get_contents(__DIR__ . "/pesepay/lib/pesepaybtn.svg"));


        $htmlOutput .= "<form style='padding-top: 15px' method='get' action='{$response->redirectUrl()}'>
            <button type='submit' style='height:55px;background:none;border:none;'>
                <img src=\"data:image/svg+xml;base64,{$svg}\" style='max-height:55px;'>
            </button>
            </form>";

    } catch (Exception $ex) {

        $htmlOutput .= "<pre>Exception:\n" .
            htmlspecialchars($ex->getMessage()) .
            "</pre>";
    }

    return $htmlOutput;
}
