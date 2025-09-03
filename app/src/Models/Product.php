<?php

namespace App\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\NumericField;
use SilverStripe\AssetAdmin\Forms\UploadField;

class Product extends DataObject
{
    private static $table_name = 'Product';

    private static $db = [
        'Title' => 'Varchar',
        'Slug' => 'Varchar',
        'Deskripsi' => 'Text',
        'Rating' => 'Decimal(3,1)',
        'Weight' => 'Decimal(10,2)' 
    ];

    private static $has_one = [
        'Image' => Image::class,
    ];

    private static $many_many = [
        'Images' => Image::class,
        'Categories' => Category::class,
    ];

    private static $has_many = [
        'Variants' => Variant::class,
    ];

    private static $owns = [
        'Image',
        'Images',
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'Slug' => 'Slug',
        'Rating' => 'Rating',
        'Variants.Count' => 'Jumlah Variant',
        'Categories.Count' => 'Jumlah Category'
    ];

    public function getCMSFields()
    {
        $fields = FieldList::create(
            TextField::create('Title'),
            TextField::create('Slug')->setReadonly(true),
            TextareaField::create('Deskripsi'),
            NumericField::create('Rating'),
            NumericField::create('Weight', 'Weight (gram)')
                ->setDescription('Berat produk untuk ongkir (gram)'),
            UploadField::create('Image')->setFolderName('product-main-images'),
            UploadField::create('Images')->setFolderName('product-images')->setIsMultiUpload(true),
            GridField::create('Variants', 'Variants', $this->Variants(), GridFieldConfig_RelationEditor::create()),
            GridField::create('Categories', 'Categories', $this->Categories(), GridFieldConfig_RelationEditor::create())
        );

        return $fields;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->Slug && $this->Title) {
            // Buat slug dari Title, lowercase, ganti spasi/dots/dash jadi '-'
            $slug = strtolower($this->Title);
            $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
            $slug = trim($slug, '-');
            $this->Slug = $slug;
        }
    }
}
