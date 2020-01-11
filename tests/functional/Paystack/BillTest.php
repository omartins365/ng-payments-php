<?php

namespace Kofi\NgPayments\Tests\functional\Paystack;

use Kofi\NgPayments\Bill;
use Kofi\NgPayments\Exceptions\FailedTransactionException;
use Kofi\NgPayments\PaymentProviders\PaymentProviderFactory;
use Kofi\NgPayments\Plan;
use PHPUnit\Framework\TestCase;

class BillTest extends TestCase
{

    public function testCharge()
    {
        $bill = new Bill("customer@email.com", 5000);

        $payment_provider = &$bill->getPaymentProvider();
        $payment_provider->disableTransactionExceptions();
        $reference = $bill->charge()->getPaymentReference();
        $payment_page_url = $bill->getPaymentPageUrl();
        $this->assertNotNull($reference);
        $this->assertNotNull($payment_page_url);

        PaymentProviderFactory::disableTransactionExceptions();
        $is_valid = Bill::isPaymentValid($reference, 5000);
        $this->assertFalse($is_valid);
        $authorization_code = Bill::getPaymentAuthorizationCode($reference, 5000);
        $this->assertNull($authorization_code);

        PaymentProviderFactory::enableTransactionExceptions();
        $this->expectException(FailedTransactionException::class);
        Bill::isPaymentValid($reference, 5000);
    }

    public function testSubscribe()
    {
        $plan = new Plan("Test Plan", 4000, "daily");
        $plan->save();
        $bill = new Bill("customer@email.com", 5000);
        $payment_provider = &$bill->getPaymentProvider();
        $payment_provider->disableTransactionExceptions();
        $reference = $bill->subscribe($plan->plan_code)->getPaymentReference();
        $payment_page_url = $bill->getPaymentPageUrl();
        $this->assertNotNull($reference);
        $this->assertNotNull($payment_page_url);

        PaymentProviderFactory::disableTransactionExceptions();
        $is_valid = Bill::isPaymentValid($reference, 4000);
        $this->assertFalse($is_valid);
        $authorization_code = Bill::getPaymentAuthorizationCode($reference, 4000);
        $this->assertNull($authorization_code);

        PaymentProviderFactory::enableTransactionExceptions();
        $this->expectException(FailedTransactionException::class);
        Bill::isPaymentValid($reference, 4000);
    }
}
