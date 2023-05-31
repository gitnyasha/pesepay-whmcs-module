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

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Retrieve data returned in payment gateway callback
// Varies per payment gateway
// $success = $_POST["x_status"];
// $invoiceId = $_POST["x_invoice_id"];
// $transactionId = $_POST["x_trans_id"];
// $paymentAmount = $_POST["x_amount"];
// $paymentFee = $_POST["x_fee"];
// $hash = $_POST["x_hash"];
// $integrationId = $gatewayParams['integrationID'];
// $integrationKey = $gatewayParams['integrationKey'];

// $transactionStatus = $success ? 'Success' : 'Failure';

$pesepay = new Pesepay($integrationKey, $encryptionKey);
// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestBody = file_get_contents('php://input');

// Validate and sanitize the received JSON data
    $data = json_decode($requestBody);

// Access the individual fields
    $merchantReference = $data->merchantReference;
    $amountDetails = $data->amountDetails;
    $applicationId = filter_var($data->applicationId, FILTER_SANITIZE_NUMBER_INT);
    $applicationName = filter_var($data->applicationName, FILTER_SANITIZE_STRING);
    $dateOfTransaction = filter_var($data->dateOfTransaction, FILTER_SANITIZE_STRING);
    $pollUrl = filter_var($data->pollUrl, FILTER_SANITIZE_STRING);
    $reasonForPayment = filter_var($data->reasonForPayment, FILTER_SANITIZE_STRING);
    $redirectUrl = filter_var($data->redirectUrl, FILTER_SANITIZE_STRING);
    $referenceNumber = filter_var($data->referenceNumber, FILTER_SANITIZE_STRING);
    $resultUrl = filter_var($data->resultUrl, FILTER_SANITIZE_STRING);
    $returnUrl = filter_var($data->returnUrl, FILTER_SANITIZE_STRING);
    $transactionStatus = filter_var($data->transactionStatus, FILTER_SANITIZE_STRING);
    $transactionStatusCode = filter_var($data->transactionStatusCode, FILTER_SANITIZE_NUMBER_INT);
    $transactionStatusDescription = filter_var($data->transactionStatusDescription, FILTER_SANITIZE_STRING);

    $status = $transactionStatus ? 'SUCCESS' : 'FAILED';

// Construct the sanitized information string
    $information = "merchantReference: " . $merchantReference . "\n" .
    "Amount: " . $amountDetails->amount . "\n" .
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

        $invoiceId = checkCbInvoiceID($merchantReference, $gatewayParams['name']);

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
                $merchantReference,
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
