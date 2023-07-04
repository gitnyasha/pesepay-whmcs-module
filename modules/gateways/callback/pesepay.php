<?php
/**
 * WHMCS Pesepay Payment Callback File
 *
 */

use Codevirtus\Payments\Pesepay;

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once dirname(__DIR__) . '/pesepay/lib/autoloader.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$pesepay = new Pesepay($integrationKey, $encryptionKey);
// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestBody = file_get_contents('php://input');

// Validate and sanitize the received JSON data
    $data = json_decode($requestBody);

// Access the individual fields
    $amountDetails = $data->amountDetails;
    $applicationId = filter_var($data->applicationId, FILTER_SANITIZE_NUMBER_INT);
    $applicationName = htmlspecialchars($data->applicationName, ENT_QUOTES, 'UTF-8');
    $dateOfTransaction = htmlspecialchars($data->dateOfTransaction, ENT_QUOTES, 'UTF-8');
    $pollUrl = htmlspecialchars($data->pollUrl, ENT_QUOTES, 'UTF-8');
    $reasonForPayment = htmlspecialchars($data->reasonForPayment, ENT_QUOTES, 'UTF-8');
    $redirectUrl = htmlspecialchars($data->redirectUrl, ENT_QUOTES, 'UTF-8');
    $referenceNumber = htmlspecialchars($data->referenceNumber, ENT_QUOTES, 'UTF-8');
    $resultUrl = htmlspecialchars($data->resultUrl, ENT_QUOTES, 'UTF-8');
    $returnUrl = htmlspecialchars($data->returnUrl, ENT_QUOTES, 'UTF-8');
    $transactionStatus = htmlspecialchars($data->transactionStatus, ENT_QUOTES, 'UTF-8');
    $transactionStatusCode = filter_var($data->transactionStatusCode, FILTER_SANITIZE_NUMBER_INT);
    $transactionStatusDescription = htmlspecialchars($data->transactionStatusDescription, ENT_QUOTES, 'UTF-8');

    $status = $transactionStatus ? 'SUCCESS' : 'FAILED';

// Construct the sanitized information string
    $information = "Amount: " . $amountDetails->amount . "\n" .
    "Currency Code: " . $amountDetails->currencyCode . "\n" .
    "Default Currency Amount: " . $amountDetails->defaultCurrencyAmount . "\n" .
    "Merchant Amount: " . $amountDetails->merchantAmount . "\n" .
    "Total Transaction Amount: " . $amountDetails->totalTransactionAmount . "\n" .
    "Transaction Service Fee: " . $amountDetails->transactionServiceFee . "\n" .
        "Application ID: " . $applicationId . "\n" .
        "Application Name: " . $applicationName . "\n" .
        "Date of Transaction: " . $dateOfTransaction . "\n" .
        "Poll URL: " . $pollUrl . "\n" .
        "Reason for Payment: " . $reasonForPayment . "\n" .
        "Redirect URL: " . $redirectUrl . "\n" .
        "Return URL: " . $returnUrl . "\n" .
        "Transaction Status: " . $transactionStatus . "\n" .
        "Transaction Status Code: " . $transactionStatusCode . "\n" .
        "Reference Number: " . $referenceNumber . "\n" .
        "Result URL: " . $resultUrl . "\n" .
        "Transaction Status Description: " . $transactionStatusDescription;

    try {

        /**
         * Validate Callback Invoice ID.
         *
         * Checks invoice ID is a valid invoice number. Note it will count an
         * invoice in any status as valid.
         *
         * Performs a die upon encountering an invalid Invoice ID.
         *
         * Returns a normalised invoice ID.
         *
         * @param int $invoiceId Invoice ID
         * @param string $gatewayName Gateway Name
         */

        $invoiceNumber = '';
        $pattern = '/#(\d+)/';
        if (preg_match($pattern, $reasonForPayment, $matches)) {
            $invoiceNumber = intval($matches[1]);
        }

        $invoiceId = checkCbInvoiceID($invoiceNumber, $gatewayParams['name']);

        /**
         * Check Callback Transaction ID.
         *
         * Performs a check for any existing transactions with the same given
         * transaction number.
         *
         * Performs a die upon encountering a duplicate.
         *
         * @param string $transactionId Unique Transaction ID
         */
        checkCbTransID($referenceNumber);

        /**
         * Log Transaction.
         *
         * Add an entry to the Gateway Log for debugging purposes.
         *
         * The debug data can be a string or an array. In the case of an
         * array it will be
         *
         * @param string $gatewayName        Display label
         * @param string|array $debugData    Data to log
         * @param string $transactionStatus  Status
         */
        logTransaction($gatewayParams['name'], $information, $transactionStatus);
        $response = $pesepay->checkPayment($referenceNumber);

        if ($response->paid()) {
            /**
             * Add Invoice Payment.
             *
             * Applies a payment transaction entry to the given invoice ID.
             *
             * @param int $invoiceId         Invoice ID
             * @param string $transactionId  Transaction ID
             * @param float $paymentAmount   Amount paid (defaults to full balance)
             * @param float $paymentFee      Payment fee (optional)
             * @param string $gatewayModule  Gateway module name
             */
            addInvoicePayment(
                $invoiceNumber,
                $referenceNumber,
                $amountDetails->amount,
                $amountDetails->merchantAmount,
                $gatewayModuleName
            );
        }
    } catch (Exception $ex) {

        /**
         * Log Transaction.
         *
         * Add an entry to the Gateway Log for debugging purposes.
         *
         * The debug data can be a string or an array. In the case of an
         * array it will be
         *
         * @param string $gatewayName        Display label
         * @param string|array $debugData    Data to log
         * @param string $transactionStatus  Status
         */
        logTransaction($gatewayParams['name'], $ex, $transactionStatus);

        // Some error logging
        die("An error occured");
    }
} else {
    die("An error occured");

}
