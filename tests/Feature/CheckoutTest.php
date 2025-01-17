<?php

namespace Tests\Feature;

use Tests\TestCase;
use Carbon\Carbon;
use App\Services\Checkout;

/**
 * Test cases for the Checkout service using Enum-based pricing rules.
 */
class CheckoutTest extends TestCase
{
    /**
     * Sample products used in tests.
     *
     * @var array
     */
    private array $products = [
        'FR1' => ['code' => 'FR1', 'price' => 3.11],
        'SR1' => ['code' => 'SR1', 'price' => 5.00],
        'CF1' => ['code' => 'CF1', 'price' => 11.23],
    ];

    /**
     * Sample rules used in tests.
     *
     * @var array
     */
    private array $rules = [
        'FR1' => [
            'rule_name'    => 'buy_one_get_one',
            'rule_details' => []
        ],
        'SR1' => [
            'rule_name'    => 'bulk_discount',
            'rule_details' => ['min_quantity' => 3, 'discount_price' => 4.50]
        ],
    ];

    private function invokePrivateMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Tests scanning a single item without any discount.
     *
     * Expected total: 3.11
     */
    public function test_single_item()
    {
        $checkout = $this->createCheckout();

        $checkout->scan('FR1');
        $this->assertEquals(3.11, $checkout->total());
    }

    /**
     * Tests the buy_one_get_one rule for product FR1.
     *
     * Scanning FR1 twice should cost the price of one.
     * Expected total: 3.11
     */
    public function test_buy_one_get_one_free()
    {
        $checkout = $this->createCheckout();

        $checkout->scan('FR1');
        $checkout->scan('FR1');
        $this->assertEquals(3.11, $checkout->total());
    }

    /**
     * Tests the bulk_discount rule for SR1.
     *
     * Buying three SR1 items should apply the discount price (4.50 each).
     * Expected total: 13.50
     */
    public function test_bulk_discount()
    {
        $checkout = $this->createCheckout();

        $checkout->scan('SR1');
        $checkout->scan('SR1');
        $checkout->scan('SR1');
        $this->assertEquals(13.50, $checkout->total());
    }

    /**
     * Tests scanning multiple different items, mixing rules.
     *
     * - FR1 => buy one get one free
     * - SR1 => bulk discount
     *
     * Example scenario:
     * - 2 x FR1 = 3.11 (BOGO applies)
     * - 3 x SR1 = 13.50 (bulk discount)
     * - plus maybe CF1 = 11.23 if scanned
     */
    public function test_mixed_basket()
    {
        $checkout = $this->createCheckout();

        // e.g. 2x FR1, 3x SR1
        $checkout->scan('FR1');
        $checkout->scan('FR1');
        $checkout->scan('SR1');
        $checkout->scan('SR1');
        $checkout->scan('SR1');

        // 2 FR1 => 3.11
        // 3 SR1 => 13.50
        // total = 16.61
        // (If you add CF1 => 11.23, then total would be 27.84)
        $this->assertEquals(16.61, $checkout->total());
    }

    /**
     * Tests that an exception is thrown for a non-existent product.
     */
    public function test_scan_non_existent_product()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Product not found: NON_EXISTENT");

        $checkout = $this->createCheckout();
        $checkout->scan('NON_EXISTENT');
    }

    /**
     * Helper method to initialize Checkout with sample $rules and $products.
     *
     * @return Checkout
     */
    private function createCheckout(): Checkout
    {
        $checkout = new Checkout($this->rules);
        $checkout->loadProducts($this->products);

        return $checkout;
    }


    /**
     * Tests an active rule with no date and no day restrictions.
     */
    public function testIsRuleActiveNoDateNoDays()
    {
        $checkout = $this->createCheckout();
        $rule = [
            'rule_name' => 'bulk_discount',
            'rule_details' => ['min_quantity' => 3, 'discount_price' => 4.50],
            'active' => 1,
            'start_date' => null,
            'end_date' => null,
            'days' => null,
        ];

        Carbon::setTestNow(Carbon::parse('2025-01-01'));
        $isActive = $this->invokePrivateMethod($checkout, 'isRuleActive', [$rule]);
        $this->assertTrue($isActive);
        Carbon::setTestNow();
    }

    /**
     * Tests a rule that is explicitly inactive.
     */
    public function testIsRuleActiveInactive()
    {
        $checkout = $this->createCheckout();
        $rule = [
            'rule_name' => 'bulk_discount',
            'rule_details' => [],
            'active' => 0,
            'start_date' => null,
            'end_date' => null,
            'days' => null,
        ];

        $isActive = $this->invokePrivateMethod($checkout, 'isRuleActive', [$rule]);
        $this->assertFalse($isActive);
    }

    /**
     * Tests a rule that has a future start date.
     */
    public function testIsRuleActiveBeforeStartDate()
    {
        $checkout = $this->createCheckout();
        $rule = [
            'rule_name' => 'bulk_discount',
            'rule_details' => [],
            'active' => 1,
            'start_date' => '2030-01-01',
            'end_date' => null,
            'days' => null,
        ];

        Carbon::setTestNow(Carbon::parse('2025-01-01'));
        $isActive = $this->invokePrivateMethod($checkout, 'isRuleActive', [$rule]);
        $this->assertFalse($isActive);
        Carbon::setTestNow();
    }

    /**
     * Tests a rule that has an expired end date.
     */
    public function testIsRuleActiveAfterEndDate()
    {
        $checkout = $this->createCheckout();
        $rule = [
            'rule_name' => 'bulk_discount',
            'rule_details' => [],
            'active' => 1,
            'start_date' => '2020-01-01',
            'end_date' => '2020-12-31',
            'days' => null,
        ];

        Carbon::setTestNow(Carbon::parse('2025-01-01'));
        $isActive = $this->invokePrivateMethod($checkout, 'isRuleActive', [$rule]);
        $this->assertFalse($isActive);
        Carbon::setTestNow();
    }

    /**
     * Tests a rule restricted to Monday and Wednesday, simulating Monday.
     */
    public function testIsRuleActiveDayRestrictionMatches()
    {
        $checkout = $this->createCheckout();
        $rule = [
            'rule_name' => 'bulk_discount',
            'rule_details' => [],
            'active' => 1,
            'start_date' => null,
            'end_date' => null,
            'days' => json_encode(['Monday', 'Wednesday']),
        ];

        Carbon::setTestNow(Carbon::parse('next Monday'));
        $isActive = $this->invokePrivateMethod($checkout, 'isRuleActive', [$rule]);
        $this->assertTrue($isActive);
        Carbon::setTestNow();
    }

    /**
     * Tests a rule restricted to Monday and Wednesday, simulating Friday.
     */
    public function testIsRuleActiveDayRestrictionDoesNotMatch()
    {
        $checkout = $this->createCheckout();
        $rule = [
            'rule_name' => 'bulk_discount',
            'rule_details' => [],
            'active' => 1,
            'start_date' => null,
            'end_date' => null,
            'days' => json_encode(['Monday', 'Wednesday']),
        ];

        Carbon::setTestNow(Carbon::parse('next Friday'));
        $isActive = $this->invokePrivateMethod($checkout, 'isRuleActive', [$rule]);
        $this->assertFalse($isActive);
        Carbon::setTestNow();
    }
}
