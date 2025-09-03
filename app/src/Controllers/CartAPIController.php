<?php

namespace App\Controllers;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Controller;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Variant;
use App\Models\AppsToken;
use App\Models\APIClient;

class CartAPIController extends BaseController
{
    private static $allowed_actions = [
        'add_to_cart',
        'get_cart',
        'update_cart_item',
        'remove_cart_item',
        'clear_cart'
    ];

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
     * POST /api/cart/add
     * Body: {
     *   "product_id": 1,
     *   "variant_id": 1, // optional
     *   "quantity": 2
     * }
     */
    public function add_to_cart(HTTPRequest $request)
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->jsonError('Method not allowed', 405);
        }

        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        try {
            $body = json_decode($request->getBody(), true);

            if (!$body) {
                $body = $request->postVars();
            }

            // Validasi input
            if (empty($body['product_id'])) {
                return $this->jsonError('Product ID is required', 400);
            }
            if (empty($body['quantity']) || (int)$body['quantity'] <= 0) {
                return $this->jsonError('Valid quantity is required', 400);
            }

            $productId = (int)$body['product_id'];
            $variantId = isset($body['variant_id']) ? (int)$body['variant_id'] : 0;
            $quantity = (int)$body['quantity'];

            // Cek product
            $product = Product::get()->byID($productId);
            if (!$product) {
                return $this->jsonError('Product not found', 404);
            }

            $variant = null;
            if ($variantId > 0) {
                $variant = Variant::get()->filter(['ID' => $variantId, 'ProductID' => $productId])->first();
                if (!$variant) {
                    return $this->jsonError('Variant not found', 404);
                }
                // Cek stock variant
                if ($variant->Stock < $quantity) {
                    return $this->jsonError('Insufficient stock. Available: ' . $variant->Stock, 400);
                }
            }

            // Cari cart item yang sudah ada
            $existingCart = Cart::get()->filter([
                'MemberID' => $member->ID,
                'ProductID' => $productId,
                'VariantID' => $variantId
            ])->first();

            if ($existingCart) {
                // Update quantity jika item sudah ada di cart
                $newQuantity = $existingCart->Quantity + $quantity;

                // Cek stock lagi untuk total quantity baru
                if ($variant && $variant->Stock < $newQuantity) {
                    return $this->jsonError('Insufficient stock. Available: ' . $variant->Stock . ', Current in cart: ' . $existingCart->Quantity, 400);
                }

                $existingCart->Quantity = $newQuantity;
                $existingCart->write();
                $cartItem = $existingCart;
            } else {
                // Buat cart item baru
                $cartItem = Cart::create();
                $cartItem->MemberID = $member->ID;
                $cartItem->ProductID = $productId;
                $cartItem->VariantID = $variantId;
                $cartItem->Quantity = $quantity;
                $cartItem->write();
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Item added to cart',
                'cart_item' => [
                    'id' => $cartItem->ID,
                    'product_id' => $cartItem->ProductID,
                    'product_title' => $cartItem->Product()->Title,
                    'variant_id' => $cartItem->VariantID,
                    'variant_title' => $cartItem->Variant()->Title,
                    'quantity' => $cartItem->Quantity,
                    'price' => $cartItem->getPrice(),
                    'total_price' => $cartItem->getTotalPrice()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->jsonError('Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/cart
     */
    public function get_cart(HTTPRequest $request)
    {
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        try {
            $cartItems = Cart::get()->filter('MemberID', $member->ID);

            $items = [];
            $totalPrice = 0;
            $totalWeight = 0;
            $totalItems = 0;

            foreach ($cartItems as $cartItem) {
                $itemData = [
                    'id' => $cartItem->ID,
                    'product_id' => $cartItem->ProductID,
                    'product_title' => $cartItem->Product()->Title,
                    'variant_id' => $cartItem->VariantID,
                    'variant_title' => $cartItem->Variant()->Title,
                    'quantity' => $cartItem->Quantity,
                    'price' => $cartItem->getPrice(),
                    'total_price' => $cartItem->getTotalPrice(),
                    'weight' => $cartItem->getWeight(),
                    'total_weight' => $cartItem->getTotalWeight(),
                    'available_stock' => $cartItem->getAvailableStock(),
                    'insufficient_stock' => $cartItem->hasInsufficientStock()
                ];

                $items[] = $itemData;
                $totalPrice += $cartItem->getTotalPrice();
                $totalWeight += $cartItem->getTotalWeight();
                $totalItems += $cartItem->Quantity;
            }

            return $this->jsonResponse([
                'success' => true,
                'items' => $items,
                'summary' => [
                    'total_items' => $totalItems,
                    'total_price' => $totalPrice,
                    'total_weight' => $totalWeight,
                    'items_count' => count($items)
                ]
            ]);
        } catch (\Exception $e) {
            return $this->jsonError('Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/cart/update/{cart_id}
     * Body: {
     *   "quantity": 3
     * }
     */
    public function update_cart_item(HTTPRequest $request)
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->jsonError('Method not allowed', 405);
        }

        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        try {
            $cartId = $request->param('ID');
            if (!$cartId) {
                return $this->jsonError('Cart item ID is required', 400);
            }

            $body = json_decode($request->getBody(), true);

            if (!$body) {
                $body = $request->postVars();
            }

            if (!$body || !isset($body['quantity'])) {
                return $this->jsonError('Quantity is required', 400);
            }

            $quantity = (int)$body['quantity'];
            if ($quantity <= 0) {
                return $this->jsonError('Quantity must be greater than 0', 400);
            }

            // Cari cart item
            $cartItem = Cart::get()->filter([
                'ID' => $cartId,
                'MemberID' => $member->ID
            ])->first();

            if (!$cartItem) {
                return $this->jsonError('Cart item not found', 404);
            }

            // Cek stock jika ada variant
            if ($cartItem->Variant()->exists() && $cartItem->Variant()->Stock < $quantity) {
                return $this->jsonError('Insufficient stock. Available: ' . $cartItem->Variant()->Stock, 400);
            }

            $cartItem->Quantity = $quantity;
            $cartItem->write();

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Cart item updated',
                'cart_item' => [
                    'id' => $cartItem->ID,
                    'product_id' => $cartItem->ProductID,
                    'product_title' => $cartItem->Product()->Title,
                    'variant_id' => $cartItem->VariantID,
                    'variant_title' => $cartItem->Variant()->Title,
                    'quantity' => $cartItem->Quantity,
                    'price' => $cartItem->getPrice(),
                    'total_price' => $cartItem->getTotalPrice()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->jsonError('Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/cart/remove/{cart_id}
     */
    public function remove_cart_item(HTTPRequest $request)
    {
        if ($request->httpMethod() !== 'DELETE') {
            return $this->jsonError('Method not allowed', 405);
        }

        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        try {
            $cartId = $request->param('ID');
            if (!$cartId) {
                return $this->jsonError('Cart item ID is required', 400);
            }

            $cartItem = Cart::get()->filter([
                'ID' => $cartId,
                'MemberID' => $member->ID
            ])->first();

            if (!$cartItem) {
                return $this->jsonError('Cart item not found', 404);
            }

            $cartItem->delete();

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Item removed from cart'
            ]);
        } catch (\Exception $e) {
            return $this->jsonError('Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/cart/clear
     */
    public function clear_cart(HTTPRequest $request)
    {
        if ($request->httpMethod() !== 'DELETE') {
            return $this->jsonError('Method not allowed', 405);
        }

        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        try {
            $cartItems = Cart::get()->filter('MemberID', $member->ID);

            foreach ($cartItems as $cartItem) {
                $cartItem->delete();
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Cart cleared'
            ]);
        } catch (\Exception $e) {
            return $this->jsonError('Internal server error: ' . $e->getMessage(), 500);
        }
    }
}
