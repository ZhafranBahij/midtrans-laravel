<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Exception;
use Illuminate\Http\Request;
use Midtrans\Snap;
use SnapBi\SnapBi;

class MidtransController extends Controller
{

    /**
     * Undocumented function
     *
     */
    public function createTransactionSnapRedirect(Request $request)
    {
        // Set your Merchant Server Key
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
        \Midtrans\Config::$isProduction = false;
        // Set sanitization on (default)
        \Midtrans\Config::$isSanitized = true;
        // Set 3DS transaction for credit card to true
        \Midtrans\Config::$is3ds = true;

        $params = array(
            'transaction_details' => array(
                'order_id' => rand(),
                'gross_amount' => 10000,
            )
        );
        
        try {
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
