<?php

namespace App\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use App\Models\CarouselSlide;

class SiteConfigExtension extends DataExtension
{

    private static $db = [
        // About Us Section
        'AboutTitle' => 'Varchar(255)',
        'AboutContent' => 'HTMLText',
    ];

    private static $has_one = [
        'AboutImage' => Image::class
    ];

    private static $has_many = [
        'CarouselSlides' => CarouselSlide::class
    ];

    private static $owns = [
        'AboutImage'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        // Remove default fields
        $fields->removeByName(['AboutImageID']);

        // Carousel Slider Tab
        $carouselConfig = GridFieldConfig_RecordEditor::create();
        $carouselConfig->getComponentByType(GridFieldAddNewButton::class)
            ->setButtonName('Add New Slide');

        $fields->addFieldsToTab('Root.Carousel Slider', [
            GridField::create(
                'CarouselSlides',
                'Carousel Slides',
                $this->owner->CarouselSlides()->sort('Sort ASC'),
                $carouselConfig
            )
        ]);

        // About Us Section Tab
        $fields->addFieldsToTab('Root.About Us', [
            TextField::create('AboutTitle', 'About Us Title')
                ->setDescription('Main title for About Us section')
                ->setAttribute('placeholder', 'e.g., About Our Company'),

            UploadField::create('AboutImage', 'About Us Image')
                ->setFolderName('about')
                ->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif'])
                ->setAllowedMaxFileNumber(1)
                ->setDescription('Main image for About Us section'),

            HTMLEditorField::create('AboutContent', 'About Us Content')
                ->setRows(8)
                ->setDescription('Main content describing your company/organization'),
        ]);
    }
}
