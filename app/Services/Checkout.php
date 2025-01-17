<?php

namespace App\Services;

use Carbon\Carbon;
use App\Enums\PricingRuleType;

/**
 * The Checkout class manages the shopping cart and applies pricing rules using Enum.
 */
class Checkout
{
    /**
     * @var array Products available for purchase.
     */
    private array $products = [];

    /**
     * @var array Pricing rules for the products.
     */
    private array $rules = [];

    /**
     * @var array The shopping cart containing scanned product codes.
     */
    private array $cart = [];

    /**
     * Constructor for the Checkout class.
     *
     * @param array $pricing_rules The pricing rules to be applied.
     */
    public function __construct(array $pricing_rules)
    {
        $this->rules = $pricing_rules;
    }

    /**
     * Adds a product to the shopping cart.
     *
     * @param string $productCode The product code to be added.
     * @throws \Exception If the product code is not found in the product list.
     * @return void
     */
    public function scan(string $productCode): void
    {
        if (!isset($this->products[$productCode])) {
            throw new \Exception("Product not found: $productCode");
        }
        $this->cart[] = $productCode;
    }

    /**
     * Calculates the total cost of the shopping cart.
     *
     * @return float The total price after applying all applicable pricing rules.
     */
    public function total(): float
    {
        $total = 0.0;
        $counts = array_count_values($this->cart);

        foreach ($counts as $productCode => $quantity) {
            $price = $this->products[$productCode]['price'];
            $rule = $this->rules[$productCode] ?? null;

            if ($rule) {
                $total += $this->applyRule($rule, $quantity, $price);
            } else {
                $total += $quantity * $price;
            }
        }

        return round($total, 2);
    }

    /**
     * Applies the pricing rule to a product by dispatching to the appropriate Enum case.
     *
     * @param array $rule An associative array containing 'rule_name' and 'rule_details'.
     * @param int $quantity The quantity of the product in the cart.
     * @param float $price The base price of the product.
     * @throws \Exception If the rule_name does not match any known Enum case.
     * @return float The calculated price after applying the rule.
     */
    private function applyRule(array $rule, int $quantity, float $price): float
    {
        $ruleName = $rule['rule_name'];
        $ruleDetails = $rule['rule_details'] ?? [];

        try {
            // Convert string to Enum
            $ruleType = PricingRuleType::from($ruleName);
        } catch (\ValueError $e) {
            // If the string doesn't match any Enum case, throw an exception
            throw new \Exception("Unknown rule: {$ruleName}");
        }

        // Delegate logic to the Enum's apply() method
        return $ruleType->apply($quantity, $price, $ruleDetails);
    }

    /**
     * Loads the product data into the Checkout system.
     *
     * @param array $products An array of products with their details.
     * @return void
     */
    public function loadProducts(array $products): void
    {
        $this->products = $products;
    }

    /**
     * Loads pricing rules from the database, considering their activation status, dates, and days of the week.
     *
     * @param \PDO $db A PDO instance for database connection.
     * @return void
     */
    public function loadRulesFromDatabase(\PDO $db): void
    {
        $query = $db->query("SELECT * FROM pricing_rules WHERE active = 1");
        $rules = [];

        while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
            if (!$this->isRuleActive($row)) {
                continue;
            }

            $rules[$row['product_code']] = [
                'rule_name'    => $row['rule_name'],
                'rule_details' => json_decode($row['rule_details'], true),
            ];
        }

        $this->rules = $rules;
    }

    /**
     * Checks if a pricing rule is active based on its start/end dates and days of the week.
     *
     * @param array $rule The rule data from the database.
     * @return bool True if the rule is active, false otherwise.
     */
    private function isRuleActive(array $rule): bool
    {
        $currentDate = Carbon::now();
        $currentDay  = $currentDate->format('l');

        if (isset($rule['active']) && !$rule['active']) {
            return false;
        }

        if (!empty($rule['start_date']) && $currentDate < new \DateTime($rule['start_date'])) {
            return false;
        }

        if (!empty($rule['end_date']) && $currentDate > new \DateTime($rule['end_date'])) {
            return false;
        }

        if (!empty($rule['days'])) {
            $days = json_decode($rule['days'], true);
            if (!in_array($currentDay, $days)) {
                return false;
            }
        }

        return true;
    }
}
