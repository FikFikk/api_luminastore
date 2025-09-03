<?php

namespace App\Controllers;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\View\ArrayData;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\Requirements;
use App\Models\Order;
use App\Models\OrderItem;

class PaymentController extends Controller
{
    private static $allowed_actions = [
        'return',
        'success',
        'failed'
    ];

    private static $url_handlers = [
        'return' => 'return',
        'success/$ID' => 'success',
        'failed/$ID' => 'failed'
    ];

    /**
     * Handle return URL from Duitku
     * URL: /payment/return?merchantOrderId=ORDER-12-1755481132&resultCode=00&reference=DS2448825868TPLHVVFL44PN
     */
    public function return(HTTPRequest $request)
    {
        $merchantOrderId = $request->getVar('merchantOrderId');
        $resultCode = $request->getVar('resultCode');
        $reference = $request->getVar('reference');

        if (!$merchantOrderId) {
            return $this->renderErrorPage('Invalid payment reference');
        }

        // Cari order berdasarkan DuitkuReference
        $order = Order::get()->filter('DuitkuReference', $merchantOrderId)->first();

        if (!$order) {
            return $this->renderErrorPage('Order not found');
        }

        // Redirect ke halaman success atau failed berdasarkan resultCode
        if ($resultCode === '00') {
            return $this->redirect('/payment/success/' . $order->ID . '?ref=' . urlencode($reference));
        } else {
            return $this->redirect('/payment/failed/' . $order->ID . '?code=' . urlencode($resultCode));
        }
    }

    /**
     * Payment success page
     * URL: /payment/success/{order_id}
     */
    public function success(HTTPRequest $request)
    {
        $orderId = $request->param('ID');
        $reference = $request->getVar('ref');

        if (!$orderId) {
            // Sebaiknya arahkan ke halaman error yang lebih user-friendly
            return $this->httpError(400, 'Order ID is required');
        }

        $order = Order::get()->byID($orderId);
        if (!$order) {
            return $this->httpError(404, 'Order not found');
        }

        // Get order items with product details
        $items = [];
        $totalItems = 0;
        $subTotalCalc = 0; // Variabel untuk menghitung subtotal secara akurat

        foreach ($order->Items() as $item) {
            $product = $item->Product();
            $itemSubtotal = $item->Price * $item->Quantity;
            $subTotalCalc += $itemSubtotal;

            $imageSmall = null;
            // Pastikan relasi product dan image ada sebelum diakses
            if ($product && $product->Image()->exists()) {
                $imageSmall = $product->Image()->Fill(150, 150)->URL; // Gunakan .URL untuk mendapatkan link
            }

            $items[] = ArrayData::create([
                'ProductTitle' => $product ? $product->Title : '(Produk tidak ditemukan)',
                'ProductImage' => $imageSmall,
                'Quantity'     => $item->Quantity,
                'Price'        => number_format($item->Price, 0, ',', '.'),
                'Subtotal'     => number_format($itemSubtotal, 0, ',', '.')
            ]);

            $totalItems++; // Hitung jumlah item unik, bukan total kuantitas
        }

        // Get customer info
        $member = $order->Member();
        $address = $order->Address();

        // Siapkan semua data untuk dikirim ke template
        $data = ArrayData::create([
            'OrderID' => $order->ID,
            'OrderReference' => $order->DuitkuReference,
            'PaymentReference' => $reference,
            'OrderDate' => $order->Created,
            'PaymentStatus' => ucfirst($order->PaymentStatus), // Buat huruf depan jadi kapital
            'ShippingStatus' => ucfirst($order->ShippingStatus),
            'Items' => new ArrayList($items), // DIUBAH: Bungkus array dengan ArrayList agar lebih handal
            'TotalItems' => $totalItems,
            'SubTotal' => number_format($subTotalCalc, 0, ',', '.'), // Gunakan hasil kalkulasi akurat
            'ShippingCost' => number_format($order->ShippingCost, 0, ',', '.'),
            'TotalPrice' => number_format($order->TotalPrice, 0, ',', '.'),
            'Fee' => number_format($order->Fee, 0, ',', '.'),
            'Courier' => strtoupper($order->Courier),
            'Service' => $order->Service,
            'CustomerName' => $member->exists() ? $member->getName() : 'Tamu',
            'CustomerEmail' => $member->exists() ? $member->Email : '-',
            'CustomerPhone' => $address->exists() ? $address->PhoneNumber : '-', // Ambil dari alamat jika ada
            'ShippingAddress' => $address->exists() ? $address->getFullAddress() : 'Alamat tidak tersedia' // Asumsi ada method getFullAddress() di model Address
        ]);

        Requirements::css('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');

        return $this->customise($data)->renderWith('PaymentSuccess');
    }


    /**
     * Payment failed page
     * URL: /payment/failed/{order_id}
     */
    public function failed(HTTPRequest $request)
    {
        $orderId = $request->param('ID');
        $errorCode = $request->getVar('code');

        if (!$orderId) {
            return $this->renderErrorPage('Order ID is required');
        }

        $order = Order::get()->byID($orderId);
        if (!$order) {
            return $this->renderErrorPage('Order not found');
        }

        $errorMessages = [
            '01' => 'Payment was cancelled by user',
            '02' => 'Payment failed due to insufficient funds',
            '03' => 'Payment expired',
            '99' => 'General payment error'
        ];

        $errorMessage = $errorMessages[$errorCode] ?? 'Payment failed for unknown reason';

        $data = ArrayData::create([
            'OrderID' => $order->ID,
            'OrderReference' => $order->DuitkuReference,
            'ErrorCode' => $errorCode,
            'ErrorMessage' => $errorMessage,
            'TotalPrice' => number_format($order->TotalPrice, 0, ',', '.'),
            'OrderDate' => $order->Created
        ]);

        Requirements::css('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');

        return $this->customise($data)->renderWith('PaymentFailed');
    }

    /**
     * Render error page
     */
    private function renderErrorPage($message)
    {
        $data = ArrayData::create([
            'ErrorMessage' => $message
        ]);

        return $this->customise($data)->renderWith('PaymentError');
    }
}
