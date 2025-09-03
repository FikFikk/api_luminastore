<?php

namespace App\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;

class Category extends DataObject
{
    private static $table_name = 'Category';

    private static $db = [
        'Title' => 'Varchar'
    ];

    private static $belongs_many_many = [
        'Products' => Product::class,
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'Products.Count' => 'Jumlah Product',
    ];

    public function getCMSFields()
    {
        return FieldList::create(
            TextField::create('Title'),
            GridField::create('Products', 'Products', $this->Products(), GridFieldConfig_RelationEditor::create())
        );
    }
}
