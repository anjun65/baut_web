<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use illuminate\Support\Facades\Auth;

use App\Cart;
use App\Transaction;
use App\TransactionDetail;

use Exception;

use Midtrans\Snap;
use Midtrans\Config;
use phpDocumentor\Reflection\PseudoTypes\True_;

class CheckoutController extends Controller
{
    public function process(Request $request)
    {
        $user = Auth::user();
        $user->update($request->except('total_price'));

        //proses checkout

        $code = 'STORE-'. mt_rand(000000,999999);
        $carts = Cart::with(['product','user'])
                    ->where('users_id', Auth::user()->id)
                    ->get();
        
        $transaction = Transaction::create([
            'users_id' => Auth::user()->id,
            'inscurance_price' => 0,
            'shipping_price' => 0,
            'total_price' => (int) $request->total_price,
            'transaction_status' => 'PENDING',
            'code' => $code,
        ]);

        foreach ($carts as $cart){
            $trx = 'TRX-' . mt_rand(000000,999999);
            
            TransactionDetail::create([
                'transactions_id' =>$transaction->id,
                'products_id' => $cart->product->id,
                'price' => $cart->product->price,
                'shipping_status' => 'PENDING',
                'resi' => '',
                'code' => $trx,
            ]);

        }

        // delete cart data
        Cart::where('users_id', Auth::user()->id)->delete();

        //konfigurasi midtrans

        Config::$serverKey = "SB-Mid-server-M6cee59uKtcIhozORsxZQrCX";
        Config::$isProduction = false;
        Config::$isSanitized = true;
        Config::$is3ds = true;

        //buat array dikirim midtrans

        $midtrans = [
            'transaction_details' => [
                'order_id' => $code,
                'gross_amount' => (int) $request->total_price,
            ],
            'customer_details' => [
                'first_name' => Auth::user()->name,
                'email' => Auth::user()->email,
            ],
            'enabled_payment' => [
                'gopay', 'permata_va', 'bank_transfer'
            ],

            'vtweb' => []

            ];

            try {
                // Get Snap Payment Page URL
                $paymentUrl = Snap::createTransaction($midtrans)->redirect_url;
                
                // Redirect to Snap Payment Page
                return redirect($paymentUrl);
            }
            catch (Exception $e) {
                echo $e->getMessage();
            }
            
    }

    public function callback(Request $request)
    {

    }
}
