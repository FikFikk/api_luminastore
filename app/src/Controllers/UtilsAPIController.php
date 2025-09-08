<?php

namespace App\Controllers;

use App\Models\Order;
use App\Models\Product;
use SilverStripe\ORM\DB;
use App\Models\APIClient;
use App\Models\AppsToken;
use App\Models\OrderItem;
use App\Models\CarouselSlide;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\SiteConfig\SiteConfig;

class UtilsAPIController extends BaseController
{
    private static $allowed_actions = [
        'latest_products',
        'popular_products',
        'carousel_slides',
        'site_config'
    ];

    private static $url_handlers = [
        'latest-products' => 'latest_products',
        'popular-products' => 'popular_products',
        'carousel-slides' => 'carousel_slides'
    ];

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

    public function site_config(HTTPRequest $request)
    {
        if ($request->httpMethod() !== 'GET') {
            return $this->jsonError('Method not allowed', 405);
        }

        // Hanya cek API Key (tanpa token user)
        $apiKey = $request->getHeader('X-API-Key');
        if (!$apiKey) {
            return $this->jsonError('Missing API Key', 403);
        }

        $client = APIClient::get()->filter('API_KEY', $apiKey)->first();
        if (!$client) {
            return $this->jsonError('Invalid API Key', 403);
        }

        try {
            $siteConfig = SiteConfig::current_site_config();

            // About image
            $aboutImageData = null;
            if ($siteConfig->AboutImage()->exists()) {
                $aboutImage = $siteConfig->AboutImage();
                $aboutImageData = [
                    'small' => $aboutImage->ScaleWidth(150)->getAbsoluteURL(),
                    'medium' => $aboutImage->ScaleWidth(400)->getAbsoluteURL(),
                    'large' => $aboutImage->ScaleWidth(800)->getAbsoluteURL(),
                    'original' => $aboutImage->getAbsoluteURL()
                ];
            }

            // Favicon
            $faviconData = null;
            if ($siteConfig->Favicon()->exists()) {
                $favicon = $siteConfig->Favicon();
                $faviconData = [
                    'small' => $favicon->ScaleWidth(32)->getAbsoluteURL(),
                    'medium' => $favicon->ScaleWidth(64)->getAbsoluteURL(),
                    'large' => $favicon->ScaleWidth(128)->getAbsoluteURL(),
                    'original' => $favicon->getAbsoluteURL()
                ];
            }

            $data = [
                'site_name' => $siteConfig->Title,
                'tagline' => $siteConfig->Tagline,
                'about' => [
                    'title' => $siteConfig->AboutTitle ?: 'About Us',
                    'content' => $siteConfig->AboutContent,
                    'image' => $aboutImageData
                ],
                'favicon' => $faviconData
            ];

            return $this->jsonResponse($data);
        } catch (\Exception $e) {
            return $this->jsonError('Failed to fetch site configuration: ' . $e->getMessage(), 500);
        }
    }


    /**
     * Get latest products
     * GET /api/utils/latest-products
     */
    public function latest_products(HTTPRequest $request)
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

            // Base query untuk products (latest first)
            $productsQuery = Product::get()->sort('Created DESC');

            // Hitung total untuk pagination
            $totalProducts = $productsQuery->count();
            $totalPages = ceil($totalProducts / $limit);

            // Ambil data dengan pagination
            $products = $productsQuery->limit($limit, $offset);

            $productsList = [];
            foreach ($products as $product) {
                // Get main image
                $productImage = null;
                if ($product->Image()->exists()) {
                    $image = $product->Image();
                    $productImage = [
                        'small' => Director::absoluteURL($image->Fill(150, 150)->getURL()) . '?v=' . time(),
                        'medium' => Director::absoluteURL($image->Fill(300, 300)->getURL()) . '?v=' . time(),
                        'large' => Director::absoluteURL($image->Fill(600, 600)->getURL()) . '?v=' . time(),
                        'original' => Director::absoluteURL($image->getURL()) . '?v=' . time()
                    ];
                }

                // Get variants info
                $variants = $product->Variants();
                $minPrice = null;
                $maxPrice = null;
                $hasStock = false;

                if ($variants->exists()) {
                    $prices = [];
                    foreach ($variants as $variant) {
                        $prices[] = (float)$variant->Price;
                        if ($variant->Stock > 0) {
                            $hasStock = true;
                        }
                    }
                    if (!empty($prices)) {
                        $minPrice = min($prices);
                        $maxPrice = max($prices);
                    }
                }

                // Get categories
                $categories = [];
                foreach ($product->Categories() as $category) {
                    $categories[] = [
                        'id' => (int)$category->ID,
                        'name' => $category->Name,
                        'slug' => $category->Slug
                    ];
                }

                $productsList[] = [
                    'id' => (int)$product->ID,
                    'title' => $product->Title,
                    'slug' => $product->Slug,
                    'description' => $product->Deskripsi,
                    'rating' => (float)$product->Rating,
                    'weight' => (float)$product->Weight,
                    'created_at' => $product->Created,
                    'updated_at' => $product->LastEdited,
                    'image' => $productImage,
                    'price_range' => [
                        'min' => $minPrice,
                        'max' => $maxPrice,
                        'min_formatted' => $minPrice ? 'Rp ' . number_format($minPrice, 0, ',', '.') : null,
                        'max_formatted' => $maxPrice ? 'Rp ' . number_format($maxPrice, 0, ',', '.') : null,
                        'display' => $minPrice && $maxPrice ?
                            ($minPrice == $maxPrice ?
                                'Rp ' . number_format($minPrice, 0, ',', '.') :
                                'Rp ' . number_format($minPrice, 0, ',', '.') . ' - Rp ' . number_format($maxPrice, 0, ',', '.')
                            ) : null
                    ],
                    'has_stock' => $hasStock,
                    'variants_count' => $variants->count(),
                    'categories' => $categories
                ];
            }

            $responseData = [
                'products' => $productsList,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_items' => $totalProducts,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1,
                    'next_page' => $page < $totalPages ? $page + 1 : null,
                    'prev_page' => $page > 1 ? $page - 1 : null
                ]
            ];

            return $this->jsonResponse($responseData);
        } catch (\Exception $e) {
            return $this->jsonError('Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get popular products based on order quantity (excluding pending shipments)
     * GET /api/utils/popular-products
     */
    public function popular_products(HTTPRequest $request)
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

            // Query untuk menghitung popularitas berdasarkan quantity
            // Hanya hitung dari order yang shipping status bukan 'pending'
            $popularityQuery = "
                SELECT 
                    p.ID as ProductID,
                    SUM(oi.Quantity) as TotalQuantity
                FROM Product p
                INNER JOIN OrderItem oi ON oi.ProductID = p.ID
                INNER JOIN `Order` o ON o.ID = oi.OrderID
                WHERE o.ShippingStatus != 'pending'
                GROUP BY p.ID
                ORDER BY TotalQuantity DESC
            ";

            $popularityResults = DB::query($popularityQuery);
            $popularProductIds = [];
            $popularityMap = [];

            foreach ($popularityResults as $result) {
                $popularProductIds[] = $result['ProductID'];
                $popularityMap[$result['ProductID']] = (int)$result['TotalQuantity'];
            }

            if (empty($popularProductIds)) {
                return $this->jsonResponse([
                    'products' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => $limit,
                        'total_items' => 0,
                        'total_pages' => 0,
                        'has_next' => false,
                        'has_prev' => false,
                        'next_page' => null,
                        'prev_page' => null
                    ]
                ]);
            }

            // Hitung total untuk pagination
            $totalProducts = count($popularProductIds);
            $totalPages = ceil($totalProducts / $limit);

            // Ambil subset untuk pagination
            $paginatedIds = array_slice($popularProductIds, $offset, $limit);

            // Ambil data products berdasarkan popularity order
            $products = Product::get()->filter('ID', $paginatedIds);

            // Sort products berdasarkan popularity order
            $productsArray = $products->toArray();
            usort($productsArray, function ($a, $b) use ($popularityMap) {
                return $popularityMap[$b->ID] - $popularityMap[$a->ID];
            });

            $productsList = [];
            foreach ($productsArray as $product) {
                // Get main image
                $productImage = null;
                if ($product->Image()->exists()) {
                    $image = $product->Image();
                    $productImage = [
                        'small' => Director::absoluteURL($image->Fill(150, 150)->getURL()) . '?v=' . time(),
                        'medium' => Director::absoluteURL($image->Fill(300, 300)->getURL()) . '?v=' . time(),
                        'large' => Director::absoluteURL($image->Fill(600, 600)->getURL()) . '?v=' . time(),
                        'original' => Director::absoluteURL($image->getURL()) . '?v=' . time()
                    ];
                }

                // Get variants info
                $variants = $product->Variants();
                $minPrice = null;
                $maxPrice = null;
                $hasStock = false;

                if ($variants->exists()) {
                    $prices = [];
                    foreach ($variants as $variant) {
                        $prices[] = (float)$variant->Price;
                        if ($variant->Stock > 0) {
                            $hasStock = true;
                        }
                    }
                    if (!empty($prices)) {
                        $minPrice = min($prices);
                        $maxPrice = max($prices);
                    }
                }

                // Get categories
                $categories = [];
                foreach ($product->Categories() as $category) {
                    $categories[] = [
                        'id' => (int)$category->ID,
                        'name' => $category->Name,
                        'slug' => $category->Slug
                    ];
                }

                $productsList[] = [
                    'id' => (int)$product->ID,
                    'title' => $product->Title,
                    'slug' => $product->Slug,
                    'description' => $product->Deskripsi,
                    'rating' => (float)$product->Rating,
                    'weight' => (float)$product->Weight,
                    'created_at' => $product->Created,
                    'updated_at' => $product->LastEdited,
                    'image' => $productImage,
                    'price_range' => [
                        'min' => $minPrice,
                        'max' => $maxPrice,
                        'min_formatted' => $minPrice ? 'Rp ' . number_format($minPrice, 0, ',', '.') : null,
                        'max_formatted' => $maxPrice ? 'Rp ' . number_format($maxPrice, 0, ',', '.') : null,
                        'display' => $minPrice && $maxPrice ?
                            ($minPrice == $maxPrice ?
                                'Rp ' . number_format($minPrice, 0, ',', '.') :
                                'Rp ' . number_format($minPrice, 0, ',', '.') . ' - Rp ' . number_format($maxPrice, 0, ',', '.')
                            ) : null
                    ],
                    'has_stock' => $hasStock,
                    'variants_count' => $variants->count(),
                    'categories' => $categories,
                    'total_sold' => $popularityMap[$product->ID] // Total quantity sold
                ];
            }

            $responseData = [
                'products' => $productsList,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_items' => $totalProducts,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1,
                    'next_page' => $page < $totalPages ? $page + 1 : null,
                    'prev_page' => $page > 1 ? $page - 1 : null
                ]
            ];

            return $this->jsonResponse($responseData);
        } catch (\Exception $e) {
            return $this->jsonError('Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get carousel slides
     * GET /api/utils/carousel-slides
     */
    public function carousel_slides(HTTPRequest $request)
    {
        // Validasi request authorization
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        try {
            // Ambil semua slide yang aktif, diurutkan berdasarkan Sort
            $slides = CarouselSlide::get()
                ->filter('IsActive', true)
                ->sort('Sort ASC');

            $slidesList = [];
            foreach ($slides as $slide) {
                // Get slide image
                $slideImage = null;
                if ($slide->Image()->exists()) {
                    $image = $slide->Image();
                    $slideImage = [
                        'small' => Director::absoluteURL($image->Fill(400, 200)->getURL()) . '?v=' . time(),
                        'medium' => Director::absoluteURL($image->Fill(800, 400)->getURL()) . '?v=' . time(),
                        'large' => Director::absoluteURL($image->Fill(1200, 600)->getURL()) . '?v=' . time(),
                        'original' => Director::absoluteURL($image->getURL()) . '?v=' . time()
                    ];
                }

                $slidesList[] = [
                    'id' => (int)$slide->ID,
                    'title' => $slide->Title,
                    'description' => $slide->Description,
                    'button_text' => $slide->ButtonText,
                    'button_link' => $slide->ButtonLink,
                    'sort_order' => (int)$slide->Sort,
                    'image' => $slideImage,
                    'created_at' => $slide->Created,
                    'updated_at' => $slide->LastEdited
                ];
            }

            $responseData = [
                'slides' => $slidesList,
                'total_slides' => count($slidesList)
            ];

            return $this->jsonResponse($responseData);
        } catch (\Exception $e) {
            return $this->jsonError('Internal server error: ' . $e->getMessage(), 500);
        }
    }
}
