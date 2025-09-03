<?php

namespace App\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class Address extends DataObject
{
    private static $table_name = 'Address';

    private static $db = [
        'Title'     => 'Varchar(100)', // contoh: Rumah, Kantor
        'Alamat'    => 'Text',
        'KodePos'   => 'Varchar(10)',
        'Kecamatan' => 'Varchar(100)',
        'Kota'      => 'Varchar(100)',
        'Provinsi'  => 'Varchar(100)',
        'IsDefault' => 'Boolean(0)',
        'ProvinceID'     => 'Int',
        'CityID'         => 'Int',
        'DistrictID'     => 'Int',
        'SubDistrictID'  => 'Int',
    ];

    private static $has_one = [
        'Member' => Member::class
    ];

    private static $summary_fields = [
        'Title',
        'Alamat',
        'Kota',
        'Provinsi',
        'IsDefault'
    ];

    public function getFullAddress()
    {
        // Buat array untuk menampung bagian-bagian alamat yang tidak kosong
        $parts = [];

        if ($this->Alamat) {
            $parts[] = $this->Alamat;
        }
        if ($this->Kecamatan) {
            $parts[] = 'Kec. ' . $this->Kecamatan;
        }
        if ($this->Kota) {
            $parts[] = $this->Kota;
        }
        if ($this->Provinsi) {
            $parts[] = $this->Provinsi;
        }

        // Gabungkan bagian-bagian utama dengan ", "
        $fullAddress = implode(', ', $parts);

        // Tambahkan kode pos di akhir jika ada
        if ($this->KodePos) {
            $fullAddress .= ' ' . $this->KodePos;
        }

        return $fullAddress;
    }

    // GETTERS FOR PROVINCE / CITY / DISTRICT / SUBDISTRICT
    // public function Province()
    // {
    //     return $this->ProvinceID ? DataObject::get_by_id(Province::class, $this->ProvinceID) : null;
    // }

    // public function City()
    // {
    //     return $this->CityID ? DataObject::get_by_id(City::class, $this->CityID) : null;
    // }

    // public function District()
    // {
    //     return $this->DistrictID ? DataObject::get_by_id(District::class, $this->DistrictID) : null;
    // }

    // public function SubDistrict()
    // {
    //     return $this->SubDistrictID ? DataObject::get_by_id(SubDistrict::class, $this->SubDistrictID) : null;
    // }

}
