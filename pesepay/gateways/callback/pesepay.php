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
$integrationId = $gatewayParams['integrationID'];
$integrationKey = $gatewayParams['integrationKey'];

$transactionStatus = $success ? 'Success' : 'Failure';

$pesepay = new Pesepay($integrationKey, $encryptionKey);

try {
    $pesepayResponse = $pesepay->processStatusUpdate();

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

    $invoiceId = checkCbInvoiceID($pesepayResponse->referenceNumber(), $gatewayParams['name']);

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
    checkCbTransID($pesepayResponse->paynowReference());

    $transactionStatus = $pesepayResponse->paid() ? 'Success' : 'Failure';

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
    logTransaction($gatewayParams['name'], $_POST, $transactionStatus);

    if ($pesepayResponse->paid()) {
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
            $invoiceId,
            $transactionId,
            null,
            null,
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
