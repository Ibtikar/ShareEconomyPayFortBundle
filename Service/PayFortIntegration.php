<?php

namespace Ibtikar\ShareEconomyPayFortBundle\Service;

use Lsw\ApiCallerBundle\Caller\LoggingApiCaller;
use Lsw\ApiCallerBundle\Call\HttpPostJsonBody;
use Ibtikar\ShareEconomyPayFortBundle\Entity\PfTransaction;

/**
 * @author Karim Shendy <kareem.elshendy@ibtikar.net.sa>
 */
class PayFortIntegration
{
    private $templating;
    private $apiCaller;
    private $config;
    private $merchantIdentifier;
    private $accessCode;
    private $shaType;
    private $shaRequestPhrase;
    private $shaResponsePhrase;
    private $mode;
    private $baseMerchantPageURL;
    private $baseApiURL;
    private $language = 'en';
    private $currency;
    // to use if the card holder dosen't have an email?
    private $spareEmail = "";
    
    const SANDBOX_MODE                 = "sandbox";
    const PRODUCTION_MODE              = "production";
    const SANDBOX_API_URL              = "https://sbpaymentservices.payfort.com/FortAPI/paymentApi";
    const PRODUCTION_API_URL           = "https://paymentservices.payfort.com/FortAPI/paymentApi";
    const SANDBOX_MERCHANT_PAGE_URL    = "https://sbcheckout.PayFort.com/FortAPI/paymentPage";
    const PRODUCTION_MERCHANT_PAGE_URL = "https://checkout.payfort.com/FortAPI/paymentPage";

    /**
     * @param array $config
     */
    public function __construct(array $config, $templating, LoggingApiCaller $apiCaller)
    {
        $this->templating = $templating;
        $this->apiCaller  = $apiCaller;
        $this->config     = $config;
        $this->currency   = $config['currency'];
        $this->mode       = $config['environment'];
        if (isset($config['spare_email'])) {
            $this->spareEmail = $config['spare_email'];
        }
        if ($config['environment'] == self::PRODUCTION_MODE) {
            $this->baseMerchantPageURL = self::PRODUCTION_MERCHANT_PAGE_URL;
            $this->baseApiURL          = self::PRODUCTION_API_URL;

            $this->setEnvironmentAttrs($config['production']);
        } else {
            $this->baseMerchantPageURL = self::SANDBOX_MERCHANT_PAGE_URL;
            $this->baseApiURL          = self::SANDBOX_API_URL;

            $this->setEnvironmentAttrs($config['sandbox']);
        }
    }

    /**
     *
     * @param type $config
     */
    private function setEnvironmentAttrs($config)
    {
        $this->merchantIdentifier = $config['merchantIdentifier'];
        $this->accessCode         = $config['accessCode'];
        $this->shaType            = $config['shaType'];
        $this->shaRequestPhrase   = $config['shaRequestPhrase'];
        $this->shaResponsePhrase  = $config['shaResponsePhrase'];
    }

    /**
     * calculate Signature Value
     *
     * @param array $params
     * @return string signature
     */
    private function calculateSignature($params, $shaPhrase, $excludedParams)
    {
        ksort($params);

        $data = $shaPhrase;
        foreach ($params as $key => $val) {
            if (!in_array($key, $excludedParams)) {
                $data .= $key . '=' . $val;
            }
        }
        $data .= $shaPhrase;

        return hash($this->shaType, $data);
    }

    /**
     * calculate request Signature Value
     *
     * @param array $requestParams
     * @return string Request signature
     */
    private function calculateRequestSignature($requestParams)
    {
        return $this->calculateSignature($requestParams, $this->shaRequestPhrase, ["card_number", "expiry_date", "card_security_code", "card_holder_name"]);
    }

    /**
     * calculate response Signature Value
     *
     * @param array $requestParams
     * @return string Request signature
     */
    public function calculateResponseSignature($requestParams)
    {
        return $this->calculateSignature($requestParams, $this->shaResponsePhrase, ["signature"]);
    }

    /**
     *
     * @param type $responseParameters
     * @return type
     */
    public function isValidResponse($responseParameters)
    {
        return isset($responseParameters['signature']) && $this->calculateResponseSignature($responseParameters) == $responseParameters['signature'];
    }

    /**
     * make new API request
     *
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function makeAPIRequest($params)
    {
        $parameters = $this->addDefaultParams($params);
        $response   = $this->apiCaller->call(new HttpPostJsonBody($this->baseApiURL, $parameters, true, ['HTTPHEADER' => ['Content-Type:application/json']]));

        // verify the request signature
        if (!$this->isValidResponse($response)) {
            throw new \Exception("Response signature not correct.");
        }

        return $response;
    }

    /**
     * store new credit card in the payment gateway
     *
     * @param type $cardNumber
     * @param type $cardSecurityCode
     * @param type $cardExpiryDate
     * @return type
     */
    public function storeCustomerCredit($holderName, $merchantReference, $cardNumber, $cardSecurityCode, $cardExpiryDate)
    {
        $parameters = [
            'card_holder_name'   => $holderName,
            'merchant_reference' => $merchantReference,
            'service_command'    => 'TOKENIZATION',
            'card_number'        => $cardNumber,
            'card_security_code' => $cardSecurityCode,
            'expiry_date'        => $cardExpiryDate
        ];

        return $this->makeAPIRequest($parameters);
    }

    /**
     * request new SDK token
     *
     * @param type $deviceID
     * @return type
     */
    public function requestSDKToken($deviceID)
    {
        $parameters = [
            'service_command' => 'SDK_TOKEN',
            'device_id'       => $deviceID
        ];

        return $this->makeAPIRequest($parameters);
    }

    /**
     *
     * @param type $deviceID
     * @return type
     */
    public function refund($fortID, $amount, $currency)
    {
        $parameters = [
            'command'  => 'REFUND',
            'fort_id'  => $fortID,
            'amount'   => $amount,
            'currency' => $currency
        ];

        return $this->makeAPIRequest($parameters);
    }

    /**
     * gererates the tokenization form html that should be submitted to payfort
     *
     * @param type $merchantReference
     * @return type
     */
    public function getTokenizationForm($merchantReference)
    {
        $params = [
            'service_command'    => 'TOKENIZATION',
            'merchant_reference' => $merchantReference
        ];

        $formParams = $this->addDefaultParams($params);

        return $this->templating->render('IbtikarShareEconomyPayFortBundle:Default:tokenizationForm.html.twig', ['formParams' => $formParams, 'formAction' => $this->baseMerchantPageURL]);
    }

    /**
     *
     * @param string $tokenName
     * @param  $amount
     * @param string $merchantReference
     * @return array
     */
    public function purchase($tokenName, $amount, $merchantReference, $email)
    {
        $parameters = [
            'command'            => 'PURCHASE',
            'eci'                => 'RECURRING',
            'currency'           => $this->currency,
            'amount'             => round($amount * 100),
            'token_name'         => $tokenName,
            'merchant_reference' => $merchantReference,
            'customer_email'     => $email
        ];

        return $this->makeAPIRequest($parameters);
    }

    /**
     * @param PfTransaction $transaction
     * @return array
     */
    public function purchaseTransaction(PfTransaction $transaction)
    {
        $email = $transaction->getPaymentMethod()->getHolder()->getEmail();
        if (!$email && $this->spareEmail) {
            $email = $this->spareEmail;
        }
        return $this->purchase($transaction->getPaymentMethod()->getTokenName(), $transaction->getAmount(), $transaction->getMerchantReference(),
                $email);
    }

    /**
     * add the static request parameters and calculate signature
     *
     * @param array $params
     * @return array
     */
    private function addDefaultParams($params)
    {
        $params['language']            = $this->language;
        $params['merchant_identifier'] = $this->merchantIdentifier;
        $params['access_code']         = $this->accessCode;
        $params['signature']           = $this->calculateRequestSignature($params);

        return $params;
    }

    /**
     * @param PfTransaction $transaction
     * @return array
     */
    public function getTransactionStatus(PfTransaction $transaction)
    {
        $parameters = [
            'query_command'      => 'CHECK_STATUS',
            'merchant_reference' => $transaction->getMerchantReference()
        ];

        if ($transaction->getFortId()) {
            $parameters['fort_id'] = $transaction->getFortId();
        }

        return $this->makeAPIRequest($parameters);
    }


    /**
     *
     * @param string $tokenName
     * @param  $amount
     * @param string $merchantReference
     * @return array
     */
    public function nonRecurringPurchase($tokenName, $amount, $merchantReference, $email, $returnUrl)
    {
        $parameters = [
            'command'            => 'PURCHASE',
            'currency'           => $this->currency,
            'amount'             => round($amount * 100),
            'token_name'         => $tokenName,
            'merchant_reference' => $merchantReference,
            'customer_email'     => $email,
            'return_url'         => $returnUrl
        ];

        return $this->makeAPIRequest($parameters);
    }
}