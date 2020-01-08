<?php


namespace Metav\NgPayments\PaymentProviders;

use GuzzleHttp\Client;
use Metav\NgPayments\Exceptions\FailedTransactionException;
use Metav\NgPayments\PaymentProviders\Base\AbstractPaymentProvider;

class Paystack extends AbstractPaymentProvider
{
    public function __construct($public_key, $secret_key, $app_env)
    {
        parent::__construct($public_key, $secret_key, $app_env);
        $this->baseUrl = "https://api.paystack.co";
        $this->httpClient = new Client(['base_uri' => $this->baseUrl]);
    }

    public function initializePayment($request_body)
    {
        $relative_url = '/initialize';
        $request_body = $this->adaptBodyParamsToPaystackAPI($request_body);
        $this->validateRequestBodyHasRequiredParams($request_body, ['email', 'amount']);
        $request_options = $this->getPostRequestOptionsForPaystack($request_body);
        $this->httpResponse = $this->httpClient->post($relative_url, $request_options);
        return $this;
    }

    public function verifyPayment($reference, $amount = null)
    {
        $relative_url = '/transaction/verify/' . $reference;
        $this->httpResponse = $this->httpClient->get($relative_url, $this->getPostRequestOptionsForPaystack());
        $response_body = $this->getResponseBodyAsArray();
        $status = $response_body['data']['status'];

        if ($this->transactionExceptions == true && $status != 'success') {
            throw new FailedTransactionException($response_body);
        }

        if ($amount != null && $response_body['data']['amount'] != $amount) {
            throw new FailedTransactionException(
                $response_body,
                'The amount paid by the customer does not match the required amount'
            );
        }

        return $status;
    }

    public function getPaymentPageUrl()
    {
        return @$this->getResponseBodyAsArray()['data']['authorization_url'] ?? '';
    }

    public function getPaymentReference()
    {
        return @$this->getResponseBodyAsArray()['data']['reference'] ?? '';
    }

    public function savePlan($request_body)
    {
        $relative_url = "/plan";
        $request_body = $this->adaptBodyParamsToPaystackAPI($request_body);

        $plan_id = @$request_body['id'] ?? @$request_body['plan_code'] ?? null;
        if ($plan_id == null) {
            return $this->createPlan($request_body, $relative_url);
        } else {
            return $this->updatePlan($request_body, $plan_id, $relative_url);
        }
    }

    public function listPlans($query_params = [])
    {
        $relative_url = "/plan";
        $query_params = $this->adaptBodyParamsToPaystackAPI($query_params);
        $this->httpResponse = $this->httpClient->get($relative_url, $this->getRequestOptionsForPaystack($query_params));
        return @$this->getResponseBodyAsArray()['data'] ?? [];
    }

    public function fetchPlan($plan_id)
    {
        $relative_url = "/plan" . "/" . $plan_id;
        $this->httpResponse = $this->httpClient->get($relative_url, $this->getRequestOptionsForPaystack());
        return @$this->getResponseBodyAsArray()['data'] ?? [];
    }

    public function saveSubAccount($request_body)
    {
        $relative_url = "/subaccount";
        $request_body = $this->adaptBodyParamsToPaystackAPI($request_body);

        $subaccount_id = @$request_body['id'] ?? @$request_body['subaccount_code'] ?? null;
        if ($subaccount_id == null) {
            return $this->createSubAccount($request_body, $relative_url);
        } else {
            return $this->updateSubAccount($request_body, $subaccount_id, $relative_url);
        }
    }

    public function listSubAccounts($query_params = [])
    {
        $relative_url = "/subaccount";
        $query_params = $this->adaptBodyParamsToPaystackAPI($query_params);
        $this->httpResponse = $this->httpClient->get($relative_url, $this->getRequestOptionsForPaystack($query_params));
        return @$this->getResponseBodyAsArray()['data'] ?? [];
    }

    public function fetchSubAccount($subaccount_id)
    {
        $relative_url = "/subaccount" . "/" . $subaccount_id;
        $this->httpResponse = $this->httpClient->get($relative_url, $this->getRequestOptionsForPaystack());
        return @$this->getResponseBodyAsArray()['data'] ?? [];
    }


    protected function adaptBodyParamsToPaystackAPI($request_body)
    {
        $paystack_params = $this->getPaystackParams();
        $paystack_request_body = $this->adaptBodyParamsToAPI($request_body, $paystack_params);

        //paystack works with amount in kobo
        if (isset($paystack_request_body['naira_amount']) && !isset($paystack_request_body['amount'])) {
            $paystack_request_body['amount'] = $paystack_request_body['naira_amount'] * 100;
            unset($paystack_request_body['naira_amount']);
        }

        return $paystack_request_body;
    }

    private function getPaystackParams()
    {
        return [
            "customer_email" => "email"
        ];
    }

    /**
     * @param $request_body
     * @return array
     */
    private function getPostRequestOptionsForPaystack($request_body = []): array
    {
        return [
            "headers" => [
                'authorization' => 'Bearer ' . $this->secretKey,
                'cache-control' => 'no-cache'
            ],
            "http_errors" => $this->httpExceptions,
            "json" => $request_body
        ];
    }

    private function getRequestOptionsForPaystack($query_params = []): array
    {
        return [
            "headers" => [
                'authorization' => 'Bearer ' . $this->secretKey,
            ],
            "http_errors" => $this->httpExceptions,
            "query" => $query_params
        ];
    }

    /**
     * @param $request_body
     * @param string $relative_url
     * @return int|null
     * @throws \Metav\NgPayments\Exceptions\InvalidRequestBodyException
     */
    private function createPlan($request_body, string $relative_url)
    {
        $this->validateRequestBodyHasRequiredParams($request_body, ['name', 'amount', 'interval']);
        $request_options = $this->getPostRequestOptionsForPaystack($request_body);
        $this->httpResponse = $this->httpClient->post($relative_url, $request_options);
        return @$this->getResponseBodyAsArray()['data']['id'] ?? null;
    }

    /**
     * @param $request_body
     * @param $plan_id
     * @param string $relative_url
     * @return mixed
     */
    private function updatePlan($request_body, $plan_id, string $relative_url)
    {
        $relative_url .= "/" . $plan_id;
        $request_options = $this->getPostRequestOptionsForPaystack($request_body);
        $this->httpResponse = $this->httpClient->put($relative_url, $request_options);

        if ($this->getResponseBodyAsArray()["status"] == true) {
            return $plan_id;
        }
        return null;
    }

    private function createSubAccount(array $request_body, string $relative_url)
    {
        $this->validateRequestBodyHasRequiredParams(
            $request_body,
            ['business_name', 'settlement_bank', 'account_number', 'percentage_charge']
        );
        $request_options = $this->getPostRequestOptionsForPaystack($request_body);
        $this->httpResponse = $this->httpClient->post($relative_url, $request_options);
        return @$this->getResponseBodyAsArray()['data']['id'] ?? null;
    }

    /**
     * @param $request_body
     * @param $subaccount_id
     * @param string $relative_url
     * @return mixed
     */
    private function updateSubAccount($request_body, $subaccount_id, string $relative_url)
    {
        $relative_url .= "/" . $subaccount_id;
        $request_options = $this->getPostRequestOptionsForPaystack($request_body);
        $this->httpResponse = $this->httpClient->put($relative_url, $request_options);
        if ($this->getResponseBodyAsArray()["status"] == true) {
            return $subaccount_id;
        }
        return null;
    }

}
