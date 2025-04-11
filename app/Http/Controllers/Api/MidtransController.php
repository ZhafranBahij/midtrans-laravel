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
use Midtrans\Notification;
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
                    'order_id' => $transaction->id,
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
            // {
            //     "token": "305285d9-72dc-4207-873d-1fb223e22fb0",
            //     "redirect_url": "https://app.sandbox.midtrans.com/snap/v4/redirection/305285d9-72dc-4207-873d-1fb223e22fb0"
            //   }
            // return $params;
            $payment = \Midtrans\Snap::createTransaction($params);

            // Redirect to Snap Payment Page
            return $payment->redirect_url;
            // return response()->json([
            //     'status_code' => 200,
            //     'message' => 'payment url created successfully',
            //     'data' =>  $payment->redirect_url,
            // ], 200);
        }
        catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    // {"order_id":"1743557812","status_code":"201","transaction_status":"pending","action":"back"}
    // {"order_id":"18830981","status_code":"200","transaction_status":"settlement"}
    public function callback(Request $request)
    {
        try {
            
            /**
             * GET TRANSACTION
             */
            $transaction = Transaction::find($request->order_id);

            /**
             * UPDATE TRANSACTION STATUS
             */
            $transaction->update([
               'status' => $request->transaction_status,
            ]);

            return response()->json([
                'status_code' => 200,
                'message' => 'Transaction update successfully',
                'data' => $transaction->toArray(),
            ], 200);

        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function notification(Request $request)
    {
        // Set your Merchant Server Key
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
        \Midtrans\Config::$isProduction = false;
        // Set sanitization on (default)
        \Midtrans\Config::$isSanitized = true;
        // Set 3DS transaction for credit card to true
        \Midtrans\Config::$is3ds = true;

        try {
            $notif = new Notification();
        }
        catch (\Exception $e) {
            exit($e->getMessage());
        }

        /**
         * GET NOTIFICATION DATA
         */
        $notif = $notif->getResponse();
        $status = $notif->transaction_status;
        $type = $notif->payment_type;
        $order_id = $notif->order_id;
        $fraud = $notif->fraud_status;

        /**
         * GET & UPDATE TRANSACTION
         */
        $transaction = Transaction::find($order_id);
        $transaction->update([
            'status' => $status,
        ]);

        if ($status == 'capture') {
            // For credit card transaction, we need to check whether transaction is challenge by FDS or not
            if ($type == 'credit_card') {
                if ($fraud == 'challenge') {
                    // TODO set payment status in merchant's database to 'Challenge by FDS'
                    // TODO merchant should decide whether this transaction is authorized or not in MAP
                    echo "Transaction order_id: " . $order_id ." is challenged by FDS";
                } else {
                    // TODO set payment status in merchant's database to 'Success'

                    echo "Transaction order_id: " . $order_id ." successfully captured using " . $type;
                }
            }
        } else if ($status == 'settlement') {
            // TODO set payment status in merchant's database to 'Settlement'
            echo "Transaction order_id: " . $order_id ." successfully transfered using " . $type;
        } else if ($status == 'pending') {
            // TODO set payment status in merchant's database to 'Pending'
            echo "Waiting customer to finish transaction order_id: " . $order_id . " using " . $type;
        } else if ($status == 'deny') {
            // TODO set payment status in merchant's database to 'Denied'
            echo "Payment using " . $type . " for transaction order_id: " . $order_id . " is denied.";
        } else if ($status == 'expire') {
            // TODO set payment status in merchant's database to 'expire'
            echo "Payment using " . $type . " for transaction order_id: " . $order_id . " is expired.";
        } else if ($status == 'cancel') {
            // TODO set payment status in merchant's database to 'Denied'
            echo "Payment using " . $type . " for transaction order_id: " . $order_id . " is canceled.";
        }
    }
}
