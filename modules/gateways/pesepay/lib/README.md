## Installation

You can install the package via composer:

```bash
composer require codevirtus/pesepay
```

### Getting Started
Import the library into your project/application

```php
require_once 'path/to/vendor/autoload.php';
use Codevirtus\Payments\Pesepay
```

Create an instance of the `Pesepay` class using your integration key and encryption key as supplied by Pesepay.

```php 
$pesepay = new Pesepay("INTEGRATION KEY", "ENCRYPTION KEY");
```

Set return and result urls

```php 
$pesepay->returnUrl = "http://example.com/gateway/return";
$pesepay->resultUrl = "http://example.com/gateway/return";
```

### Make seamless payment

Create the payment 
##### NB: The customer object must be populated — Pesepay returns 500 when `customer` is omitted or its fields are null (verified against the live API, October 2026)

```php
$payment = $pesepay->createPayment('CURRECNCY_CODE', 'PAYMENT_METHOD_CODE', 'CUSTOMER_EMAIL', 'CUSTOMER_PHONE_NUMBER', 'CUSTOMER_NAME');
```

Create an `object` of the required fields (if any)
```php
$requiredFields = ['requiredFieldName'=>'requiredFieldValue'];
```

For methods with no required fields (e.g. InnBucks `PZW212`) send an empty object, not an empty array:
```php
$requiredFields = (object) [];   // encodes as {}
```

Send of the payment
```php
$response = $pesepay->makeSeamlessPayment($payment, 'Online Transaction', $AMOUNT, $requiredFields, 'MERCHANT_REFERENCE(OPTIONAL)');

if ($response->success()) {
    # Save the reference number and/or poll url (used to check the status of a transaction)
    $referenceNumber = $response->referenceNumber();
    $pollUrl = $response->pollUrl();

} else {
    #Get Error Message
    $errorMessage = $response->message();
}
```

### Make redirect payment

Create a transaction
```php
$transaction = $pesepay->createTransaction($amount, 'CURRENCY_CODE', 'PAYMENT_REASON', 'MERCHANT_REFERENCE(OPTIONAL)');
```

Initiate the transaction
```php
$response = $pesepay->initiateTransaction($transaction);

if ($response->success()) {
    # Save the reference number and/or poll url (used to check the status of a transaction)
    $referenceNumber = $response->referenceNumber();
    $pollUrl = $response->pollUrl();
    # Get the redirect url and redirect user to complete transaction   
    $redirectUrl = $response->redirectUrl();
    
} else {
    # Get error message
    $errorMessage = $response->message();
}
```

### Check Payment Status
#### Method 1: Using referenceNumber
```php
$response = $pesepay->checkPayment($referenceNumber);

if ($response->success()) {

    if ($response->paid()) {
        # Payment was successfull
    }

} else {
    # Get error message
    $errorMessage = $response->message();
}
```
#### Method 2: Using poll url
```php
$response = $pesepay->pollTransaction($pollUrl);

if ($response->success()) {

    if ($response->paid()) {
        # Payment was successfull
    }

} else {
    # Get error message
    $errorMessage = $response->message();
}
```