<?php

namespace App\Admin;

use SilverStripe\Admin\ModelAdmin;
use App\Models\Product;
use App\Models\Variant;
use App\Models\Category;

class ProductAdmin extends ModelAdmin
{
    private static $menu_title = 'Products';
    private static $url_segment = 'products';
    private static $managed_models = [
        Product::class,
        Variant::class,
        Category::class
    ];
}
