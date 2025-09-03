<?php

namespace App\Admin;

use SilverStripe\Admin\ModelAdmin;
use App\Models\APIClient;

class APIClientAdmin extends ModelAdmin
{
    private static $managed_models = [
        APIClient::class
    ];

    private static $url_segment = 'api-clients';
    private static $menu_title = 'API Clients';
}
