<?php

namespace App\Models;

use SilverStripe\Security\Member;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\FieldType\DBDatetime;
use App\Models\Address;

class AppMember extends Member
{
    private static $table_name = 'AppMember';

    private static $db = [
        'PhoneNumber' => 'Varchar(20)',
        'DeletedAt' => 'DBDatetime',
    ];

    private static $has_one = [
        'PhotoProfile' => Image::class
    ];

    private static $has_many = [
        'Addresses' => Address::class
    ];

    private static $owns = [
        'PhotoProfile'
    ];

    public function softDelete()
    {
        $this->DeletedAt = DBDatetime::now()->Rfc2822();
        $this->write();
    }
}
