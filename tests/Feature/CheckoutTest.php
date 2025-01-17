<?php

namespace Tests\Feature;

use Tests\TestCase;
use Carbon\Carbon;


class CheckoutTest extends TestCase
{
    private $products = [
        'FR1' => ['code' => 'FR1', 'name' => 'Fruit tea', 'price' => 3.11],
        'SR1' => ['code' => 'SR1', 'name' => 'Strawberries', 'price' => 5.00],
        'CF1' => ['code' => 'CF1', 'name' => 'Coffee', 'price' => 11.23],
    ];

    private $rules = [
        'FR1' => ['rule_name' => 'buy_one_get_one', 'rule_details' => []],
        'SR1' => [
            'rule_name' => 'bulk_discount',
            'rule_details' => ['min_quantity' => 3, 'discount_price' => 4.50],
        ],
    ];

    public function test_single_item()
    {
        $checkout = $this->createCheckout();

        $checkout->scan('FR1');
        $this->assertEquals(3.11, $checkout->total());
    }

    public function test_buy_one_get_one_free()
    {
        $checkout = $this->createCheckout();

        $checkout->scan('FR1');
        $checkout->scan('FR1');
        $this->assertEquals(3.11, $checkout->total());
    }

    public function test_bulk_discount()
    {
        $checkout = $this->createCheckout();

        $checkout->scan('SR1');
        $checkout->scan('SR1');
        $checkout->scan('SR1');
        $this->assertEquals(13.50, $checkout->total());
    }

    public function test_mixed_basket()
    {
        $checkout = $this->createCheckout();

        $checkout->scan('FR1');
        $checkout->scan('SR1');
        $checkout->scan('FR1');
        $checkout->scan('FR1');
        $checkout->scan('CF1');
        $this->assertEquals(22.45, $checkout->total());
    }

    private function createCheckout()
    {
        $checkout = new \App\Services\Checkout($this->rules);
        $checkout->loadProducts($this->products);

        return $checkout;
    }

    public function test_rule_with_date_restrictions()
    {
        $checkout = $this->createCheckout();
    
        $rule = [
            'rule_name' => 'bulk_discount',
            'rule_details' => ['min_quantity' => 3, 'discount_price' => 4.50],
            'active' => 1,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'days' => null,
        ];
    
        $isRuleActive = $this->invokePrivateMethod($checkout, 'isRuleActive', [$rule]);
        $this->assertTrue($isRuleActive);
    
        $rule['start_date'] = '2030-01-01';
        $isRuleActive = $this->invokePrivateMethod($checkout, 'isRuleActive', [$rule]);
        $this->assertFalse($isRuleActive);
    }
    
    /**
     * Helper to invoke private or protected methods.
     *
     * @param object $object The instance of the class.
     * @param string $methodName The private/protected method name.
     * @param array $parameters Parameters to pass to the method.
     * @return mixed The result of the method call.
     */
    private function invokePrivateMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
    
        return $method->invokeArgs($object, $parameters);
    }

    public function test_combination_of_rules()
{
    $checkout = $this->createCheckout();

    $checkout->scan('FR1'); // Buy one get one free
    $checkout->scan('FR1');
    $checkout->scan('SR1'); // Bulk discount
    $checkout->scan('SR1');
    $checkout->scan('SR1');

    $this->assertEquals(16.61, $checkout->total());
}

public function test_inactive_rule_is_ignored()
{
    $checkout = $this->createCheckout();

    $rule = [
        'rule_name' => 'bulk_discount',
        'rule_details' => ['min_quantity' => 3, 'discount_price' => 4.50],
        'active' => 0, // Rule is inactive
        'start_date' => null,
        'end_date' => null,
        'days' => null,
    ];

    $isRuleActive = $this->invokePrivateMethod($checkout, 'isRuleActive', [$rule]);
    $this->assertFalse($isRuleActive);
}


public function test_rule_with_day_restrictions()
{
    $checkout = $this->createCheckout();

    $rule = [
        'rule_name' => 'bulk_discount',
        'rule_details' => ['min_quantity' => 3, 'discount_price' => 4.50],
        'active' => 1,
        'start_date' => null,
        'end_date' => null,
        'days'  => '["Monday","Wednesday"]',
    ];

    Carbon::setTestNow(Carbon::parse('next Monday'));
    dump(Carbon::now()->format('l')); 
    $this->assertSame('Monday', Carbon::now()->format('l'));

    $isRuleActive = $this->invokePrivateMethod($checkout, 'isRuleActive', [$rule]);
    $this->assertTrue($isRuleActive);

    Carbon::setTestNow(Carbon::parse('next Friday'));
    dump(Carbon::now()->format('l')); 

    $isRuleActive = $this->invokePrivateMethod($checkout, 'isRuleActive', [$rule]);
    $this->assertFalse($isRuleActive);

    Carbon::setTestNow();
}


public function test_scan_non_existent_product()
{
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage("Product not found: NON_EXISTENT");

    $checkout = $this->createCheckout();
    $checkout->scan('NON_EXISTENT');
}

}
