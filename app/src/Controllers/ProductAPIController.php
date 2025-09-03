<?php

namespace App\Controllers;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\ORM\PaginatedList;
use App\Models\Product;
use App\Models\AppsToken;
use App\Models\Category;
use App\Models\APIClient;

class ProductAPIController extends BaseController
{
    private static $allowed_actions = [
        'index',
        'show',
        'categories'
    ];

    /**
     * Helper untuk membuat response JSON
     */
    protected function jsonResponse($data, $status = 200): HTTPResponse
    {
        $response = HTTPResponse::create(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            $status
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
     * GET /api/products
     */
    public function index(HTTPRequest $request)
    {
        // Validasi request
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        // Base query
        $products = Product::get()
            ->leftJoin('Variant', '"Variant"."ProductID" = "Product"."ID"')
            ->alterDataQuery(function ($query) {
                $query->selectField('"Variant"."Price"', 'VariantPrice');
            });

        // Filtering
        $filters = [
            'title'     => ['Title:PartialMatch', $request->getVar('title')],
            'deskripsi' => ['Deskripsi:PartialMatch', $request->getVar('deskripsi')],
            'category'  => ['Categories.Title:PartialMatch', $request->getVar('category')],
            'variant'   => ['Variants.Title:PartialMatch', $request->getVar('variant')],
            'rating'    => ['Rating', $request->getVar('rating')],
        ];

        foreach ($filters as [$field, $value]) {
            if ($value) {
                $products = $products->filter($field, $value);
            }
        }

        // Sorting
        $allowedSortFields = [
            'Title'  => 'Title',
            'Price'  => 'VariantPrice',
            'Rating' => 'Rating',
        ];
        $sortField = $allowedSortFields[$request->getVar('sort_field')] ?? 'Title';
        $sortDir   = strtoupper($request->getVar('sort_dir') ?? 'ASC');
        if (!in_array($sortDir, ['ASC', 'DESC'])) $sortDir = 'ASC';

        $products = $products->sort($sortField, $sortDir);

        // Pagination
        $page  = max(1, (int)$request->getVar('page') ?: 1);
        $limit = max(1, (int)$request->getVar('limit') ?: 20);

        $paginated = PaginatedList::create($products, $request)
            ->setPageLength($limit)
            ->setCurrentPage($page);

        // Build response data
        $data = [];
        foreach ($paginated as $product) {
            // Main image
            $mainImage = null;
            if ($product->Image()->exists()) {
                $mainImage = [
                    'original' => $product->Image()->AbsoluteLink(),
                    'small'    => $product->Image()->Fit(150, 150)->AbsoluteLink(),
                    'medium'   => $product->Image()->Fit(300, 300)->AbsoluteLink(),
                ];
            }

            // All images
            $images = [];
            foreach ($product->Images() as $img) {
                if ($img->exists()) {
                    $images[] = [
                        'original' => $img->AbsoluteLink(),
                        'small'    => $img->Fit(150, 150)->AbsoluteLink(),
                        'medium'   => $img->Fit(300, 300)->AbsoluteLink(),
                    ];
                }
            }

            $data[] = [
                'id'         => $product->ID,
                'title'      => $product->Title,
                'slug'       => $product->Slug,
                'deskripsi'  => $product->Deskripsi,
                'rating'     => $product->Rating,
                'weight'     => $product->Weight,
                'price'      => $product->VariantPrice,
                'image'      => $mainImage,
                'images'     => $images,
                'categories' => $product->Categories()->column('Title'),
            ];
        }

        return $this->jsonResponse([
            'data'  => $data,
            'total' => $paginated->getTotalItems(),
            'page'  => $page,
            'limit' => $limit
        ]);
    }


    /**
     * GET /api/product/show/{slug}
     */
    public function show(HTTPRequest $request)
    {
        try {
            list($member, $error) = $this->authorizeRequest($request);
            if ($error) {
                return $error;
            }

            $slug = $request->param('ID');
            if (!$slug) {
                return $this->jsonError('Slug is required', 400);
            }

            $product = Product::get()->filter('Slug', $slug)->first();
            if (!$product) {
                return $this->jsonError('Product not found', 404);
            }

            // Main image
            $mainImage = null;
            if ($product->Image()->exists()) {
                $mainImage = [
                    'original' => $product->Image()->AbsoluteLink(),
                    'small'    => $product->Image()->Fit(150, 150)->AbsoluteLink(),
                    'medium'   => $product->Image()->Fit(300, 300)->AbsoluteLink(),
                ];
            }

            // All images
            $images = [];
            foreach ($product->Images() as $img) {
                if ($img->exists()) {
                    $images[] = [
                        'original' => $img->AbsoluteLink(),
                        'small'    => $img->Fit(150, 150)->AbsoluteLink(),
                        'medium'   => $img->Fit(300, 300)->AbsoluteLink(),
                    ];
                }
            }

            // Variants
            $variants = [];
            foreach ($product->Variants() as $variant) {
                $variantImage = null;
                if ($variant->Image()->exists()) {
                    $variantImage = [
                        'original' => $variant->Image()->AbsoluteLink(),
                        'small'    => $variant->Image()->Fit(100, 100)->AbsoluteLink(),
                        'medium'   => $variant->Image()->Fit(300, 300)->AbsoluteLink(),
                    ];
                }

                $variants[] = [
                    'id'    => $variant->ID,
                    'title' => $variant->Title,
                    'sku'   => $variant->SKU,
                    'price' => $variant->Price,
                    'stock' => $variant->Stock,
                    'image' => $variantImage,
                ];
            }

            return $this->jsonResponse([
                'id'         => $product->ID,
                'title'      => $product->Title,
                'slug'       => $product->Slug,
                'deskripsi'  => $product->Deskripsi,
                'rating'     => $product->Rating,
                'image'      => $mainImage,
                'images'     => $images,
                'categories' => $product->Categories()->column('Title'),
                'variants'   => $variants
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }



    /**
     * GET /api/category
     */
    public function categories(HTTPRequest $request)
    {
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        $categories = Category::get()->map('ID', 'Title')->toArray();

        $data = [];
        foreach ($categories as $id => $title) {
            $data[] = ['id' => $id, 'title' => $title];
        }

        return $this->jsonResponse($data);
    }
}
