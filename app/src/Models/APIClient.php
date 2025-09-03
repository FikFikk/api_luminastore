<?php
namespace App\Models;

use SilverStripe\ORM\DataObject;

class APIClient extends DataObject
{
    private static $table_name = 'APIClient';

    private static $db = [
        'Name' => 'Varchar',
        'API_KEY' => 'Varchar(255)'
    ];

    private static $summary_fields = [
        'Name', 'API_KEY', 'Created'
    ];

    public function onBeforeWrite()
    {
        if (!$this->API_KEY) {
            $this->API_KEY = bin2hex(random_bytes(16));
        }

        parent::onBeforeWrite();
    }
}
