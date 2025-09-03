<?php

namespace App\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class Cart extends DataObject
{
    private static $table_name = 'Cart';

    private static $db = [
        'Quantity' => 'Int'
    ];

    private static $has_one = [
        'Member' => Member::class,
        'Product' => Product::class,
        'Variant' => Variant::class
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'Product.Title' => 'Product',
        'Variant.Title' => 'Variant',
        'Quantity' => 'Quantity',
        'Member.Email' => 'Member',
        'Created' => 'Added Date'
    ];

    private static $default_sort = 'Created DESC';

    private static $indexes = [
        'MemberProduct' => [
            'type' => 'unique',
            'columns' => ['MemberID', 'ProductID', 'VariantID']
        ]
    ];

    /**
     * Get the price for this cart item
     */
    public function getPrice()
    {
        if ($this->Variant()->exists()) {
            return $this->Variant()->Price;
        }
        return $this->Product()->Price ?? 0;
    }

    /**
     * Get the total price for this cart item (price * quantity)
     */
    public function getTotalPrice()
    {
        return $this->getPrice() * $this->Quantity;
    }

    /**
     * Get the weight for this cart item
     */
    public function getWeight()
    {
        return $this->Product()->Weight ?? 0;
    }

    /**
     * Get the total weight for this cart item (weight * quantity)
     */
    public function getTotalWeight()
    {
        return $this->getWeight() * $this->Quantity;
    }

    /**
     * Check if the cart item has sufficient stock
     */
    public function hasInsufficientStock()
    {
        if ($this->Variant()->exists()) {
            return $this->Variant()->Stock < $this->Quantity;
        }
        // If no variant, assume product has stock (or add Product stock field)
        return false;
    }

    /**
     * Get stock available for this cart item
     */
    public function getAvailableStock()
    {
        if ($this->Variant()->exists()) {
            return $this->Variant()->Stock;
        }
        // Return large number if no variant (or implement product stock)
        return 999;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        
        // Ensure quantity is at least 1
        if ($this->Quantity < 1) {
            $this->Quantity = 1;
        }
    }
}