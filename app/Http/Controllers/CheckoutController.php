<?php

namespace App\Http\Controllers;

use App\Services\Checkout;

class CheckoutController extends Controller
{
    public function index()
    {
        $checkout = new Checkout(config('pricing_rules'));

        $checkout->scan('FR1');
        $checkout->scan('SR1');
        $checkout->scan('FR1');
        $checkout->scan('FR1');
        $checkout->scan('CF1');

        return response()->json([
            'total' => $checkout->total(),
        ]);
    }
}
