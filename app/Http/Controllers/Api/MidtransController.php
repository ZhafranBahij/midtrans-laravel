<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionSnapRedirectRequest;
use App\Models\Note;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
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
                 * FIND VOUCHER
                 */
                $voucher = Voucher::find($validated['voucher_id']);
                
                /**
                 * CREATE TRANSACTION IN DATABASE
                 */
                $transaction = Transaction::create([
                    'voucher_id' => $voucher->id ?? null,
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
                 * CALCULATE DISCOUNT
                 */
                $sum_subtotal = $transaction->transactionDetails->sum('subtotal');
                $transaction->update([
                    'total_before_discount' => $sum_subtotal,
                    'total' => (int) ($sum_subtotal * (1 - $voucher->discount_percentange / 100)),
                ]);

                /**
                 * Transaction array to insert in Snap Params
                 */
                $transaction_details = array(
                    'order_id' => $transaction->id,
                    'gross_amount' => $transaction->total , // no decimal allowed for creditcard
                );

                // Fill SNAP API parameter
                return array(
                    'transaction_details' => $transaction_details,
                    'customer_details' => $customer_details,
                    // 'item_details' => $item_details,
                );
            });

            // return $params;
            
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
            return response()->json([
                'status_code' => 400,
                'message' => 'Something went wrong',
                 'error' => $e->getMessage()
             ], 400);
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

    // {
    //     "transaction_time": "2020-01-09 18:27:19",
    //     "transaction_status": "capture",
    //     "transaction_id": "57d5293c-e65f-4a29-95e4-5959c3fa335b",
    //     "status_message": "midtrans payment notification",
    //     "status_code": "200",
    //     "signature_key": "16d6f84b2fb0468e2a9cf99a8ac4e5d803d42180347aaa70cb2a7abb13b5c6130458ca9c71956a962c0827637cd3bc7d40b21a8ae9fab12c7c3efe351b18d00a",
    //     "payment_type": "credit_card",
    //     "order_id": "Postman-1578568851",
    //     "merchant_id": "G141532850",
    //     "masked_card": "48111111-1114",
    //     "gross_amount": "10000.00",
    //     "fraud_status": "accept",
    //     "eci": "05",
    //     "currency": "IDR",
    //     "channel_response_message": "Approved",
    //     "channel_response_code": "00",
    //     "card_type": "credit",
    //     "bank": "bni",
    //     "approval_code": "1578569243927"
    // }
    public function notification(Request $request)
    {
        try {
            // Set your Merchant Server Key
            \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
            // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
            \Midtrans\Config::$isProduction = false;
            // Set sanitization on (default)
            \Midtrans\Config::$isSanitized = true;
            // Set 3DS transaction for credit card to true
            \Midtrans\Config::$is3ds = true;

            $notif = new Notification();

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
                'fraud_status' => $fraud ?? null,
                'midtrans_payment_method' => $type ?? null,
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
                $transaction->update([
                    'settlement_time' => now(),
                ]);
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
        catch (\Exception $e) {
            // return response()->json([
            //     'status_code' => $e->getCode(),
            //     'message' => 'Something went wrong',
            //      'error' => $e->getMessage()
            //  ], $e->getCode());
        }
    }
}
