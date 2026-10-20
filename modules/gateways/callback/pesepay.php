<?php
/**
 * WHMCS Pesepay Payment Callback File
 *
 * Verification follows https://developers.pesepay.com/webhooks/verifying-callbacks/
 *
 * 1. The result URL carries an HMAC token (invoice, amount, currency) signed
 *    with the encryption key. Anything without a valid token is a forged post.
 * 2. The Authorization header must carry our own integration key — Pesepay
 *    echoes it back.
 * 3. The result is confirmed server-to-server with Check Payment Status.
 *    The callback body proves nothing; only the encrypted, key-authenticated
 *    check response decides whether the invoice is marked paid. The verified
 *    amount and currency must match the ones in the token.
 */

use Codevirtus\Payments\Pesepay;

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once dirname(__DIR__) . '/pesepay/lib/autoloader.php';

/**
 * Read the Authorization header, with the common PHP-CGI/FPM fallbacks.
 *
 * @return string
 */
function pesepay_authorization_header()
{
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if (function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $name => $value) {
            if (strtolower($name) === 'authorization') {
                return $value;
            }
        }
    }
    return '';
}

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Only POST callbacks are valid.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("An error occured");
}

$encryptionKey = isset($gatewayParams['encryptionKey']) ? $gatewayParams['encryptionKey'] : '';

// Step 1: verify the HMAC token that pesepay_link() appended to the result
// URL. It binds this callback to one invoice, amount and currency. Without
// the encryption key a forger cannot produce a valid token, so replaying
// another payment's reference number cannot credit a different invoice.
$tokenInvoiceId = isset($_GET['invoiceid']) ? $_GET['invoiceid'] : '';
$tokenAmount = isset($_GET['amount']) ? $_GET['amount'] : '';
$tokenCurrency = isset($_GET['currency']) ? $_GET['currency'] : '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

$expectedToken = hash_hmac(
    'sha256',
    $tokenInvoiceId . '|' . $tokenAmount . '|' . $tokenCurrency,
    $encryptionKey
);

if ($token === '' || $tokenInvoiceId === '' || $tokenAmount === '' || $tokenCurrency === ''
    || !hash_equals($expectedToken, $token)) {
    logTransaction(
        $gatewayParams['name'],
        'Rejected callback: missing or invalid verification token on the result URL.',
        'Failed'
    );
    http_response_code(403);
    die('Forbidden');
}

// Step 2: Pesepay sends our own integration key back in the Authorization
// header. Reject the request if it is missing or does not match.
$integrationKey = isset($gatewayParams['integrationKey']) ? $gatewayParams['integrationKey'] : '';
$authorization = pesepay_authorization_header();

if ($integrationKey === '' || $authorization === '' || !hash_equals($integrationKey, $authorization)) {
    logTransaction(
        $gatewayParams['name'],
        "Rejected callback: Authorization header missing or does not match the integration key.\n" .
        "Received Authorization: " . ($authorization === '' ? '(missing)' : '(does not match)'),
        'Failed'
    );
    http_response_code(401);
    die('Unauthorized');
}

$requestBody = file_get_contents('php://input');

// Validate and sanitize the received JSON data
$data = json_decode($requestBody);

if (!is_object($data) || empty($data->referenceNumber)) {
    logTransaction($gatewayParams['name'], 'Rejected callback: missing referenceNumber in payload.', 'Failed');
    http_response_code(400);
    die('Bad Request');
}

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

$isSandbox = !empty($gatewayParams['sandbox']) && $gatewayParams['sandbox'] != 'off';
$pesepay = new Pesepay($gatewayParams['integrationKey'], $gatewayParams['encryptionKey'], $isSandbox);

try {

    /**
     * Validate Callback Invoice ID.
     *
     * The invoice number comes from the signed token on the result URL, not
     * from the (untrusted) callback body.
     *
     * @param int $invoiceId Invoice ID
     * @param string $gatewayName Gateway Name
     */
    $invoiceId = checkCbInvoiceID((int) $tokenInvoiceId, $gatewayParams['name']);

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

    // Step 2: confirm the outcome server-to-server. The callback body alone
    // proves nothing — ask Pesepay directly with Check Payment Status.
    $verification = $pesepay->checkPayment($referenceNumber);

    if (!$verification->success()) {
        logTransaction(
            $gatewayParams['name'],
            "Callback verification failed for {$referenceNumber}: " . $verification->message(),
            'Failed'
        );
        http_response_code(502);
        die('Verification failed');
    }

    // Cross-check: the verified transaction must match the invoice, amount
    // and currency that were signed into the token when the payment link was
    // created. A reference number from a smaller payment can never settle a
    // bigger invoice this way.
    $verifiedAmount = (float) $verification->amount();
    $verifiedCurrency = strtoupper((string) $verification->currencyCode());

    if (abs((float) $tokenAmount - $verifiedAmount) > 0.0001
        || strtoupper($tokenCurrency) !== $verifiedCurrency) {
        logTransaction(
            $gatewayParams['name'],
            "Rejected callback for {$referenceNumber}: verified transaction does not match the invoice token.\n" .
            "Token amount: {$tokenAmount} {$tokenCurrency}\n" .
            "Verified amount: {$verification->amount()} {$verification->currencyCode()}",
            'Failed'
        );
        http_response_code(403);
        die('Verification failed');
    }

    // The body's status field is logged for context only; the decision to
    // mark the invoice paid is made exclusively from the verified response.
    logTransaction(
        $gatewayParams['name'],
        $information . "\nVerified Status: " . $verification->transactionStatus(),
        $verification->transactionStatus()
    );

    // Step 3/4: the invoice was matched above (checkCbInvoiceID) and the
    // reference number is unique (checkCbTransID). Act on the verified status.
    if ($verification->paid()) {
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
            $referenceNumber,
            $verification->amount(),
            $verification->merchantAmount(),
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
    logTransaction($gatewayParams['name'], $ex, 'Failed');

    // Some error logging
    die("An error occured");
}
