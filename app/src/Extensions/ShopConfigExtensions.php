<?php
namespace App\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\NumericField;

class ShopConfigExtension extends DataExtension
{
    private static $db = [
        'StoreTitle'     => 'Varchar(100)',
        'StoreAlamat'    => 'Text',
        'StoreKodePos'   => 'Varchar(10)',
        'StoreKecamatan' => 'Varchar(100)',
        'StoreKota'      => 'Varchar(100)',
        'StoreProvinsi'  => 'Varchar(100)',
        // ✅ KOLOM BARU UNTUK ID RAJAONGKIR
        'StoreProvinceID' => 'Int',
        'StoreCityID'     => 'Int',
        'StoreDistrictID' => 'Int', // ID Kecamatan Asal
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab('Root.StoreAddress', [
            TextField::create('StoreTitle', 'Nama Alamat (contoh: Gudang Utama)'),
            TextareaField::create('StoreAlamat', 'Alamat Lengkap'),
            TextField::create('StoreKodePos', 'Kode Pos'),
            TextField::create('StoreKecamatan', 'Kecamatan'),
            TextField::create('StoreKota', 'Kota'),
            TextField::create('StoreProvinsi', 'Provinsi'),
            // ✅ FIELD BARU DI CMS UNTUK MENGISI ID
            NumericField::create('StoreProvinceID', 'ID Provinsi (RajaOngkir)'),
            NumericField::create('StoreCityID', 'ID Kota/Kabupaten (RajaOngkir)'),
            NumericField::create('StoreDistrictID', 'ID Kecamatan (RajaOngkir)')
                ->setDescription('Ini akan digunakan sebagai lokasi asal pengiriman.'),
        ]);
    }
}
