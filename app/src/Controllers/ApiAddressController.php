<?php

namespace App\Controllers;

use App\Models\Address;
use SilverStripe\ORM\DB;
use App\Models\APIClient;
use App\Models\AppsToken;
use SilverStripe\Security\Member;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

class ApiAddressController extends BaseController
{
    private static $allowed_actions = [
        'createMemberAddress',
        'getMemberAddresses',
        'setDefaultAddress',
        'deleteMemberAddress',
        'updateMemberAddress'
    ];

    // private $rajaOngkirApiKey  = 'TJjGC1vo902905ba249532de9ghTh1yV';
    private $rajaOngkirApiKey  = '55debe1527da557278552dc3007fbbf3'; //ct account

    protected function authorizeRequest(HTTPRequest $request)
    {
        // Cek API Key
        $apiKey = $request->getHeader('X-API-Key');
        if (!$apiKey) {
            return [null, $this->jsonError('Missing API Key', 403)];
        }

        $client = APIClient::get()->filter('API_KEY', $apiKey)->first();
        if (!$client) {
            return [null, $this->jsonError('Invalid API Key', 403)];
        }

        // Cek Access Token
        $authHeader = $request->getHeader('Authorization');
        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            return [null, $this->jsonError('Missing or invalid Authorization header', 401)];
        }
        $accessToken = substr($authHeader, 7);

        $tokenRecord = AppsToken::get()
            ->filter([
                'AccessToken' => $accessToken,
                'APIClientID' => $client->ID
            ])
            ->first();

        if (!$tokenRecord || !$tokenRecord->Member()->exists()) {
            return [null, $this->jsonError('Invalid access token', 401)];
        }

        return [$tokenRecord->Member(), null];
    }

    protected function jsonResponse($data, $status = 200)
    {
        $response = HTTPResponse::create(json_encode($data));
        $response->addHeader('Content-Type', 'application/json');
        $response->setStatusCode($status);
        return $response;
    }

    protected function jsonError($message, $statusCode = 400)
    {
        return $this->jsonResponse(['error' => $message], $statusCode);
    }

    public function getMemberAddresses(HTTPRequest $request)
    {
        // Authorize request, ambil member dari token
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        $addresses = Address::get()->filter(['MemberID' => $member->ID]);
        if ($addresses->count() === 0) {
            return $this->jsonResponse(['addresses' => []]);
        }

        $result = [];
        foreach ($addresses as $address) {
            $result[] = [
                'ID'              => $address->ID,
                'ClassName'       => $address->ClassName,
                'RecordClassName' => $address->RecordClassName,
                'Title'           => $address->Title,
                'Alamat'          => $address->Alamat,
                'KodePos'         => $address->KodePos,
                'Kecamatan'       => $address->Kecamatan,
                'Kota'            => $address->Kota,
                'Provinsi'        => $address->Provinsi,
                'IsDefault'       => $address->IsDefault,
                'MemberID'        => $address->MemberID,
                'ProvinceID'      => $address->ProvinceID,
                'CityID'          => $address->CityID,
                'DistrictID'      => $address->DistrictID,
                'SubDistrictID'   => $address->SubDistrictID,
                'Created'         => $address->Created,
                'LastEdited'      => $address->LastEdited,
            ];
        }

        return $this->jsonResponse([
            'success' => true,
            'addresses' => $result
        ]);
    }

    public function createMemberAddress(HTTPRequest $request)
    {
        // Authorize request
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        $body = json_decode($request->getBody(), true);
        if (!$body) {
            return $this->jsonResponse(['error' => 'Invalid JSON'], 400);
        }

        // Validasi required fields
        $requiredFields = ['title', 'alamat', 'provinsi', 'kota', 'kecamatan', 'kodepos'];
        foreach ($requiredFields as $field) {
            if (empty($body[$field])) {
                return $this->jsonResponse(['error' => ucfirst($field) . ' is required'], 400);
            }
        }

        try {
            // Ambil ID hierarki RajaOngkir berdasarkan nama lokasi
            $rajaIDs = $this->fetchRajaOngkirIDsByHierarchy(
                $body['provinsi'],
                $body['kota'],
                $body['kecamatan']
            );

            // Jika tidak dapat mengambil ID RajaOngkir, set default 0
            if (!$rajaIDs) {
                error_log("Warning: Could not fetch RajaOngkir IDs for: " .
                    $body['provinsi'] . ', ' . $body['kota'] . ', ' . $body['kecamatan']);
                $rajaIDs = [
                    'ProvinceID' => 0,
                    'CityID' => 0,
                    'DistrictID' => 0,
                    'SubDistrictID' => 0,
                ];
            }

            // Jika set as default, reset default yang lain
            if (isset($body['is_default']) && $body['is_default'] == 1) {
                $existingAddresses = Address::get()->filter(['MemberID' => $member->ID]);
                foreach ($existingAddresses as $addr) {
                    $addr->IsDefault = 0;
                    $addr->write();
                }
            }

            $address = Address::create();
            $address->Title        = $body['title'];
            $address->Alamat       = $body['alamat'];
            $address->KodePos      = $body['kodepos'];
            $address->Kecamatan    = $body['kecamatan'];
            $address->Kota         = $body['kota'];
            $address->Provinsi     = $body['provinsi'];
            $address->IsDefault    = $body['is_default'] ?? 0;
            $address->MemberID     = $member->ID;

            // Set ID RajaOngkir
            $address->ProvinceID    = $rajaIDs['ProvinceID'];
            $address->CityID        = $rajaIDs['CityID'];
            $address->DistrictID    = $rajaIDs['DistrictID'];
            $address->SubDistrictID = $rajaIDs['SubDistrictID'];

            $address->write();

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Address created successfully',
                'data'    => [
                    'ID'              => $address->ID,
                    'ClassName'       => $address->ClassName,
                    'RecordClassName' => $address->RecordClassName,
                    'Title'           => $address->Title,
                    'Alamat'          => $address->Alamat,
                    'KodePos'         => $address->KodePos,
                    'Kecamatan'       => $address->Kecamatan,
                    'Kota'            => $address->Kota,
                    'Provinsi'        => $address->Provinsi,
                    'IsDefault'       => $address->IsDefault,
                    'MemberID'        => $address->MemberID,
                    'ProvinceID'      => $address->ProvinceID,
                    'CityID'          => $address->CityID,
                    'DistrictID'      => $address->DistrictID,
                    'SubDistrictID'   => $address->SubDistrictID,
                    'Created'         => $address->Created,
                    'LastEdited'      => $address->LastEdited,
                ]
            ], 201);
        } catch (\Exception $e) {
            error_log("Error creating address: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Failed to create address'], 500);
        }
    }

    public function setDefaultAddress(HTTPRequest $request)
    {
        // Hanya allow PUT method
        if ($request->httpMethod() !== 'PUT') {
            return $this->jsonError('Method not allowed', 405);
        }

        // Authorize request
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) {
            return $error;
        }

        // Get address ID from URL
        $addressId = $request->param('ID');
        if (!$addressId) {
            return $this->jsonError('Address ID is required', 400);
        }

        try {
            // Cari alamat yang akan dijadikan default
            $address = Address::get()
                ->filter([
                    'ID' => $addressId,
                    'MemberID' => $member->ID
                ])
                ->first();

            if (!$address) {
                return $this->jsonError('Address not found or access denied', 404);
            }

            // Start transaction
            DB::get_conn()->transactionStart();

            // Set semua alamat member menjadi tidak default
            DB::query("UPDATE Address SET IsDefault = 0 WHERE MemberID = " . $member->ID);

            // Set alamat yang dipilih menjadi default
            $address->IsDefault = 1;
            $address->write();

            // Commit transaction
            DB::get_conn()->transactionEnd();

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Default address updated successfully',
                'data' => [
                    'ID' => $address->ID,
                    'Title' => $address->Title,
                    'Alamat' => $address->Alamat,
                    'IsDefault' => $address->IsDefault
                ]
            ]);
        } catch (\Exception $e) {
            DB::get_conn()->transactionRollback();
            error_log('Set default address error: ' . $e->getMessage());
            return $this->jsonError('Failed to set default address: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Hapus alamat member
     * DELETE /api/addresses/{id}
     */
    public function deleteMemberAddress(HTTPRequest $request)
    {
        // Hanya allow DELETE method
        if ($request->httpMethod() !== 'DELETE') {
            return $this->jsonError('Method not allowed', 405);
        }

        // Authorize request
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) {
            return $error;
        }

        // Get address ID from URL
        $addressId = $request->param('ID');
        if (!$addressId) {
            return $this->jsonError('Address ID is required', 400);
        }

        try {
            // Cek jumlah alamat member
            $totalAddresses = Address::get()
                ->filter('MemberID', $member->ID)
                ->count();

            if ($totalAddresses <= 1) {
                return $this->jsonError('Cannot delete the last remaining address', 400);
            }

            // Cari alamat yang akan dihapus
            $address = Address::get()
                ->filter([
                    'ID' => $addressId,
                    'MemberID' => $member->ID
                ])
                ->first();

            if (!$address) {
                return $this->jsonError('Address not found or access denied', 404);
            }

            // Start transaction
            DB::get_conn()->transactionStart();

            $wasDefault = $address->IsDefault;

            // Hapus alamat
            $address->delete();

            // Jika alamat yang dihapus adalah default, set alamat pertama sebagai default
            if ($wasDefault) {
                $firstAddress = Address::get()
                    ->filter('MemberID', $member->ID)
                    ->first();

                if ($firstAddress) {
                    $firstAddress->IsDefault = 1;
                    $firstAddress->write();
                }
            }

            // Commit transaction
            DB::get_conn()->transactionEnd();

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Address deleted successfully'
            ]);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::get_conn()->transactionRollback();

            error_log('Delete address error: ' . $e->getMessage());
            return $this->jsonError('Failed to delete address', 500);
        }
    }

    /**
     * Update alamat member
     * PUT /api/addresses/{id}
     */
    public function updateMemberAddress(HTTPRequest $request)
    {
        // Hanya allow PUT method
        if ($request->httpMethod() !== 'PUT') {
            return $this->jsonError('Method not allowed', 405);
        }

        // Authorize request
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) {
            return $error;
        }

        // Get address ID from URL
        $addressId = $request->param('ID');
        if (!$addressId) {
            return $this->jsonError('Address ID is required', 400);
        }

        // Get request body
        $body = json_decode($request->getBody(), true);
        if (!$body) {
            return $this->jsonError('Invalid JSON data', 400);
        }

        try {
            // Cari alamat yang akan diupdate
            $address = Address::get()
                ->filter([
                    'ID' => $addressId,
                    'MemberID' => $member->ID
                ])
                ->first();

            if (!$address) {
                return $this->jsonError('Address not found or access denied', 404);
            }

            // Validate required fields - sesuai dengan createMemberAddress
            $requiredFields = ['title', 'alamat', 'provinsi', 'kota', 'kecamatan', 'kodepos'];
            foreach ($requiredFields as $field) {
                if (empty($body[$field])) {
                    return $this->jsonError(ucfirst($field) . ' is required', 400);
                }
            }

            // Start transaction
            DB::get_conn()->transactionStart();

            // Ambil ID hierarki RajaOngkir berdasarkan nama lokasi - sama seperti create
            $rajaIDs = $this->fetchRajaOngkirIDsByHierarchy(
                $body['provinsi'],
                $body['kota'],
                $body['kecamatan']
            );

            // Jika tidak dapat mengambil ID RajaOngkir, set default 0
            if (!$rajaIDs) {
                error_log("Warning: Could not fetch RajaOngkir IDs for update: " .
                    $body['provinsi'] . ', ' . $body['kota'] . ', ' . $body['kecamatan']);
                $rajaIDs = [
                    'ProvinceID' => 0,
                    'CityID' => 0,
                    'DistrictID' => 0,
                    'SubDistrictID' => 0,
                ];
            }

            // Jika set as default, reset default yang lain
            if (isset($body['is_default']) && $body['is_default'] == 1) {
                $existingAddresses = Address::get()->filter(['MemberID' => $member->ID]);
                foreach ($existingAddresses as $addr) {
                    if ($addr->ID != $address->ID) { // Exclude current address
                        $addr->IsDefault = 0;
                        $addr->write();
                    }
                }
            }

            // Update fields - sesuai dengan createMemberAddress format
            $address->Title        = $body['title'];
            $address->Alamat       = $body['alamat'];
            $address->KodePos      = $body['kodepos'];
            $address->Kecamatan    = $body['kecamatan'];
            $address->Kota         = $body['kota'];
            $address->Provinsi     = $body['provinsi'];
            $address->IsDefault    = $body['is_default'] ?? $address->IsDefault; // Keep existing if not provided

            // Set ID RajaOngkir yang baru
            $address->ProvinceID    = $rajaIDs['ProvinceID'];
            $address->CityID        = $rajaIDs['CityID'];
            $address->DistrictID    = $rajaIDs['DistrictID'];
            $address->SubDistrictID = $rajaIDs['SubDistrictID'];

            $address->write();

            // Commit transaction
            DB::get_conn()->transactionEnd();

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Address updated successfully',
                'data' => [
                    'ID'              => $address->ID,
                    'ClassName'       => $address->ClassName,
                    'RecordClassName' => $address->RecordClassName,
                    'Title'           => $address->Title,
                    'Alamat'          => $address->Alamat,
                    'KodePos'         => $address->KodePos,
                    'Kecamatan'       => $address->Kecamatan,
                    'Kota'            => $address->Kota,
                    'Provinsi'        => $address->Provinsi,
                    'IsDefault'       => $address->IsDefault,
                    'MemberID'        => $address->MemberID,
                    'ProvinceID'      => $address->ProvinceID,
                    'CityID'          => $address->CityID,
                    'DistrictID'      => $address->DistrictID,
                    'SubDistrictID'   => $address->SubDistrictID,
                    'Created'         => $address->Created,
                    'LastEdited'      => $address->LastEdited,
                ]
            ]);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::get_conn()->transactionRollback();

            error_log('Update address error: ' . $e->getMessage());
            return $this->jsonError('Failed to update address: ' . $e->getMessage(), 500);
        }
    }

    private function fetchRajaOngkirIDsByHierarchy($provinsi, $kota, $kecamatan)
    {
        try {
            // 1. Fetch Province ID
            $provinceID = $this->getRajaOngkirID('province', $provinsi);
            if (!$provinceID) {
                error_log("Province not found: $provinsi");
                return false;
            }

            // 2. Fetch City ID
            $cityID = $this->getRajaOngkirID('city', $kota, $provinceID);
            if (!$cityID) {
                error_log("City not found: $kota in province $provinsi");
                return false;
            }

            // 3. Fetch District ID
            $districtID = $this->getRajaOngkirID('district', $kecamatan, $cityID);
            if (!$districtID) {
                error_log("District not found: $kecamatan in city $kota");
                return false;
            }

            // 4. Fetch SubDistrict ID (ambil yang pertama)
            // $subDistrictID = $this->getRajaOngkirID('sub-district', null, $districtID);
            // if (!$subDistrictID) {
            //     error_log("SubDistrict not found for district: $kecamatan");
            //     return false;
            // }
            $subDistrictID = null;

            return [
                'ProvinceID'    => $provinceID,
                'CityID'        => $cityID,
                'DistrictID'    => $districtID,
                'SubDistrictID' => $subDistrictID,
            ];
        } catch (\Exception $e) {
            error_log("Error in fetchRajaOngkirIDsByHierarchy: " . $e->getMessage());
            return false;
        }
    }

    private function getRajaOngkirID($type, $name = null, $parentID = null)
    {
        $url = match ($type) {
            'province'    => 'https://rajaongkir.komerce.id/api/v1/destination/province',
            'city'        => "https://rajaongkir.komerce.id/api/v1/destination/city/{$parentID}",
            'district'    => "https://rajaongkir.komerce.id/api/v1/destination/district/{$parentID}",
            'sub-district' => "https://rajaongkir.komerce.id/api/v1/destination/sub-district/{$parentID}",
            default       => null
        };

        if (!$url) return null;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "key: " . $this->rajaOngkirApiKey
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            error_log("RajaOngkir cURL error for $type: " . $err);
            return null;
        }

        if ($httpCode !== 200) {
            error_log("RajaOngkir HTTP error for $type: HTTP $httpCode");
            return null;
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['data'])) {
            error_log("RajaOngkir invalid response for $type: " . $response);
            return null;
        }

        // Untuk sub-district, ambil yang pertama saja
        if ($type === 'sub-district') {
            return isset($data['data'][0]['id']) ? $data['data'][0]['id'] : null;
        }

        // Untuk yang lain, cari berdasarkan nama (case insensitive)
        foreach ($data['data'] as $item) {
            if (isset($item['name']) && strcasecmp($item['name'], $name) === 0) {
                return $item['id'] ?? null;
            }
        }

        return null;
    }
}
