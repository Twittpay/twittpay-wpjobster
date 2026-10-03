<?php

defined('ABSPATH') or die('Direct access is not allowed.');

class TwittPay_Client
{
    const ENDPOINT = 'https://checkout.twittpay.com';

    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = trim((string) $apiKey);
    }

    /** Create a payment and return the checkout link. */
    public function createPayment(array $data)
    {
        $response = $this->send('/api/payment/create', $data);

        if (empty($response['payment_url'])) {
            $message = isset($response['message']) ? $response['message'] : 'The payment could not be started';
            throw new Exception($message);
        }

        return $response['payment_url'];
    }

    /** Ask TwittPay for the real state of a payment. */
    public function verifyPayment($transactionId)
    {
        $transactionId = trim((string) $transactionId);

        if ($transactionId === '') {
            throw new Exception('Transaction ID cannot be empty');
        }

        return $this->send('/api/payment/verify', array('transaction_id' => $transactionId));
    }

    private function send($path, array $payload)
    {
        if ($this->apiKey === '') {
            throw new Exception('The Brand Key is not set');
        }

        $curl = curl_init(self::ENDPOINT . $path);
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => array(
                'API-KEY: ' . $this->apiKey,
                'Accept: application/json',
                'Content-Type: application/json',
            ),
        ));

        $body  = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new Exception('Connection error: ' . $error);
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new Exception('Unreadable response from the payment service');
        }

        return $decoded;
    }
}
