<?php

namespace App\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

class ResetPasswordController extends Controller
{
    private static $allowed_actions = [
        'index'
    ];

    public function index(HTTPRequest $request)
    {
        $email = $request->getVar('email');
        $ts    = $request->getVar('ts');
        $token = $request->getVar('token');
        $key   = $request->getVar('key');

        $data = [
            'Email' => $email,
            'Timestamp' => $ts,
            'Token' => $token,
            'ApiKey' => $key
        ];

        return $this->customise($data)->renderWith('ResetPasswordForm');
    }
}
