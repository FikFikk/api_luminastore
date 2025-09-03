<?php

namespace App\Controllers;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Controller;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Upload;
use SilverStripe\Control\Director;
use App\Models\AppMember;
use App\Models\APIClient;
use App\Models\AppsToken;
use SilverStripe\Security\MemberAuthenticator\MemberAuthenticator;

class UserAPIController extends BaseController
{
    private static $allowed_actions = [
        'index',
        'getUser',
        'update_profile',
        'update_password',
        'delete_user',
    ];

    public function index(HTTPRequest $request)
    {
        return $this->getUser($request);
    }

    /**
     * Utility untuk respon JSON sukses
     */
    protected function jsonResponse($data, $code = 200)
    {
        return HTTPResponse::create(json_encode($data), $code)
            ->addHeader('Content-Type', 'application/json');
    }

    protected function jsonError($message, $code = 400)
    {
        return $this->jsonResponse(['message' => $message], $code);
    }

    protected function checkMemberNotDeleted($member)
    {
        if (!$member) {
            return $this->jsonError('User not found', 404);
        }

        if (!empty($member->DeletedAt)) {
            return $this->jsonError('User has been deleted', 410); // 410 Gone
        }

        return null; // OK
    }

    /**
     * Cek API Key & Access Token dari Header
     */
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

        if (!empty($member->DeletedAt)) {
            return $this->jsonError('User has been deleted', 410); // 410 Gone
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

        $member = $tokenRecord->Member();

        // Cek apakah user sudah dihapus
        $deletedCheck = $this->checkMemberNotDeleted($member);
        if ($deletedCheck instanceof HTTPResponse) {
            return [null, $deletedCheck];
        }

        return [$member, null];
    }

    /**
     * GET User Profile
     */
    public function getUser(HTTPRequest $request)
    {
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        $photoProfileUrls = null;

        if ($member->PhotoProfile()->exists()) {
            $image = $member->PhotoProfile();

            $photoProfileUrls = [
                'original' => Director::absoluteURL($image->getURL()),
                'small'    => Director::absoluteURL($image->Fit(150, 150)->getURL()),
                'medium'   => Director::absoluteURL($image->Fit(300, 300)->getURL()),
            ];
        }

        // ambil addresses
        $addresses = [];
        foreach ($member->Addresses() as $address) {
            $addresses[] = [
                'ID'           => $address->ID,
                'Title'        => $address->Title,
                'Alamat'       => $address->Alamat,
                'KodePos'      => $address->KodePos,
                'Kecamatan'    => $address->Kecamatan,
                'Kota'         => $address->Kota,
                'Provinsi'     => $address->Provinsi,
                'IsDefault'    => (bool)$address->IsDefault,
                'MemberID'     => $address->MemberID,
                'ProvinceID'   => $address->ProvinceID,
                'CityID'       => $address->CityID,
                'DistrictID'   => $address->DistrictID,
                'SubDistrictID'=> $address->SubDistrictID,
                'Created'      => $address->Created,
                'LastEdited'   => $address->LastEdited
            ];
        }

        $data = [
            'ID'           => $member->ID,
            'Email'        => $member->Email,
            'FirstName'    => $member->FirstName,
            'Surname'      => $member->Surname,
            'PhoneNumber'  => $member->PhoneNumber,
            'PhotoProfile' => $photoProfileUrls,
            'Addresses'    => $addresses
        ];

        return $this->jsonResponse($data);
    }


    /**
     * UPDATE Profile
     */
    public function update_profile(HTTPRequest $request)
    {
        try {
            list($member, $error) = $this->authorizeRequest($request);
            if ($error) return $error;

            $isJson = strpos($request->getHeader('Content-Type'), 'application/json') !== false;
            $data = $isJson ? json_decode($request->getBody(), true) : $request->postVars();

            // Update field teks
            if (isset($data['FirstName'])) {
                $member->FirstName = $data['FirstName'];
            }
            if (isset($data['Surname'])) {
                $member->Surname = $data['Surname'];
            }
            if (isset($data['PhoneNumber'])) {
                $member->PhoneNumber = $data['PhoneNumber'];
            }

            // Handle upload foto profil
            $file = $request->postVar('PhotoProfile');
            if ($file && isset($file['tmp_name']) && $file['size'] > 0) {
                $upload = new Upload();
                $upload->getValidator()->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif']);

                $image = Image::create();
                $upload->loadIntoFile($file, $image, 'PhotoProfile');

                if ($image->isInDB()) {
                    $image->write();
                    $member->PhotoProfileID = $image->ID;
                }
            }

            $member->write();

            $photoProfileUrls = null;
            if ($member->PhotoProfile()->exists()) {
                $image = $member->PhotoProfile();
                $photoProfileUrls = [
                    'original' => Director::absoluteURL($image->getURL()) . '?v=' . time(),
                    'small'    => Director::absoluteURL($image->Fit(150, 150)->getURL()) . '?v=' . time(),
                    'medium'   => Director::absoluteURL($image->Fit(300, 300)->getURL()) . '?v=' . time(),
                ];
            }

            return $this->jsonResponse([
                'message' => 'Profile updated successfully',
                'data'    => [
                    'Email'           => $member->Email,
                    'FirstName'       => $member->FirstName,
                    'Surname'         => $member->Surname,
                    'PhoneNumber'     => $member->PhoneNumber,
                    'PhotoProfile'    => $photoProfileUrls
                ]
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * UPDATE Password
     */
    public function update_password(HTTPRequest $request)
    {
        list($member, $error) = $this->authorizeRequest($request);
        if ($error) return $error;

        $data = (strpos($request->getHeader('Content-Type'), 'application/json') !== false)
            ? json_decode($request->getBody(), true)
            : $request->postVars();

        if (!$data || !isset($data['NewPassword'])) {
            return $this->jsonError('NewPassword field is required', 400);
        }

        try {
            // Validasi password lama
            if (
                !isset($data['CurrentPassword']) ||
                !(new MemberAuthenticator())->checkPassword($member, $data['CurrentPassword'])->isValid()
            ) {
                return $this->jsonError('INVALID_CURRENT_PASSWORD', 400);
            }

            // Update password baru
            $member->changePassword($data['NewPassword']);
            $member->write();

            return $this->jsonResponse(['message' => 'Password updated successfully']);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }

    public function delete_user(HTTPRequest $request)
    {
        try {
            list($member, $error) = $this->authorizeRequest($request);
            if ($error) return $error;

            // Cek apakah user sudah dihapus sebelumnya
            if ($member->DeletedAt) {
                return $this->jsonError('User already deleted', 400);
            }

            $member->DeletedAt = date('Y-m-d H:i:s'); // set waktu soft delete
            $member->write();

            return $this->jsonResponse([
                'message' => 'User deleted successfully',
                'deleted_at' => $member->DeletedAt
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
