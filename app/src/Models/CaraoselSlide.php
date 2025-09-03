<?php

namespace App\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Security\Permission;

class CarouselSlide extends DataObject
{
    private static $table_name = 'CarouselSlide';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Description' => 'Text',
        'ButtonText' => 'Varchar(100)',
        'ButtonLink' => 'Varchar(255)',
        'Sort' => 'Int',
        'IsActive' => 'Boolean'
    ];

    private static $has_one = [
        'Image' => Image::class,
        'SiteConfig' => \SilverStripe\SiteConfig\SiteConfig::class
    ];

    private static $owns = [
        'Image'
    ];

    private static $default_sort = 'Sort ASC';

    private static $defaults = [
        'IsActive' => true,
        'Sort' => 0
    ];

    private static $summary_fields = [
        'Image.CMSThumbnail' => 'Image',
        'Title' => 'Title',
        'Description' => 'Description',
        'IsActive.Nice' => 'Active',
        'Sort' => 'Sort Order'
    ];

    private static $searchable_fields = [
        'Title',
        'Description',
        'IsActive'
    ];

    public function getCMSFields()
    {
        $fields = FieldList::create([
            TextField::create('Title', 'Slide Title')
                ->setDescription('Main title for this slide')
                ->setAttribute('placeholder', 'e.g., Welcome to Our Store'),

            UploadField::create('Image', 'Slide Image')
                ->setFolderName('carousel')
                ->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif'])
                ->setAllowedMaxFileNumber(1)
                ->setDescription('Main image for this slide (recommended: 1920x800px)'),

            TextareaField::create('Description', 'Description')
                ->setRows(3)
                ->setDescription('Brief description or subtitle for this slide')
                ->setAttribute('placeholder', 'e.g., Discover our latest products and special offers'),

            TextField::create('ButtonText', 'Button Text')
                ->setDescription('Text for the call-to-action button (optional)')
                ->setAttribute('placeholder', 'e.g., Shop Now, Learn More'),

            TextField::create('ButtonLink', 'Button Link')
                ->setDescription('URL for the button link (optional)')
                ->setAttribute('placeholder', 'e.g., /products, https://example.com'),

            NumericField::create('Sort', 'Sort Order')
                ->setDescription('Order of appearance (lower numbers appear first)')
                ->setValue($this->Sort ?: $this->getNextSortOrder()),

            CheckboxField::create('IsActive', 'Active')
                ->setDescription('Uncheck to hide this slide from the carousel')
        ]);

        return $fields;
    }

    public function getTitle()
    {
        return $this->getField('Title') ?: 'Untitled Slide';
    }

    protected function getNextSortOrder()
    {
        $maxSort = CarouselSlide::get()->max('Sort');
        return $maxSort ? $maxSort + 10 : 10;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // Set default sort order if not set
        if (!$this->Sort) {
            $this->Sort = $this->getNextSortOrder();
        }
    }

    public function canView($member = null)
    {
        return Permission::check('CMS_ACCESS_CMSMain', 'any', $member);
    }

    public function canEdit($member = null)
    {
        return Permission::check('CMS_ACCESS_CMSMain', 'any', $member);
    }

    public function canDelete($member = null)
    {
        return Permission::check('CMS_ACCESS_CMSMain', 'any', $member);
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check('CMS_ACCESS_CMSMain', 'any', $member);
    }
}
