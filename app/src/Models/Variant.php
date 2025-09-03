<?php

namespace App\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\NumericField;
use SilverStripe\AssetAdmin\Forms\UploadField;

class Variant extends DataObject
{
    private static $table_name = 'Variant';

    private static $db = [
        'Title' => 'Varchar',
        'SKU' => 'Varchar',
        'Price' => 'Decimal(10,2)',
        'Stock' => 'Int'
    ];

    private static $has_one = [
        'Product' => Product::class,
        'Image' => Image::class
    ];

    private static $owns = [
        'Image',
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'SKU' => 'SKU',
        'Price' => 'Price',
        'Stock' => 'Stock',
        'Product.Title' => 'Product',
    ];

    public function getCMSFields()
    {
        return FieldList::create(
            TextField::create('Title'),
            TextField::create('SKU'),
            NumericField::create('Price'),
            NumericField::create('Stock'),
            UploadField::create('Image')->setFolderName('variant-images')
        );
    }
}
