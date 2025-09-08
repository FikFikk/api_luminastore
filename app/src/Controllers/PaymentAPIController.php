<?php

namespace App\Controllers;

use DateTime;
use DateInterval;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Address;
use App\Models\Product;
use App\Models\Variant;
use App\Models\APIClient;
use App\Models\AppsToken;
use App\Models\OrderItem;
use SilverStripe\Dev\Debug;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\SiteConfig\SiteConfig;

class PaymentAPIController extends BaseController
{
    private static $allowed_actions = [
        'list_orders',
        'create_order',
        'get_payment_methods',
        'callback',
        'check_status',
        'show_order',
        'get_detailed_payment_methods',
        'track_order',
        'calculate_shipping' // New endpoint
    ];

    // Konfigurasi Duitku
    private $merchantKey = '19fb4bce607511f15b207adb99272846';
    private $merchantCode = 'DS24488';
    private $apiUrl = 'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry'; // sandbox
    private $callbackUrl = 'https://admin-fikri-shop.jasamobileapp.com/api/payment/callback';
    private $returnUrl = 'https://admin-fikri-shop.jasamobileapp.com/payment/return';

    // private $rajaOngkirApiKey  = 'TJjGC1vo902905ba249532de9ghTh1yV';
    private $rajaOngkirApiKey  = '55debe1527da557278552dc3007fbbf3'; //ct Account
    // Updated API URL untuk district domestic cost
    private $rajaOngkirApiUrl  = 'https://rajaongkir.komerce.id/api/v1/calculate/district/domestic-cost';
    private $lastShippingErrorMessage = '';

    /**
     * Helper untuk membuat response JSON
     */
    protected function jsonResponse($data, $statusCode = 200)
    {
        $response = HTTPResponse::create(
            json_encode($data),
            $statusCode
        );
        $response->addHeader('Content-Type', 'application/json');
        return $response;
    }

    protected function jsonError($message, $statusCode = 400)
    {
        return $this->jsonResponse(['error' => $message], $statusCode);
    }

    /**
     * Validasi API Key & Token Bearer
     */
    protected function authorizeRequest(HTTPRequest $request)
    {
        // Cek API Key
        $apiKey = $request->getHeader('X-API-Key');
        if (!$apiKey) {
            return [null, $this->jsonError('Missing API Key', 403)];
        }
        $client = APIClient::get()->filter('API_KEY', $apiKey)->first();
        if (!$client) {
            return [null, $this->jsonError('Invalid API Key', 403)];
        }

        // Cek Access Token
        $authHeader = $request->getHeader('Authorization');
        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            return [null, $this->jsonError('Missing or invalid Authorization header', 401)];
        }
        $accessToken = substr($authHeader, 7);
        $tokenRecord = AppsToken::get()
            ->filter([
                'AccessToken' => $accessToken,
                'APIClientID' => $client->ID
            ])
            ->first();
        if (!$tokenRecord || !$tokenRecord->Member()->exists()) {
            return [null, $this->jsonError('Invalid access token', 401)];
        }
        return [$tokenRecord->Member(), null];
    }

    /**
     * GET /api/payment/calculate-shipping
     * Query params: destination_id, weight, couriers (optional, comma-separated)
     */


    /**
     * GET /api/payment/calculate-shipping
     * Query params: destination_id, weight, couriers (optional, comma-separated)
     * 
     * FIXED VERSION: Ensures consistent response format and parameter handling
     */
    public function calculate_shipping(HTTPRequest $request)
    {
        // Validasi request authorization
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        $destinationId = $request->getVar('destination_id');
        $weight = (int)$request->getVar('weight');
        $couriers = $request->getVar('couriers'); // Optional: comma-separated courier codes

        // Validasi input dengan lebih strict
        if (!$destinationId) {
            return $this->jsonError('Destination ID is required', 400);
        }

        // Pastikan destination_id adalah numeric
        $destinationIdNum = (int)$destinationId;
        if ($destinationIdNum <= 0) {
            return $this->jsonError('Destination ID must be a valid positive number', 400);
        }

        if ($weight <= 0) {
            return $this->jsonError('Weight must be greater than 0', 400);
        }

        // Batasi weight ke range yang reasonable untuk RajaOngkir
        if ($weight > 30000) { // Max 30kg
            return $this->jsonError('Weight exceeds maximum limit (30kg)', 400);
        }

        if ($weight < 1) { // Min 1g
            $weight = 1;
        }

        try {
            // Ambil origin dari SiteConfig
            $siteConfig = SiteConfig::current_site_config();
            if (!$siteConfig->StoreDistrictID) {
                return $this->jsonError('Store origin (StoreDistrictID) is not configured in Site Settings', 500);
            }

            $originId = (int)$siteConfig->StoreDistrictID;
            if ($originId <= 0) {
                return $this->jsonError('Store origin ID is invalid', 500);
            }

            // Jika couriers tidak dispesifikasi, gunakan default popular couriers
            if (!$couriers) {
                $couriers = 'jne:jnt:sicepat';
            }

            // Validasi courier codes
            $validCouriers = ['jne', 'jnt', 'sicepat', 'pos', 'lion', 'ninja', 'wahana', 'ide', 'sap'];
            $requestedCouriers = explode(':', $couriers);
            $filteredCouriers = array_intersect($requestedCouriers, $validCouriers);

            if (empty($filteredCouriers)) {
                return $this->jsonError('No valid courier codes provided', 400);
            }

            $validCouriersString = implode(':', $filteredCouriers);

            // Log request parameters for debugging
            error_log("Shipping calculation request: origin={$originId}, destination={$destinationIdNum}, weight={$weight}, couriers={$validCouriersString}");

            // Panggil fungsi kalkulasi dengan format API baru
            $shippingOptions = $this->fetchRajaOngkirShippingOptionsNew($originId, $destinationIdNum, $weight, $validCouriersString);

            if (isset($shippingOptions['error'])) {
                error_log("RajaOngkir API returned error: " . $shippingOptions['error']);
                return $this->jsonError($shippingOptions['error'], 400);
            }

            if (empty($shippingOptions)) {
                return $this->jsonError('No shipping options available for this route', 404);
            }

            // Group by courier untuk kemudahan frontend - FIXED VERSION
            $groupedOptions = [];
            foreach ($shippingOptions as $option) {
                $courierCode = $option['courier'];

                // Skip services with invalid cost
                if (!isset($option['cost']) || $option['cost'] <= 0) {
                    continue;
                }

                if (!isset($groupedOptions[$courierCode])) {
                    $groupedOptions[$courierCode] = [
                        'courier_code' => $courierCode,
                        'courier_name' => $option['courier_name'] ?: ucfirst($courierCode),
                        'services' => []
                    ];
                }

                // FIXED: Ensure consistent service name format
                $serviceName = $option['service'];
                $description = $option['description'] ?: $option['service'];

                // Format service name consistently
                if (!empty($description) && $description !== $serviceName) {
                    $displayServiceName = $serviceName . ' - ' . $description;
                } else {
                    $displayServiceName = $serviceName . ' - ' . $serviceName; // Fallback format
                }

                $groupedOptions[$courierCode]['services'][] = [
                    'service_code' => $option['service'],
                    'service_name' => $displayServiceName,
                    'description' => $description,
                    'cost' => (int)$option['cost'],
                    'cost_formatted' => 'Rp ' . number_format($option['cost'], 0, ',', '.'),
                    'etd' => $option['etd'] ?: 'N/A'
                ];
            }

            // Filter out couriers with no valid services
            $groupedOptions = array_filter($groupedOptions, function ($courier) {
                return !empty($courier['services']);
            });

            if (empty($groupedOptions)) {
                return $this->jsonError('No valid shipping services found', 404);
            }

            // Sort services by cost dalam setiap courier
            foreach ($groupedOptions as &$courier) {
                usort($courier['services'], function ($a, $b) {
                    return $a['cost'] - $b['cost'];
                });
            }

            // Sort couriers by cheapest service
            uasort($groupedOptions, function ($a, $b) {
                $minCostA = min(array_column($a['services'], 'cost'));
                $minCostB = min(array_column($b['services'], 'cost'));
                return $minCostA - $minCostB;
            });

            // FIXED: Ensure consistent response format
            $response = [
                'shipping_options' => array_values($groupedOptions),
                'total_options' => count($shippingOptions),
                'weight' => $weight,
                'origin_id' => $originId,
                'destination_id' => $destinationIdNum,
                'couriers_requested' => $filteredCouriers
            ];

            // Debug log for consistency check
            error_log("Final response format: " . json_encode($response));

            return $this->jsonResponse($response);
        } catch (\Exception $e) {
            error_log('Shipping calculation error: ' . $e->getMessage());
            return $this->jsonError('Failed to calculate shipping: ' . $e->getMessage(), 500);
        }
    }

    private function fetchRajaOngkirShippingOptionsNew($origin, $destination, $weight, $courier)
    {
        // Validasi bahwa URL tidak kosong sebelum digunakan
        if (empty($this->rajaOngkirApiUrl)) {
            return ["error" => "RajaOngkir API URL is not configured."];
        }

        // Validasi API Key
        if (empty($this->rajaOngkirApiKey)) {
            return ["error" => "RajaOngkir API key is not configured."];
        }

        // Validasi input parameters
        if ($origin <= 0 || $destination <= 0 || $weight <= 0) {
            return ["error" => "Invalid parameters: origin, destination, and weight must be positive numbers."];
        }

        $curl = curl_init();

        // Data yang akan dikirim dalam format POST
        $postData = [
            "origin"        => (string)$origin,
            "destination"   => (string)$destination,
            "weight"        => (int)$weight,
            "courier"       => $courier
        ];

        // Log the request data for debugging
        error_log("RajaOngkir API request data: " . json_encode($postData));

        // Menggunakan http_build_query untuk format application/x-www-form-urlencoded
        $postFields = http_build_query($postData);

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->rajaOngkirApiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                "content-type: application/x-www-form-urlencoded",
                "key: " . $this->rajaOngkirApiKey
            ],
            // Opsi SSL (aktifkan untuk production)
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);

        // Log response for debugging
        error_log("RajaOngkir API HTTP Code: $httpCode");
        if ($response) {
            error_log("RajaOngkir API Response: " . substr($response, 0, 500)); // Log first 500 chars
        }

        curl_close($curl);

        // Penanganan error cURL
        if ($err) {
            error_log("RajaOngkir (New) cURL Error: " . $err);
            return ["error" => "Network error: Unable to connect to shipping service."];
        }

        // Penanganan error HTTP status
        if ($httpCode !== 200) {
            $errorMsg = "API request failed with HTTP status code: $httpCode.";

            // Provide more specific error messages based on HTTP status
            switch ($httpCode) {
                case 400:
                    $errorMsg = "Invalid request parameters. Please check origin, destination, weight, and courier values.";
                    break;
                case 401:
                    $errorMsg = "API authentication failed. Please check API key.";
                    break;
                case 422:
                    $errorMsg = "Validation error: Invalid origin, destination, or courier combination.";
                    break;
                case 429:
                    $errorMsg = "API rate limit exceeded. Please try again later.";
                    break;
                case 500:
                    $errorMsg = "Shipping service internal error. Please try again later.";
                    break;
                case 503:
                    $errorMsg = "Shipping service temporarily unavailable.";
                    break;
            }

            error_log("RajaOngkir (New) HTTP Error: $httpCode - $errorMsg - Response: $response");
            return ["error" => $errorMsg];
        }

        $result = json_decode($response, true);

        // Penanganan error decode JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('RajaOngkir (New) JSON Error: ' . json_last_error_msg());
            return ["error" => "Invalid response format from shipping service."];
        }

        // Cek format response dari rajaongkir.komerce.id
        if (isset($result['meta']['code']) && $result['meta']['code'] != 200) {
            $errorMsg = $result['meta']['message'] ?? "Failed to fetch shipping costs from RajaOngkir.";
            error_log("RajaOngkir (New) API Logic Error: " . $errorMsg);
            return ["error" => $errorMsg];
        }

        // FIXED: Format ulang response agar sesuai dengan kebutuhan dan konsisten dengan Postman
        if (isset($result['data']) && !empty($result['data'])) {
            $ongkirList = [];

            // FIXED: Group services by courier untuk konsistensi
            $courierGroups = [];
            foreach ($result['data'] as $service) {
                // Skip services with invalid data
                if (!isset($service['cost']) || $service['cost'] <= 0) {
                    continue;
                }

                $courierCode = $service['code'] ?? '';
                if (!isset($courierGroups[$courierCode])) {
                    $courierGroups[$courierCode] = [
                        'courier_name' => $service['name'] ?? ucfirst($courierCode),
                        'services' => []
                    ];
                }

                $courierGroups[$courierCode]['services'][] = [
                    "courier_name" => $service['name'] ?? ucfirst($courierCode),
                    "courier"      => $courierCode,
                    "service"      => $service['service'] ?? '',
                    "description"  => $service['description'] ?? '',
                    "cost"         => (int)$service['cost'],
                    "etd"          => $service['etd'] ?? ''
                ];
            }

            // Convert to flat list like the original format
            foreach ($courierGroups as $courierCode => $courierData) {
                foreach ($courierData['services'] as $serviceData) {
                    $ongkirList[] = $serviceData;
                }
            }

            if (empty($ongkirList)) {
                return ["error" => "No valid shipping options found for this route."];
            }

            error_log("Final shipping options count: " . count($ongkirList));
            return $ongkirList;
        }

        // Jika tidak ada data pengiriman yang tersedia
        return ["error" => "No shipping options available for the selected route and couriers."];
    }



    public function list_orders(HTTPRequest $request)
    {
        // Validasi request authorization
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        try {
            // Parameter untuk pagination
            $page = (int)$request->getVar('page') ?: 1;
            $limit = (int)$request->getVar('limit') ?: 10;
            $limit = min($limit, 50); // Maksimal 50 item per page
            $offset = ($page - 1) * $limit;

            // Parameter untuk filter
            $status = $request->getVar('status'); // all, pending, paid, cancelled
            $paymentStatus = $request->getVar('payment_status'); // pending, paid, failed, cancelled
            $shippingStatus = $request->getVar('shipping_status'); // pending, processing, shipped, delivered

            // Base query untuk orders milik member
            $ordersQuery = Order::get()->filter([
                'MemberID' => $member->ID
            ]);

            // Apply filters jika ada
            if ($paymentStatus && in_array($paymentStatus, ['pending', 'paid', 'failed', 'cancelled'])) {
                $ordersQuery = $ordersQuery->filter('PaymentStatus', $paymentStatus);
            }

            if ($shippingStatus && in_array($shippingStatus, ['pending', 'processing', 'shipped', 'delivered'])) {
                $ordersQuery = $ordersQuery->filter('ShippingStatus', $shippingStatus);
            }

            // Quick status filter
            if ($status) {
                switch ($status) {
                    case 'pending':
                        $ordersQuery = $ordersQuery->filter('PaymentStatus', 'pending');
                        break;
                    case 'paid':
                        $ordersQuery = $ordersQuery->filter('PaymentStatus', 'paid');
                        break;
                    case 'cancelled':
                        $ordersQuery = $ordersQuery->filter([
                            'PaymentStatus' => ['cancelled', 'failed']
                        ]);
                        break;
                }
            }

            // Hitung total untuk pagination
            $totalOrders = $ordersQuery->count();
            $totalPages = ceil($totalOrders / $limit);

            // Ambil data dengan pagination dan sorting (terbaru dulu)
            $orders = $ordersQuery
                ->sort('Created DESC')
                ->limit($limit, $offset);

            $ordersList = [];
            foreach ($orders as $order) {
                // Ambil item pertama untuk preview
                $firstItem = $order->Items()->first();
                $firstProduct = null;
                $firstProductImage = null;

                if ($firstItem) {
                    $firstProduct = $firstItem->Product();
                    if ($firstProduct && $firstProduct->exists() && $firstProduct->Image()->exists()) {
                        $image = $firstProduct->Image();
                        $firstProductImage = [
                            'small' => Director::absoluteURL($image->Fill(80, 80)->getURL()) . '?v=' . time(),
                            'medium' => Director::absoluteURL($image->Fill(150, 150)->getURL()) . '?v=' . time(),
                            'original' => Director::absoluteURL($image->getURL()) . '?v=' . time()
                        ];
                    }
                }

                // Hitung total items dan quantity
                $totalItems = $order->Items()->count();
                $totalQuantity = 0;
                foreach ($order->Items() as $item) {
                    $totalQuantity += $item->Quantity;
                }

                // Format estimated delivery untuk display
                $estimatedDeliveryFormatted = null;
                $isDeliveryOverdue = false;

                if ($order->EstimatedDelivery) {
                    try {
                        $estimatedDate = new DateTime($order->EstimatedDelivery);
                        $estimatedDeliveryFormatted = $estimatedDate->format('d M Y');

                        // Check if delivery is overdue (only for shipped orders)
                        if ($order->ShippingStatus === 'shipped') {
                            $today = new DateTime();
                            $isDeliveryOverdue = $today > $estimatedDate;
                        }
                    } catch (\Exception $e) {
                        // Jika ada error parsing tanggal, set null
                        $estimatedDeliveryFormatted = null;
                    }
                }

                $ordersList[] = [
                    'id' => (int)$order->ID,
                    'reference' => $order->DuitkuReference,
                    'payment_status' => $order->PaymentStatus,
                    'shipping_status' => $order->ShippingStatus,
                    'created_at' => $order->Created,
                    'updated_at' => $order->LastEdited,
                    'expired_at' => $order->ExpiredAt,
                    'is_expired' => (
                        $order->PaymentStatus === 'pending'
                        && !empty($order->ExpiredAt)
                        && strtotime($order->ExpiredAt) < time()
                    ),

                    // Financial details
                    'total_price' => (float)$order->TotalPrice,
                    'total_price_formatted' => 'Rp ' . number_format($order->TotalPrice, 0, ',', '.'),
                    'shipping_cost' => (float)$order->ShippingCost,
                    'shipping_cost_formatted' => 'Rp ' . number_format($order->ShippingCost, 0, ',', '.'),

                    // Items summary
                    'total_items' => $totalItems,
                    'total_quantity' => $totalQuantity,
                    'first_product' => [
                        'id' => $firstProduct ? $firstProduct->ID : null,
                        'title' => $firstProduct && $firstProduct->exists() ? $firstProduct->Title : 'Product not found',
                        'slug' => $firstProduct && $firstProduct->exists() ? $firstProduct->URLSegment : null,
                        'image' => $firstProductImage
                    ],

                    // Shipping details
                    'courier' => strtoupper($order->Courier),
                    'service' => $order->Service,
                    'etd' => $order->ETD ?? null,                                    // ETD mentah dari RajaOngkir
                    'estimated_delivery' => $order->EstimatedDelivery ?? null,       // Tanggal estimasi (Y-m-d)
                    'estimated_delivery_formatted' => $estimatedDeliveryFormatted,   // Tanggal estimasi yang sudah diformat
                    'is_delivery_overdue' => $isDeliveryOverdue,                     // Apakah pengiriman sudah terlambat

                    // Payment details
                    'payment_method_code' => $order->PaymentMethod,
                    // 'payment_method' => $this->getPaymentMethodName($order->PaymentMethod),
                    'payment_url' => $order->PaymentUrl,
                    'fee' => (float)$order->Fee,
                    'fee_formatted' => 'Rp ' . number_format($order->Fee, 0, ',', '.'),

                    // Status helpers
                    'can_cancel' => $order->PaymentStatus === 'pending',
                    'can_pay' => $order->PaymentStatus === 'pending' && !empty($order->PaymentUrl),
                    'is_paid' => $order->PaymentStatus === 'paid',
                    'is_delivered' => $order->ShippingStatus === 'delivered',

                    // Tracking info
                    'tracking_number' => $order->TrackingNumber ?? null,
                    'has_tracking' => !empty($order->TrackingNumber)
                ];
            }

            // Debug::show($ordersList);
            // die;

            // Summary statistics
            $allOrders = Order::get()->filter(['MemberID' => $member->ID]);
            $statistics = [
                'total_orders' => $allOrders->count(),
                'pending_orders' => $allOrders->filter('PaymentStatus', 'pending')->count(),
                'paid_orders' => $allOrders->filter('PaymentStatus', 'paid')->count(),
                'delivered_orders' => $allOrders->filter('ShippingStatus', 'delivered')->count()
            ];

            $responseData = [
                'orders' => $ordersList,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_items' => $totalOrders,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1,
                    'next_page' => $page < $totalPages ? $page + 1 : null,
                    'prev_page' => $page > 1 ? $page - 1 : null
                ],
                'statistics' => $statistics,
                'filters' => [
                    'status' => $status,
                    'payment_status' => $paymentStatus,
                    'shipping_status' => $shippingStatus
                ]
            ];

            return $this->jsonResponse($responseData);
        } catch (\Exception $e) {
            return $this->jsonError('Internal server error: ' . $e->getMessage(), 500);
        }
    }

    public function show_order(HTTPRequest $request)
    {
        // Validasi request authorization
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        // Ambil order ID dari URL parameter
        $orderId = $request->param('ID');
        if (!$orderId) {
            return $this->jsonError('Order ID is required', 400);
        }

        // Cari order berdasarkan ID dan pastikan milik member yang sedang login
        $order = Order::get()->filter([
            'ID' => $orderId,
            'MemberID' => $member->ID
        ])->first();

        if (!$order) {
            return $this->jsonError('Order not found', 404);
        }

        try {
            // Ambil detail items order
            $orderItems = [];
            $totalItems = 0;
            $subTotalCalculated = 0;

            foreach ($order->Items() as $item) {
                $product = $item->Product();
                $variant = $item->Variant();

                $itemSubtotal = $item->Price * $item->Quantity;
                $subTotalCalculated += $itemSubtotal;

                // Handle product image
                $productImage = null;
                if ($product && $product->exists() && $product->Image()->exists()) {
                    $image = $product->Image();
                    $productImage = [
                        'original' => Director::absoluteURL($image->getURL()) . '?v=' . time(),
                        'small' => Director::absoluteURL($image->Fill(150, 150)->getURL()) . '?v=' . time(),
                        'medium' => Director::absoluteURL($image->Fill(300, 300)->getURL()) . '?v=' . time(),
                        'large' => Director::absoluteURL($image->Fill(600, 600)->getURL()) . '?v=' . time()
                    ];
                }

                $orderItems[] = [
                    'id' => $item->ID,
                    'product_id' => $item->ProductID,
                    'product_title' => $product && $product->exists() ? $product->Title : 'Product not found',
                    'product_slug' => $product && $product->exists() ? $product->URLSegment : null,
                    'product_image' => $productImage,
                    'variant_id' => $item->VariantID ?: null,
                    'variant_title' => $variant && $variant->exists() ? $variant->Title : null,
                    'quantity' => (int)$item->Quantity,
                    'price' => (float)$item->Price,
                    'weight' => (float)$item->Weight,
                    'subtotal' => (float)$itemSubtotal,
                    'price_formatted' => 'Rp ' . number_format($item->Price, 0, ',', '.'),
                    'subtotal_formatted' => 'Rp ' . number_format($itemSubtotal, 0, ',', '.')
                ];

                $totalItems++;
            }

            // Ambil informasi customer
            $customer = $order->Member();
            $customerData = [
                'id' => $customer->ID,
                'name' => $customer->getName(),
                'first_name' => $customer->FirstName,
                'surname' => $customer->Surname,
                'email' => $customer->Email,
                'phone' => $customer->PhoneNumber ?: null
            ];

            // Ambil informasi alamat pengiriman
            $address = $order->Address();
            $shippingAddress = null;
            if ($address && $address->exists()) {
                $shippingAddress = [
                    'id' => $address->ID,
                    'recipient_name' => $address->Title,
                    'phone_number' => $address->PhoneNumber,
                    'address_line' => $address->Alamat,
                    'postal_code' => $address->KodePos,
                    'province' => $address->Provinsi,
                    'city' => $address->Kota,
                    'district' => $address->Kecamatan,
                    'sub_district' => $address->SubDistrict ?? null,
                    'full_address' => $address->getFullAddress() // Asumsi method ini ada di model Address
                ];
            }

            // Format estimated delivery untuk display
            $estimatedDeliveryFormatted = null;
            $isDeliveryOverdue = false;

            if ($order->EstimatedDelivery) {
                try {
                    $estimatedDate = new DateTime($order->EstimatedDelivery);
                    $estimatedDeliveryFormatted = $estimatedDate->format('d M Y');

                    // Check if delivery is overdue (only for shipped orders)
                    if ($order->ShippingStatus === 'shipped') {
                        $today = new DateTime();
                        $isDeliveryOverdue = $today > $estimatedDate;
                    }
                } catch (\Exception $e) {
                    // Jika ada error parsing tanggal, set null
                    $estimatedDeliveryFormatted = null;
                }
            }

            // Check if order is expired - FIXED: Handle null ExpiredAt
            $isExpired = false;
            if ($order->PaymentStatus === 'pending' && !empty($order->ExpiredAt)) {
                $expiredTimestamp = strtotime($order->ExpiredAt);
                $isExpired = $expiredTimestamp !== false && $expiredTimestamp < time();
            }

            // Siapkan response data
            $responseData = [
                'order' => [
                    'id' => (int)$order->ID,
                    'reference' => $order->DuitkuReference,
                    'payment_status' => $order->PaymentStatus,
                    'shipping_status' => $order->ShippingStatus,
                    'created_at' => $order->Created,
                    'updated_at' => $order->LastEdited,

                    // Financial details
                    'subtotal' => (float)$subTotalCalculated,
                    'shipping_cost' => (float)$order->ShippingCost,
                    'total_price' => (float)$order->TotalPrice,

                    // Formatted currency
                    'subtotal_formatted' => 'Rp ' . number_format($subTotalCalculated, 0, ',', '.'),
                    'shipping_cost_formatted' => 'Rp ' . number_format($order->ShippingCost, 0, ',', '.'),
                    'total_price_formatted' => 'Rp ' . number_format($order->TotalPrice, 0, ',', '.'),

                    // Shipping details
                    'courier' => strtoupper($order->Courier),
                    'service' => $order->Service,
                    'etd' => $order->ETD ?? null,                                    // ETD mentah dari RajaOngkir
                    'estimated_delivery' => $order->EstimatedDelivery ?? null,       // Tanggal estimasi (Y-m-d)
                    'estimated_delivery_formatted' => $estimatedDeliveryFormatted,   // Tanggal estimasi yang sudah diformat
                    'is_delivery_overdue' => $isDeliveryOverdue,                     // Apakah pengiriman sudah terlambat

                    // Payment details
                    'payment_url' => $order->PaymentUrl,
                    'payment_method_code' => $order->PaymentMethod,
                    // 'payment_method' => $this->getPaymentMethodName($order->PaymentMethod),
                    'fee' => (float)$order->Fee,
                    'fee_formatted' => 'Rp ' . number_format($order->Fee, 0, ',', '.'),

                    // Counts
                    'total_items' => $totalItems,
                    'total_quantity' => array_sum(array_column($orderItems, 'quantity'))
                ],
                'items' => $orderItems,
                'customer' => $customerData,
                'shipping_address' => $shippingAddress,

                // Status helpers
                'can_cancel' => $order->PaymentStatus === 'pending',
                'can_pay' => $order->PaymentStatus === 'pending' && !empty($order->PaymentUrl),
                'is_paid' => $order->PaymentStatus === 'paid',
                'is_delivered' => $order->ShippingStatus === 'delivered',

                'expired_at' => $order->ExpiredAt,
                'is_expired' => $isExpired, // FIXED: Use the safely calculated $isExpired

                // Tracking info (jika ada sistem tracking)
                'tracking_number' => $order->TrackingNumber ?? null,
                'tracking_url' => $order->TrackingNumber ? $this->getTrackingUrl($order->Courier, $order->TrackingNumber) : null
            ];

            // Debug::show($responseData);
            // die;

            return $this->jsonResponse($responseData);
        } catch (\Exception $e) {
            return $this->jsonError('Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/payment/create_order
     * Body: {
     *   "cart_ids": [1, 2, 3], // Array of cart item IDs to checkout
     *   "address_id": 1,
     *   "payment_method": "BC",
     *   "courier": "jne",
     *   "service": "REG"
     * }
     */
    public function create_order(HTTPRequest $request)
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->jsonError('Method not allowed', 405);
        }

        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        try {
            $body = json_decode($request->getBody(), true);
            if (!$body) {
                return $this->jsonError('Invalid JSON body', 400);
            }

            // Validasi input
            if (empty($body['cart_ids']) || !is_array($body['cart_ids'])) {
                return $this->jsonError('Cart IDs array is required', 400);
            }
            if (empty($body['address_id'])) return $this->jsonError('Address ID is required', 400);
            if (empty($body['payment_method'])) return $this->jsonError('Payment method is required', 400);
            if (empty($body['courier'])) return $this->jsonError('Courier is required', 400);
            if (empty($body['service'])) return $this->jsonError('Service is required', 400);

            // Cek address
            $address = Address::get()->filter(['ID' => $body['address_id'], 'MemberID' => $member->ID])->first();
            if (!$address) {
                return $this->jsonError('Address not found', 404);
            }

            // Ambil cart items
            $cartItems = Cart::get()->filter([
                'ID' => $body['cart_ids'],
                'MemberID' => $member->ID
            ]);

            if ($cartItems->count() === 0) {
                return $this->jsonError('No valid cart items found', 404);
            }

            // Validasi dan hitung total
            $totalPrice = 0;
            $totalWeight = 0;
            $validatedItems = [];

            foreach ($cartItems as $cartItem) {
                $product = $cartItem->Product();
                $variant = $cartItem->Variant();

                if (!$product->exists()) {
                    return $this->jsonError('Product not found for cart item ID ' . $cartItem->ID, 404);
                }

                // Cek stock jika ada variant
                if ($variant->exists() && $variant->Stock < $cartItem->Quantity) {
                    return $this->jsonError('Insufficient stock for ' . $variant->Title . '. Available: ' . $variant->Stock . ', Requested: ' . $cartItem->Quantity, 400);
                }

                $price = $cartItem->getPrice();
                $weight = $cartItem->getWeight();

                $validatedItems[] = [
                    'cart_item' => $cartItem,
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $cartItem->Quantity,
                    'price' => $price,
                    'weight' => $weight
                ];

                $totalPrice += $cartItem->getTotalPrice();
                $totalWeight += $cartItem->getTotalWeight();
            }

            if ($totalWeight <= 0) {
                return $this->jsonError('Total weight must be greater than 0', 400);
            }

            // Validasi input shipping
            $destinationIdNum = (int)$address->DistrictID;
            if ($destinationIdNum <= 0) {
                return $this->jsonError('Address destination ID must be a valid positive number', 400);
            }

            // Batasi weight ke range yang reasonable
            if ($totalWeight > 30000) {
                return $this->jsonError('Weight exceeds maximum limit (30kg)', 400);
            }

            if ($totalWeight < 1) {
                $totalWeight = 1;
            }

            // Ambil origin dari SiteConfig
            $siteConfig = SiteConfig::current_site_config();
            if (!$siteConfig->StoreDistrictID) {
                return $this->jsonError('Store origin (StoreDistrictID) is not configured in Site Settings', 500);
            }

            $originId = (int)$siteConfig->StoreDistrictID;
            if ($originId <= 0) {
                return $this->jsonError('Store origin ID is invalid', 500);
            }

            $courier = $body['courier'];
            $service = $body['service'];

            // Validasi courier codes
            $validCouriers = ['jne', 'jnt', 'sicepat', 'pos', 'lion', 'ninja', 'wahana', 'ide', 'sap'];
            if (!in_array($courier, $validCouriers)) {
                return $this->jsonError('Invalid courier code', 400);
            }

            // Fetch shipping options
            $shippingOptions = $this->fetchRajaOngkirShippingOptionsNew($originId, $destinationIdNum, $totalWeight, $courier);

            if (isset($shippingOptions['error'])) {
                return $this->jsonError($shippingOptions['error'], 400);
            }

            if (empty($shippingOptions)) {
                return $this->jsonError('No shipping options available for this route', 404);
            }

            // Group by courier untuk kemudahan mencari
            $groupedOptions = [];
            foreach ($shippingOptions as $option) {
                $courierCode = $option['courier'];

                // Skip services with invalid cost
                if (!isset($option['cost']) || $option['cost'] <= 0) {
                    continue;
                }

                if (!isset($groupedOptions[$courierCode])) {
                    $groupedOptions[$courierCode] = [
                        'courier_code' => $courierCode,
                        'courier_name' => $option['courier_name'] ?: ucfirst($courierCode),
                        'services' => []
                    ];
                }

                $serviceName = $option['service'];
                if (!empty($option['description']) && $option['description'] !== $serviceName) {
                    $serviceName .= ' - ' . $option['description'];
                }

                $groupedOptions[$courierCode]['services'][] = [
                    'service_code' => $option['service'],
                    'service_name' => $serviceName,
                    'description' => $option['description'] ?: $option['service'],
                    'cost' => (int)$option['cost'],
                    'cost_formatted' => 'Rp ' . number_format($option['cost'], 0, ',', '.'),
                    'etd' => $option['etd'] ?: 'N/A'
                ];
            }

            // Filter out couriers with no valid services
            $groupedOptions = array_filter($groupedOptions, function ($courier) {
                return !empty($courier['services']);
            });

            if (empty($groupedOptions)) {
                return $this->jsonError('No valid shipping services found', 404);
            }

            // Cari service yang dipilih dan ambil ETD
            $selectedShippingOption = null;
            $estimatedDelivery = null;

            if (isset($groupedOptions[$courier])) {
                foreach ($groupedOptions[$courier]['services'] as $serviceOption) {
                    if ($serviceOption['service_code'] === $service) {
                        $selectedShippingOption = $serviceOption;
                        // Parse ETD dan hitung estimated delivery
                        $estimatedDelivery = $this->calculateEstimatedDelivery($serviceOption['etd'], $service);
                        break;
                    }
                }
            }

            if (!$selectedShippingOption) {
                $availableServices = [];
                foreach ($groupedOptions as $courierData) {
                    if ($courierData['courier_code'] === $courier) {
                        foreach ($courierData['services'] as $serviceData) {
                            $availableServices[] = $serviceData['service_code'] . ' (' . $serviceData['cost_formatted'] . ')';
                        }
                        break;
                    }
                }

                $errorMessage = "Service '{$service}' not found for '{$courier}'.";
                if (!empty($availableServices)) {
                    $errorMessage .= " Available services: " . implode(', ', $availableServices);
                } else {
                    $availableCouriers = array_keys($groupedOptions);
                    $errorMessage .= " Available couriers: " . implode(', ', $availableCouriers);
                }

                return $this->jsonError($errorMessage, 400);
            }

            $shippingCost = $selectedShippingOption['cost'];
            $finalTotal = $totalPrice + $shippingCost;
            $expiryPeriod = 1440;

            // Buat order
            $order = Order::create();
            $order->MemberID = $member->ID;
            $order->AddressID = $address->ID;
            $order->TotalPrice = $finalTotal;
            $order->ShippingCost = $shippingCost;
            $order->Fee = 0;
            $order->ExpiredAt = date('Y-m-d H:i:s', strtotime("+$expiryPeriod minutes"));;
            $order->Courier = $courier;
            $order->Service = $service;
            $order->ETD = $selectedShippingOption['etd']; // Simpan ETD mentah dari RajaOngkir
            $order->EstimatedDelivery = $estimatedDelivery; // Simpan tanggal estimasi yang sudah dihitung
            $order->PaymentStatus = 'pending';
            $order->ShippingStatus = 'pending';
            $order->Notes = !empty($body['notes']) ? $body['notes'] : null;
            $order->write();

            // Buat order items dan update stock
            foreach ($validatedItems as $item) {
                $orderItem = OrderItem::create();
                $orderItem->OrderID = $order->ID;
                $orderItem->ProductID = $item['product']->ID;
                $orderItem->VariantID = $item['variant']->exists() ? $item['variant']->ID : 0;
                $orderItem->Quantity = $item['quantity'];
                $orderItem->Price = $item['price'];
                $orderItem->Weight = $item['weight'];
                $orderItem->write();

                // Update stock variant jika ada
                if ($item['variant']->exists()) {
                    $item['variant']->Stock -= $item['quantity'];
                    $item['variant']->write();
                }
            }

            // Buat payment request ke Duitku
            $paymentResult = $this->createDuitkuPayment($order, $body['payment_method']);

            // Debug::show($paymentResult);
            // die;

            if ($paymentResult['success']) {
                $paymentFee = isset($paymentResult['totalFee']) ? (int)$paymentResult['totalFee'] : 0;
                $finalTotal = $totalPrice + $shippingCost + $paymentFee;

                // Update order dengan data payment
                $order->TotalPrice = $finalTotal;
                $order->Fee = $paymentFee;
                $order->DuitkuReference = $paymentResult['reference'];
                $order->PaymentUrl = $paymentResult['paymentUrl'];
                $order->PaymentMethod = $body['payment_method'];
                $order->write();

                // Hapus cart items setelah semua berhasil
                foreach ($validatedItems as $item) {
                    $item['cart_item']->delete();
                }

                return $this->jsonResponse([
                    'success' => true,
                    'order_id' => $order->ID,
                    'total_amount' => $finalTotal,
                    'items_total' => $totalPrice,
                    'shipping_cost' => $shippingCost,
                    'payment_fee' => $paymentFee,
                    'total_weight' => $totalWeight,
                    'estimated_delivery' => $estimatedDelivery,
                    'etd' => $selectedShippingOption['etd'],
                    'paymentUrl' => $paymentResult['paymentUrl'],
                    'reference' => $paymentResult['reference']
                ]);
            } else {
                // Rollback: hapus order dan order items jika payment gagal
                foreach ($order->Items() as $orderItem) {
                    $orderItem->delete();
                }
                $order->delete();

                // Rollback stock
                foreach ($validatedItems as $item) {
                    if ($item['variant']->exists()) {
                        $item['variant']->Stock += $item['quantity'];
                        $item['variant']->write();
                    }
                }

                return $this->jsonError('Payment creation failed: ' . $paymentResult['message'], 400);
            }
        } catch (\Exception $e) {
            return $this->jsonError('Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Calculate estimated delivery date based on ETD from RajaOngkir
     * @param string $etd ETD string from RajaOngkir (e.g., "2 day", "1-2 day", "3-5 day")
     * @return string|null Estimated delivery date in Y-m-d format
     */
    private function calculateEstimatedDelivery($etd, $serviceCode)
    {
        if (empty($etd) || $etd === 'N/A') {
            return null;
        }

        // Parse ETD string
        $etd = strtolower(trim($etd));

        // Remove "day" or "days" from the string
        $etd = preg_replace('/\s*(day|days|hari)\s*/i', '', $etd);

        $days = 0;

        // Handle different ETD formats
        if (preg_match('/^(\d+)$/', $etd, $matches)) {
            // Format: "2"
            $days = (int)$matches[1];
        } elseif (preg_match('/^(\d+)-(\d+)$/', $etd, $matches)) {
            // Format: "1-2" or "3-5" - use the maximum value
            $days = (int)$matches[2];
        } elseif (preg_match('/^(\d+)\s*-\s*(\d+)$/', $etd, $matches)) {
            // Format: "1 - 2" with spaces
            $days = (int)$matches[2];
        } else {
            // If we can't parse it, try to extract first number found
            if (preg_match('/(\d+)/', $etd, $matches)) {
                $days = (int)$matches[1];
            } else {
                return null; // Can't parse, return null
            }
        }

        // Add buffer for safety (add 1 extra day)
        $days += 1;

        // Calculate estimated delivery date (skip weekends if needed)
        $estimatedDate = new DateTime();
        $estimatedDate->add(new DateInterval('P' . $days . 'D'));

        // Skip weekend delivery for most couriers (except for express services)
        $isExpressService = $this->isExpressService($serviceCode);
        if (!$isExpressService && in_array($estimatedDate->format('N'), [6, 7])) {
            // If delivery falls on weekend, move to next Monday
            $daysToAdd = 8 - (int)$estimatedDate->format('N');
            $estimatedDate->add(new DateInterval('P' . $daysToAdd . 'D'));
        }

        return $estimatedDate->format('Y-m-d');
    }

    /**
     * Check if service is express/same-day delivery
     * @param string $service Service code
     * @return bool
     */
    private function isExpressService($service)
    {
        $expressServices = [
            'YES',
            'ONS',
            'SDS',
            'CTCYES', // JNE
            'EZ',
            'SS', // J&T
            'SIUNT',
            'GOKIL', // SiCepat
            'EXPRESS',
            'SAMEDAY', // POS
            'REGPACK',
            'ONS', // Lion Parcel
            'NSS',
            'NSD', // Ninja Express
            'FR', // Wahana
            'ONS', // IDE
            'SS' // SAP Express
        ];

        return in_array(strtoupper($service), $expressServices);
    }
    /**
     * GET /api/payment/methods
     */
    public function get_payment_methods(HTTPRequest $request)
    {
        // Validasi request
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        $amount = $request->getVar('amount') ?: 10000;

        Debug::show($amount);
        die;
        try {
            $paymentMethods = $this->getDuitkuPaymentMethods($amount);

            return $this->jsonResponse($paymentMethods);
        } catch (\Exception $e) {
            return $this->jsonError('Failed to get payment methods: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/payment/callback
     * Duitku callback handler
     */
    public function callback(HTTPRequest $request)
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->jsonError('Method not allowed', 405);
        }

        try {
            $body = json_decode($request->getBody(), true);
            if (!$body) {
                $body = $request->postVars();
            }

            error_log('Duitku Callback: ' . json_encode($body));

            $merchantOrderId = $body['merchantOrderId'] ?? null;
            $amount = $body['amount'] ?? null;
            $resultCode = $body['resultCode'] ?? null;
            $signature = $body['signature'] ?? null;

            if (!$merchantOrderId || !$amount || !$signature) {
                return $this->jsonError('Missing required callback parameters', 400);
            }

            $calculatedSignature = md5($this->merchantCode . $amount . $merchantOrderId . $this->merchantKey);
            if ($signature !== $calculatedSignature) {
                error_log('Invalid signature. Expected: ' . $calculatedSignature . ', Got: ' . $signature);
                return $this->jsonError('Invalid signature', 400);
            }

            $order = Order::get()->filter('DuitkuReference', $merchantOrderId)->first();
            if (!$order) {
                return $this->jsonError('Order not found', 404);
            }

            if ($resultCode === '00') {
                $order->PaymentStatus = 'paid';
                $order->write();

                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Payment confirmed'
                ]);
            } else {
                $order->PaymentStatus = 'failed';
                $order->write();

                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Payment failed'
                ]);
            }
        } catch (\Exception $e) {
            error_log('Callback error: ' . $e->getMessage());
            return $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/payment/status/{order_id}
     */
    public function check_status(HTTPRequest $request)
    {
        // Validasi request
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        $orderId = $request->param('ID');
        if (!$orderId) {
            return $this->jsonError('Order ID is required', 400);
        }

        $order = Order::get()->filter([
            'ID' => $orderId,
            'MemberID' => $member->ID
        ])->first();

        if (!$order) {
            return $this->jsonError('Order not found', 404);
        }

        // Get order items details
        $orderItems = [];
        foreach ($order->Items() as $item) {
            $orderItems[] = [
                'product_id' => $item->ProductID,
                'product_title' => $item->Product()->Title,
                'variant_id' => $item->VariantID,
                'variant_title' => $item->Variant()->Title,
                'quantity' => $item->Quantity,
                'price' => $item->Price,
                'total_price' => $item->Price * $item->Quantity
            ];
        }

        return $this->jsonResponse([
            'order_id' => $order->ID,
            'payment_status' => $order->PaymentStatus,
            'shipping_status' => $order->ShippingStatus,
            'total_price' => $order->TotalPrice,
            'shipping_cost' => $order->ShippingCost,
            'courier' => $order->Courier,
            'service' => $order->Service,
            'reference' => $order->DuitkuReference,
            'payment_url' => $order->PaymentUrl,
            'items' => $orderItems,
            'created' => $order->Created
        ]);
    }

    /**
     * Helper: Get Duitku payment methods
     */
    private function getDuitkuPaymentMethods($amount)
    {
        $params = [
            'merchantcode' => $this->merchantCode,
            'amount' => $amount,
            'datetime' => date('Y-m-d H:i:s'),
            'signature' => md5($this->merchantCode . $amount . date('Y-m-d H:i:s') . $this->merchantKey)
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://sandbox.duitku.com/webapi/api/merchant/paymentmethod/getpaymentmethod',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode !== 200) {
            throw new \Exception('Failed to get payment methods from Duitku');
        }

        return json_decode($response, true);
    }

    /**
     * Helper: Create payment request to Duitku
     */
    private function createDuitkuPayment($order, $paymentMethod)
    {
        $merchantOrderId = 'ORDER-' . $order->ID . '-' . time();
        $amount = (int)$order->TotalPrice;

        $productDetails = [];
        foreach ($order->Items() as $item) {
            $productTitle = $item->Product()->Title ?? 'Unknown Product';
            $variantTitle = $item->Variant()->exists() ? $item->Variant()->Title : '';
            $qty = $item->Quantity;
            $productDetails[] = $productTitle .
                ($variantTitle ? " - $variantTitle" : '') .
                " (x$qty)";
        }
        $productDetails = implode(', ', $productDetails);

        // Limit product details length
        if (strlen($productDetails) > 255) {
            $productDetails = substr($productDetails, 0, 252) . '...';
        }

        $email = $order->Member()->Email;
        $phoneNumber = $order->Member()->PhoneNumber ?: '081234567890';
        $customerName = trim($order->Member()->FirstName . ' ' . $order->Member()->Surname);

        if (empty($customerName)) {
            $customerName = 'Customer';
        }

        $params = [
            'merchantCode' => $this->merchantCode,
            'paymentAmount' => $amount,
            'paymentMethod' => $paymentMethod,
            'merchantOrderId' => $merchantOrderId,
            'productDetails' => $productDetails,
            'customerName' => $customerName,
            'customerEmail' => $email,
            'customerPhone' => $phoneNumber,
            'callbackUrl' => $this->callbackUrl,
            'returnUrl' => $this->returnUrl,
            'expiryPeriod' => 1440,
            'signature' => md5($this->merchantCode . $merchantOrderId . $amount . $this->merchantKey)
        ];

        // Debug::show($params);
        // die;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            return [
                'success' => false,
                'message' => 'Network error: ' . $curlError
            ];
        }

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => "HTTP Error $httpCode: Failed to create payment request"
            ];
        }

        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'Invalid response format from payment gateway'
            ];
        }

        if (isset($result['statusCode']) && $result['statusCode'] === '00') {
            $returnedAmount = (int)($result['amount'] ?? 0);
            $totalFee = $returnedAmount - $amount;

            return [
                'success' => true,
                'paymentUrl' => $result['paymentUrl'],
                'reference' => $merchantOrderId,
                'totalFee' => $totalFee,
                'vaNumber' => $result['vaNumber'] ?? null,
                'finalAmount' => $returnedAmount
            ];
        } else {
            $errorMessage = $result['statusMessage'] ?? 'Unknown error from payment gateway';
            return [
                'success' => false,
                'message' => $errorMessage
            ];
        }
    }

    /**
     * Helper method untuk mendapatkan URL tracking
     */
    private function getTrackingUrl($courier, $trackingNumber)
    {
        $trackingUrls = [
            'jne' => "https://www.jne.co.id/id/tracking/trace/{$trackingNumber}",
            'pos' => "https://www.posindonesia.co.id/id/tracking#{$trackingNumber}",
            'tiki' => "https://www.tiki.id/tracking?connote={$trackingNumber}",
        ];

        $courier = strtolower($courier);
        return $trackingUrls[$courier] ?? null;
    }
}
