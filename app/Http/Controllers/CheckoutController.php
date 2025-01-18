<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Checkout;
use App\Repository\ProductRepository;
use App\Repository\PricingRulesRepository;

class CheckoutController extends Controller
{
    public function checkoutCart(Request $request)
    {
        $productCodesParam = $request->query('products');

        if (!$productCodesParam) {
            return response()->json([
                'error' => 'No product codes provided. Use ?products=FR1,SR1,...'
            ], 400);
        }

        $productCodes = array_map('trim', explode(',', $productCodesParam));

        if (empty($productCodes)) {
            return response()->json([
                'error' => 'No valid product codes found.'
            ], 400);
        }

        $pdo = new \PDO('mysql:host=mysql;dbname=laravel', 'sail', 'password');

        $productRepo = new ProductRepository();
        $rulesRepo = new PricingRulesRepository();

        $allProducts = $productRepo->getAllProducts($pdo);
        $activeRules = $rulesRepo->getActiveRules($pdo);

        $checkout = new Checkout($activeRules);
        $checkout->loadProducts($allProducts);

        foreach ($productCodes as $code) {
            try {
                $checkout->scan($code);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 400);
            }
        }

        $total = $checkout->total();

        return response()->json([
            'product_codes' => $productCodes,
            'total' => $total,
        ]);
    }
}
