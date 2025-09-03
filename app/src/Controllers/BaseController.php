<?php

namespace App\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

class BaseController extends Controller
{
    protected function init()
    {
        parent::init();

        // Set header CORS
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, Accept, x-api-key");

        // Jika preflight OPTIONS request
        if ($this->getRequest()->httpMethod() === 'OPTIONS') {
            $response = HTTPResponse::create()
                ->addHeader('Access-Control-Allow-Origin', '*')
                ->addHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->addHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, x-api-key')
                ->setStatusCode(200);

            // Kirim response dan hentikan eksekusi supaya tidak dilempar 404
            $response->output();
            exit;
        }
    }
}
