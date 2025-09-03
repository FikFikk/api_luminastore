<?php

namespace App\Admin;

use SilverStripe\Admin\ModelAdmin;
use App\Models\Order;

class OrderAdmin extends ModelAdmin
{
    private static $menu_title = 'Order';
    private static $url_segment = 'order';
    private static $managed_models = [
        Order::class,
    ];
}
