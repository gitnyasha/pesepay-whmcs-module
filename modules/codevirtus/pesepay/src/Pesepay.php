<?php

namespace Codevirtus\Payments;

include_once 'Payment.php';
include_once 'Customer.php';
include_once 'Amount.php';
include_once 'Response.php';
include_once 'ErrorResponse.php';
include_once 'Transaction.php';

class Pesepay
{
    const BASE_URL = "https://api.pesepay.com/api/payments-engine";
    /**
     * Check payment status API endpoint
     */
    const CHECK_PAYMENT_URL = self::BASE_URL . '/v1/payments/check-payment';

    /**
     * Make Seamless payment API Endpoint
     */
    const MAKE_SEAMLESS_PAYMENT_URL = self::BASE_URL . '/v2/payments/make-payment';

    /**
     * Initiate payment API Endpoint
     */
    const INITIATE_PAYMENT_URL = self::BASE_URL . '/v1/payments/initiate';

    const ALGORITHM = 'AES-256-CBC';

    const INIT_VECTOR_LENGTH = 16;

    private $integrationKey;
    private $encryptionKey;
    public $resultUrl;
    public $returnUrl;

    public function __construct($integrationKey, $encryptionKey)
    {
        $this->integrationKey = $integrationKey;
        $this->encryptionKey = $encryptionKey;
    }

    public function pollTransaction($pollUrl)
    {

        $response = $this->initCurlRequest("GET", $pollUrl);

        if ($response instanceof ErrorResponse) {
            return $response;
        }

        $decryptedData = $this->decrypt($response['payload']);

        $jsonDecoded = json_decode($decryptedData, true);
        $referenceNumber = $jsonDecoded['referenceNumber'];
        $pollUrl = $jsonDecoded['pollUrl'];
        $paid = $jsonDecoded['transactionStatus'] == 'SUCCESS';

        return new Response($referenceNumber, $pollUrl, null, $paid);
    }

    public function checkPayment($referenceNumber)
    {
        $url = self::CHECK_PAYMENT_URL . '?referenceNumber=' . $referenceNumber;
        return $this->pollTransaction($url);
    }

    public function initiateTransaction($transaction)
    {
        if ($this->resultUrl == null) {
            throw new \InvalidArgumentException('Result url has not beeen specified.');
        }

        if ($this->returnUrl == null) {
            throw new \InvalidArgumentException('Return url has not been specified.');
        }

        $transaction->resultUrl = $this->resultUrl;
        $transaction->returnUrl = $this->returnUrl;

        $encryptedData = $this->encrypt(json_encode($transaction));

        $payload = json_encode(['payload' => $encryptedData]);

        $response = $this->initCurlRequest("POST", self::INITIATE_PAYMENT_URL, $payload);

        if ($response instanceof ErrorResponse) {
            return $response;
        }

        $decryptedData = $this->decrypt($response['payload']);

        $jsonDecoded = json_decode($decryptedData, true);
        $referenceNumber = $jsonDecoded['referenceNumber'];
        $pollUrl = $jsonDecoded['pollUrl'];
        $redirectUrl = $jsonDecoded['redirectUrl'];

        return new Response($referenceNumber, $pollUrl, $redirectUrl);
    }

    public function makeSeamlessPayment($payment, $reasonForPayment, $amount, $requiredFields = null)
    {
        if ($this->resultUrl == null) {
            throw new \InvalidArgumentException('Result url has not beeen specified.');
        }

        $payment->resultUrl = $this->resultUrl;
        $payment->returnUrl = $this->returnUrl;
        $payment->reasonForPayment = $reasonForPayment;
        $payment->amountDetails = new Amount($amount, $payment->currencyCode);

        $payment->setRequiredFields($requiredFields);

        $encryptedData = $this->encrypt(json_encode($payment));

        $payload = json_encode(['payload' => $encryptedData]);

        $response = $this->initCurlRequest("POST", self::MAKE_SEAMLESS_PAYMENT_URL, $payload);

        if ($response instanceof ErrorResponse) {
            return $response;
        }

        $decryptedData = $this->decrypt($response['payload']);

        $jsonDecoded = json_decode($decryptedData, true);
        $referenceNumber = $jsonDecoded['referenceNumber'];
        $pollUrl = $jsonDecoded['pollUrl'];

        return new Response($referenceNumber, $pollUrl);
    }

    public function createTransaction($amount, $currencyCode, $paymentReason, $merchantReference = null)
    {
        return new Transaction($amount, $currencyCode, $paymentReason, $merchantReference);
    }

    public function createPayment($currencyCode, $paymentMethodCode, $email, $phone = null, $name = null)
    {

        $customer = new Customer($email, $phone, $name);

        return new Payment($currencyCode, $paymentMethodCode, $customer);
    }

    private function initCurlRequest($requestType, $url, $payload = null)
    {
        $headers = [
            'key: ' . $this->integrationKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $requestType,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'cUrl',
        ]);

        if ($requestType == "POST") {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($curl);

        $status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        $result = json_decode($response, true);

        if ($status_code == 200) {
            return $result;
        } else {
            $message = $result['message'];
            return new ErrorResponse($message);
        }
    }

    /**
     * Encrypt input text by AES-256-CBC algorithm
     *
     * @param string $secretKey 16/24/32 -characters secret key
     * @param string $plainText Text for encryption
     *
     */

    private function encrypt($plainText)
    {
        // echo $this->encryptionKey;
        try {
            // Check secret length
            if (!$this->isKeyLengthValid($this->encryptionKey)) {
                throw new \InvalidArgumentException("Secret key's length must be 128, 192 or 256 bits");
            }
            // Get initialization vector
            $initVector = substr($this->encryptionKey, 0, self::INIT_VECTOR_LENGTH);
            // Encrypt input text
            $raw = openssl_encrypt(
                $plainText,
                self::ALGORITHM,
                $this->encryptionKey,
                0,
                $initVector
            );
            // Return successful encoded object
            return $raw;
        } catch (\Exception$e) {
            // Operation failed
            echo $e;
            return new static(isset($initVector), null, $e->getMessage());
        }
    }

    /**
     * Decrypt encoded text by AES-256-CBC algorithm
     *
     * @param string $secretKey  16/24/32 -characters secret password
     * @param string $cipherText Encrypted text
     *
     */

    private function decrypt($cipherText)
    {
        try {
            // Check secret length
            if (!$this->isKeyLengthValid($this->encryptionKey)) {
                throw new \InvalidArgumentException("Secret key's length must be 128, 192 or 256 bits");
            }

            // Get raw encoded data
            $encoded = base64_decode($cipherText);

            // Slice initialization vector using the secret key
            $initVector = substr($this->encryptionKey, 0, self::INIT_VECTOR_LENGTH);

            // Trying to get decrypted text
            $decoded = openssl_decrypt(
                $encoded,
                self::ALGORITHM,
                $this->encryptionKey,
                OPENSSL_RAW_DATA,
                $initVector
            );

            //$initVector OPENSSL_RAW_DATA
            if ($decoded === false) {
                // Operation failed
                return new static(isset($initVector), null, openssl_error_string());
            }

            // Return successful decoded object
            return $decoded;

        } catch (\Exception$e) {
            // Operation failed
            return new static(isset($initVector), null, $e->getMessage());
        }

    }

    /**
     * Check that secret password length is valid
     *
     * @param string $secretKey 16/24/32 -characters secret password
     *
     * @return bool
     */

    private function isKeyLengthValid($secretKey)
    {
        $length = strlen($secretKey);
        return $length == 16 || $length == 24 || $length == 32;
    }
}