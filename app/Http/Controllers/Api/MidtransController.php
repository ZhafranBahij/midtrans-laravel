<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionSnapRedirectRequest;
use App\Models\Note;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Midtrans\Snap;
use SnapBi\SnapBi;

class MidtransController extends Controller
{

    /**
     * Undocumented function
     *
     */
    public function createTransactionSnapRedirect(TransactionSnapRedirectRequest $request)
    {
        try {
            $validated = $request->validated();

            // Set your Merchant Server Key
            \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
            // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
            \Midtrans\Config::$isProduction = false;
            // Set sanitization on (default)
            \Midtrans\Config::$isSanitized = true;
            // Set 3DS transaction for credit card to true
            \Midtrans\Config::$is3ds = true;

            $params = DB::transaction(function () use ($validated) {
                /**
                 * FIND OR CREATE USER
                 */
                $user = User::firstOrCreate([
                    'email' => $validated['user']['email'],
                ], [
                    'name' => $validated['user']['name'],
                    'phone' => $validated['user']['mobile_number'],
                    'password' => 12345678
                ]);
                
                /**
                 * CREATE TRANSACTION IN DATABASE
                 */
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                ]);

                /**
                 * User array to insert in Snap Params
                 */

                // Fix name
                $first_name = explode(' ', $user->name)[0];
                $last_name = explode(' ', $user->name)[1] ?? '';

                $customer_details = array(
                    'first_name'    => $first_name,
                    'last_name'     => $last_name,
                    'email'         => $user->email,
                    'phone'         => $user->phone,
                );

                /**
                 * GET PRODUCTS
                 */
                $item_details = [];
                foreach ($validated['transactions'] as $item) {

                    // Find Product
                    $product = Product::find($item['product_id']);

                    // Create transaction details for database
                    $transaction->transactionDetails()->create([
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'price' => $product->price,
                        'subtotal' => $product->price * $item['quantity'],
                    ]);

                    // Item details array to insert in Snap Params
                    $item_details[] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'quantity' => $item['quantity'],
                        'price' => $product->price,
                    ];
                }

                /**
                 * Transaction array to insert in Snap Params
                 */
                $transaction_details = array(
                    'order_id' => rand(),
                    'gross_amount' => $transaction->transactionDetails->sum('subtotal'), // no decimal allowed for creditcard
                );

                // Fill SNAP API parameter
                return array(
                    'transaction_details' => $transaction_details,
                    'customer_details' => $customer_details,
                    'item_details' => $item_details,
                );
            });
            
            // Get Snap Payment Page URL
            $paymentUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;

            // Redirect to Snap Payment Page
            return $paymentUrl;
        }
        catch (Exception $e) {
          echo $e->getMessage();
        }
    }

    /**
     * Undocumented function
     *
     */
    public function createTransactionSnapRedirect2()
    {
        // Set your Merchant Server Key
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
        \Midtrans\Config::$isProduction = false;
        // Set sanitization on (default)
        \Midtrans\Config::$isSanitized = true;
        // Set 3DS transaction for credit card to true
        \Midtrans\Config::$is3ds = true;

        // Required
        $transaction_details = array(
            'order_id' => rand(),
            'gross_amount' => 145000, // no decimal allowed for creditcard
        );

        // Optional
        $item1_details = array(
            'id' => 'a1',
            'price' => 50000,
            'quantity' => 2,
            'name' => "Apple"
        );

        // Optional
        $item2_details = array(
            'id' => 'a2',
            'price' => 45000,
            'quantity' => 1,
            'name' => "Orange"
        );

        // Optional
        $item_details = array ($item1_details, $item2_details);

        // Optional
        $billing_address = array(
            'first_name'    => "Zhafran",
            'last_name'     => "Bahij",
            'address'       => "Mangga 20",
            'city'          => "Jakarta",
            'postal_code'   => "16602",
            'phone'         => "081122334455",
            'country_code'  => 'IDN'
        );

        // Optional
        $shipping_address = array(
            'first_name'    => "Opet",
            'last_name'     => "Papashi",
            'address'       => "Manggis 90",
            'city'          => "Jakarta",
            'postal_code'   => "16601",
            'phone'         => "08113366345",
            'country_code'  => 'IDN'
        );

        // Optional
        $customer_details = array(
            'first_name'    => "Zhafran",
            'last_name'     => "Bahij",
            'email'         => "fran@bahij.com",
            'phone'         => "081122334455",
            'billing_address'  => $billing_address,
            'shipping_address' => $shipping_address
        );

        // Fill SNAP API parameter
        $params = array(
            'transaction_details' => $transaction_details,
            'customer_details' => $customer_details,
            'item_details' => $item_details,
        );

        try {
            // Get Snap Payment Page URL
            $paymentUrl = Snap::createTransaction($params)->redirect_url;
        
            // Redirect to Snap Payment Page
            return $paymentUrl;
        }
        catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function callback(Request $request)
    {
        try {
            $note = Note::create([
                'title' => now().' Transaction Notification'.' Rhytm of Good',
            ]);

            return response()->json([
                'status_code' => 200,
                'message' => 'Note created successfully',
                'data' => $note
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status_code' => $th->getCode(),
                'message' => 'Something went wrong',
                'error' => $th->getMessage()
            ], $th->getCode());
        }
    }
}
