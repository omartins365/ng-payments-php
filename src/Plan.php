<?php


namespace Metav\NgPayments;

use Metav\NgPayments\PaymentProviders\PaymentProviderFactory;
use Metav\NgPayments\Traits\AttributesTrait;

class Plan
{
    use AttributesTrait;

    protected $paymentProvider = null;

    public function __construct($plan_name = null, $amount_in_naira = null, $plan_interval = null)
    {
        $this->paymentProvider = PaymentProviderFactory::getPaymentProvider();
        if (func_num_args() == 1 && is_array(func_get_arg(0))) {
            $this->attributes = func_get_arg(0);
            return;
        }

        $this->attributes["name"] = $plan_name;
        $this->attributes["naira_amount"] = $amount_in_naira;
        $this->attributes["interval"] = $plan_interval;
    }

    public function save()
    {
        $this->id = $this->paymentProvider->savePlan($this->attributes);
        return $this->id;
    }

    public static function fetchAll($query_params = null)
    {
        $plans_data = PaymentProviderFactory::getPaymentProvider()->fetchAllPlans($query_params);
        if ($plans_data == []) {
            return $plans_data;
        }

        $plans = [];
        foreach ($plans_data as $plan_data) {
            $plans[] = new Plan($plan_data);
        }

        return $plans;
    }

    public static function fetch($plan_id)
    {
        $plan_details = PaymentProviderFactory::getPaymentProvider()->fetchPlan($plan_id);
        return new Plan($plan_details);
    }
}
