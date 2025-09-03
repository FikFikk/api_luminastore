<?php

namespace App\Controllers;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Controller;
use App\Models\APIClient;
use App\Models\AppsToken;

/**
 * Base API Controller untuk konsistensi
 */
abstract class BaseAPIController extends Controller
{
    /**
     * Standard JSON Response Helper
     */
    protected function jsonResponse($data, $status = 200)
    {
        $response = [
            'status' => 'success',
            'data' => $data
        ];

        return HTTPResponse::create(
            json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status
        )->addHeader('Content-Type', 'application/json');
    }

    /**
     * Standard JSON Error Response Helper
     */
    protected function jsonError($message, $status = 400)
    {
        $response = [
            'status' => 'error',
            'message' => $message
        ];

        return HTTPResponse::create(
            json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status
        )->addHeader('Content-Type', 'application/json');
    }

    /**
     * Get API Client from X-API-Key header
     */
    protected function getApiClient(HTTPRequest $request)
    {
        $apiKey = $request->getHeader('X-API-Key');
        if (!$apiKey) {
            return null;
        }

        return APIClient::get()->filter('API_KEY', $apiKey)->first();
    }

    /**
     * Get Bearer Token from Authorization header
     */
    protected function getBearerToken(HTTPRequest $request)
    {
        $authHeader = $request->getHeader('Authorization');
        if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
            return substr($authHeader, 7);
        }
        return null;
    }

    /**
     * Authorize request with API Key only (untuk endpoint publik)
     */
    protected function authorizeApiKey(HTTPRequest $request)
    {
        $client = $this->getApiClient($request);
        if (!$client) {
            return $this->jsonError('Invalid API Key', 403);
        }
        return null; // Success
    }

    /**
     * Authorize request with API Key + Bearer Token (untuk endpoint protected)
     * Return: [Member, APIClient, Error]
     */
    protected function authorizeRequest(HTTPRequest $request)
    {
        // Check API Key
        $client = $this->getApiClient($request);
        if (!$client) {
            return [null, null, $this->jsonError('Invalid API Key', 403)];
        }

        // Check Bearer Token
        $token = $this->getBearerToken($request);
        if (!$token) {
            return [null, null, $this->jsonError('Missing or invalid Authorization header', 401)];
        }

        // Find member by token
        $tokenRecord = AppsToken::get()
            ->filter([
                'AccessToken' => $token,
                'APIClientID' => $client->ID
            ])
            ->first();

        if (!$tokenRecord || !$tokenRecord->Member()->exists()) {
            return [null, null, $this->jsonError('Invalid access token', 401)];
        }

        return [$tokenRecord->Member(), $client, null]; // [Member, APIClient, Error]
    }

    /**
     * Parse JSON request body
     */
    protected function getJsonBody(HTTPRequest $request)
    {
        $body = json_decode($request->getBody(), true);
        return json_last_error() === JSON_ERROR_NONE ? $body : null;
    }
}
