<?php

namespace App\Models;

use DateTime;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Member;

class Order extends DataObject
{
    private static $table_name = 'Order';

    private static $db = [
        'TotalPrice'     => 'Decimal(12,2)',
        'ShippingCost'   => 'Decimal(12,2)',
        'PaymentStatus'  => 'Enum("pending,paid,failed","pending")',
        'ShippingStatus' => 'Enum("pending,shipped,delivered,cancelled","pending")',
        'PaymentMethod'     => 'Varchar(255)',
        'Courier'        => 'Varchar(50)',
        'Service'        => 'Varchar(50)',
        'DuitkuReference' => 'Varchar(100)',
        'TrackingNumber'  => 'Varchar(100)',
        'ETD' => 'Varchar(50)',
        'PaymentUrl'     => 'Text',
        'Notes'     => 'Varchar(255)',
        'Fee'     => 'Decimal(12,2)',
        "ExpiredAt" => "Datetime"
    ];

    private static $has_one = [
        'Member'  => Member::class,
        'Address' => Address::class,
    ];

    private static $has_many = [
        'Items' => OrderItem::class
    ];

    private static $summary_fields = [
        'ID',
        'Member.Email',
        'TotalPrice',
        'PaymentStatus',
        'ShippingStatus',
        'TrackingNumber'
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldToTab(
            'Root.Main',
            TextField::create('TrackingNumber', 'Tracking Number (Resi)')
                ->setDescription('Masukkan nomor resi pengiriman')
        );

        return $fields;
    }

    public function getSubTotal()
    {
        $total = 0;
        foreach ($this->Items() as $item) {
            $total += $item->getSubtotal();
        }
        return $total;
    }

    public function getCustomerName()
    {
        return $this->Member()->exists() ? $this->Member()->getName() : 'Tamu';
    }

    public function getCustomerEmail()
    {
        return $this->Member()->exists() ? $this->Member()->Email : '-';
    }

    public function getCustomerPhone()
    {
        return $this->Address()->exists() ? $this->Address()->PhoneNumber : ($this->Member()->exists() ? $this->Member()->PhoneNumber : '-');
    }

    public function getEstimatedDeliveryFormatted()
    {
        if (!$this->EstimatedDelivery) {
            return null;
        }

        $date = new DateTime($this->EstimatedDelivery);
        return $date->format('d M Y');
    }

    // Method untuk cek apakah estimasi sudah terlewat
    public function isDeliveryOverdue()
    {
        if (!$this->EstimatedDelivery || $this->ShippingStatus === 'delivered') {
            return false;
        }

        $today = new DateTime();
        $estimatedDate = new DateTime($this->EstimatedDelivery);

        return $today > $estimatedDate;
    }

    public function getShippingAddress()
    {
        if ($this->Address()->exists()) {
            $address = $this->Address();
            return sprintf(
                '%s, %s, %s, %s, %s %s',
                $address->Address,
                $address->Subdistrict,
                $address->City,
                $address->Province,
                $address->Country,
                $address->Postcode
            );
        }
        return 'Alamat tidak tersedia';
    }

    public function getOrderReference()
    {
        return $this->DuitkuReference;
    }

    public function getTotalItems()
    {
        return $this->Items()->count();
    }

    public function getFormattedSubTotal()
    {
        return number_format($this->getSubTotal(), 0, ',', '.');
    }

    public function getFormattedShippingCost()
    {
        return number_format($this->ShippingCost, 0, ',', '.');
    }

    public function getFormattedTotalPrice()
    {
        return number_format($this->TotalPrice, 0, ',', '.');
    }
}
