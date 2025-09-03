<?php
namespace App\Models;

use SilverStripe\ORM\DataObject;

class AppsToken extends DataObject
{
    private static $table_name = 'AppsToken';

    private static $db = [
        'AccessToken' => 'Varchar(255)',
    ];

    private static $has_one = [
        'Member'    => AppMember::class,
        'APIClient' => APIClient::class,
    ];

    private static $indexes = [
        'AccessToken' => true, // unique index direkomendasikan di DB
    ];

    private static $default_sort = '"Created" DESC';
}
