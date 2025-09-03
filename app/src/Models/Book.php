<?php

namespace App\Models;

use SilverStripe\ORM\DataObject;

class Book extends DataObject
{
    private static $table_name = 'Book';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Author' => 'Varchar(255)',
        'PublishedYear' => 'Int'
    ];

    private static $summary_fields = [
        'ID', 'Title', 'Author', 'PublishedYear'
    ];

    private static $default_sort = 'Created DESC';
}
