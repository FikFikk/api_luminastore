<?php
namespace App\Models;

use SilverStripe\ORM\DataObject;

class OrderItem extends DataObject
{
    private static $table_name = 'OrderItem';

    private static $db = [
        'Quantity' => 'Int',
        'Price'    => 'Decimal(12,2)', // harga per item saat dibeli
        'Weight'   => 'Decimal(12,2)', // berat per item saat dibeli
    ];

    private static $has_one = [
        'Order'   => Order::class,
        'Product' => Product::class,
        'Variant' => Variant::class,
    ];

    private static $summary_fields = [
        'Product.Title',
        'Quantity',
        'Price',
        'Weight'
    ];

    public function getProductTitle()
    {
        return $this->Product()->exists() ? $this->Product()->Title : '(Produk tidak ditemukan)';
    }

    /**
     * Getter untuk gambar produk
     */
    public function getProductImage()
    {
        if ($this->Product()->exists() && $this->Product()->Image()->exists()) {
            return $this->Product()->Image()->Link();
        }
        return null;
    }

    /**
     * Getter untuk subtotal = Quantity × Price
     */
    public function getSubtotal()
    {
        return $this->Quantity * $this->Price;
    }
}
