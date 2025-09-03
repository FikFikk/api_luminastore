<?php

namespace App\Controllers;

use App\Models\APIClient;
use App\Models\AppMember;
use App\Models\AppsToken;
use SilverStripe\Dev\Debug;
use Psr\Log\LoggerInterface;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ArrayData;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\IdentityStore;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\MemberAuthenticator\MemberAuthenticator;

class AuthAPIController extends BaseController
{
    private static $allowed_actions = [
        'register',
        'login',
        'logout',
        'forgot_password',
        'set_password'
    ];

    private static $url_handlers = [
        'POST api/auth/register'         => 'register',
        'POST api/auth/login'            => 'login',
        'GET  api/auth/logout'           => 'logout',
        'POST api/auth/forgot_password'  => 'forgot_password',
        'POST api/auth/set_password'     => 'set_password'
    ];


    /* ===================== Helpers ===================== */

    protected function jsonResponse($data, $code = 200)
    {
        return HTTPResponse::create(json_encode($data), $code)
            ->addHeader('Content-Type', 'application/json');
    }

    protected function jsonError($message, $code = 400)
    {
        return $this->jsonResponse(['message' => $message], $code);
    }

    /** Ambil APIClient dari header X-API-Key */
    protected function getApiClient(HTTPRequest $request): ?APIClient
    {
        $key = $request->getHeader('X-API-Key');
        if (!$key) {
            return null;
        }
        return APIClient::get()->filter('API_KEY', $key)->first();
    }

    /** Wajib ada API Key yang valid */
    protected function requireApiClient(HTTPRequest $request)
    {
        $client = $this->getApiClient($request);
        if (!$client) {
            return $this->jsonError('Invalid API Key', 403);
        }
        return $client;
    }

    /** Ambil Bearer token dari header Authorization */
    protected function getBearerToken(HTTPRequest $request): ?string
    {
        $header = $request->getHeader('Authorization');
        if ($header && strpos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }
        return null;
    }

    /** Buat token baru dan simpan ke tabel AppsToken */
    protected function issueToken(AppMember $member, APIClient $client): string
    {
        $token = bin2hex(random_bytes(32));

        $row = AppsToken::create();
        $row->AccessToken  = $token;
        $row->MemberID     = $member->ID;
        $row->APIClientID  = $client->ID;
        $row->write();

        return $token;
    }

    protected function checkMemberNotDeleted($member)
    {
        if (!$member) {
            return $this->jsonError('User not found', 404);
        }

        if (!empty($member->DeletedAt)) {
            return $this->jsonError('User has been deleted', 410); // 410 Gone
        }

        return null; // Lolos
    }

    /** Cari member via token + API client (token terikat ke client) */
    protected function findMemberByToken(string $token, APIClient $client): ?AppMember
    {
        $row = AppsToken::get()
            ->filter([
                'AccessToken' => $token,
                'APIClientID' => $client->ID
            ])->first();

        return $row ? $row->Member() : null;
    }

    /* ===================== Actions ===================== */

    /** POST /api/auth/register */
    public function register($request)
    {
        try {
            $email = $request->postVar('Email');
            $password = $request->postVar('Password');

            $existing = AppMember::get()->filter('Email', $email)->first();

            if ($existing) {
                if (empty($existing->DeletedAt)) {
                    throw new ValidationException('Email already registered', 409);
                }

                // Cek jika password baru sama dengan password lama
                $encryptor = \SilverStripe\Security\PasswordEncryptor::create_for_algorithm($existing->PasswordEncryption);
                $isSame = $encryptor->check($existing->Password, $password, $existing->Salt);

                if ($isSame) {
                    throw new ValidationException('Password cannot be the same as the previous one', 400);
                }

                // Restore akun lama
                $existing->FirstName    = $request->postVar('FirstName');
                $existing->PhoneNumber  = $request->postVar('PhoneNumber');
                $existing->Password     = $password;
                $existing->DeletedAt    = null;
                $existing->write();

                return $this->jsonResponse([
                    'status'    => 'success',
                    'message'   => 'Account restored successfully',
                    'member_id' => $existing->ID
                ], 200);
            }

            // Register baru
            $member = AppMember::create();
            $member->FirstName   = $request->postVar('FirstName');
            $member->Email       = $email;
            $member->PhoneNumber = $request->postVar('PhoneNumber');
            $member->Password    = $password;
            $member->write();

            return $this->jsonResponse([
                'status'    => 'success',
                'message'   => 'Account created successfully',
                'member_id' => $member->ID
            ], 201);
        } catch (ValidationException $e) {
            return $this->jsonResponse([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        } catch (\Throwable $e) {
            return $this->jsonResponse([
                'status'  => 'error',
                'message' => 'Internal server error',
                'debug'   => $e->getMessage()
            ], 500);
        }
    }



    /** POST /api/auth/login */
    public function login(HTTPRequest $request)
    {
        $client = $this->requireApiClient($request);
        if ($client instanceof HTTPResponse) return $client;

        $data = (strpos($request->getHeader('Content-Type'), 'application/json') !== false)
            ? json_decode($request->getBody(), true)
            : $request->postVars();

        $email = $data['Email'] ?? '';
        $pass  = $data['Password'] ?? '';

        $authenticator = new MemberAuthenticator();
        $member = $authenticator->authenticate([
            'Email' => $email,
            'Password' => $pass
        ], $request);

        if (!$member) {
            return $this->jsonError('Invalid email or password', 401);
        }

        $deletedCheck = $this->checkMemberNotDeleted($member);
        if ($deletedCheck instanceof HTTPResponse) {
            return $deletedCheck;
        }

        if (!($member instanceof AppMember)) {
            $member->ClassName = AppMember::class;
            $member->write();
            $member = AppMember::get()->byID($member->ID);
        }

        // Selalu buat token baru per login
        $accessToken = $this->issueToken($member, $client);

        singleton(IdentityStore::class)->logIn($member, false, $request);

        return $this->jsonResponse([
            'message' => 'Login successful',
            'access_token' => $accessToken
        ]);
    }

    /** GET /api/auth/logout */
    public function logout(HTTPRequest $request)
    {
        $client = $this->requireApiClient($request);
        if ($client instanceof HTTPResponse) return $client;

        $token = $this->getBearerToken($request);
        if (!$token) {
            return $this->jsonError('Missing access token', 401);
        }

        // Hapus baris token untuk client ini
        $row = AppsToken::get()->filter([
            'AccessToken' => $token,
            'APIClientID' => $client->ID
        ])->first();

        if (!$row || !$row->exists()) {
            return $this->jsonError('Invalid token', 401);
        }

        $row->delete();

        return $this->jsonResponse(['message' => 'Logout successful']);
    }

    /** POST /api/auth/forgot_password */
    public function forgot_password(HTTPRequest $request)
    {
        // Ambil API Client pertama (atau bisa filter sesuai kebutuhan)
        $apiClient = APIClient::get()->first();
        if (!$apiClient) {
            return $this->jsonError('API Client not found', 404);
        }
        $apiKey = $apiClient->API_KEY;

        $data = (strpos($request->getHeader('Content-Type'), 'application/json') !== false)
            ? json_decode($request->getBody(), true)
            : $request->postVars();

        $email = $data['Email'] ?? null;
        if (!$email) return $this->jsonError('Email is required');

        $member = Member::get()->filter('Email', $email)->first();
        if (!$member) return $this->jsonError('User not found', 404);

        if (!($member instanceof AppMember)) {
            $member->ClassName = AppMember::class;
            $member->write();
        }

        // Buat token reset
        $timestamp = time();
        $secret = getenv('APP_SECRET') ?: 'default_secret';
        $token = hash_hmac('sha256', $email . '|' . $timestamp, $secret);

        // Determine protocol (https if forwarded, otherwise server protocol)
        $protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ($_SERVER['REQUEST_SCHEME'] ?? 'http');

        // Then build reset link
        $resetLink = $protocol . '://' . $_SERVER['HTTP_HOST']
            . "/reset-password?email={$email}&ts={$timestamp}&token={$token}&key={$apiKey}";

        $emailBody = $this->generateForgotPasswordEmailBody($email, $resetLink);

        Email::create()
            ->setTo($email)
            ->setFrom(getenv('MAILER_FROM') ?: 'tes@gmail.com')
            ->setSubject('Reset your password')
            ->setBody($emailBody)
            ->send();

        return $this->jsonResponse(['message' => 'Reset password email sent']);
    }


    /** POST /api/auth/set_password */
    public function set_password(HTTPRequest $request): HTTPResponse
    {
        // ... (Validasi token dan parameter Anda sudah benar)
        $client = $this->requireApiClient($request);
        if ($client instanceof HTTPResponse) return $client;

        $data = json_decode($request->getBody(), true);
        $email = $data['Email'] ?? null;
        $token = $data['Token'] ?? null;
        $ts = $data['Timestamp'] ?? null;
        $newPassword = $data['NewPassword'] ?? null;

        if (!$email || !$token || !$ts || !$newPassword) {
            return $this->jsonError('Missing parameters', 400);
        }
        if ((time() - (int)$ts) > 3600) {
            return $this->jsonError('Token expired', 400);
        }

        $secret = getenv('APP_SECRET') ?: 'default_secret';
        $expectedToken = hash_hmac('sha256', $email . '|' . $ts, $secret);
        if (!hash_equals($expectedToken, $token)) {
            return $this->jsonError('Invalid token', 401);
        }

        try {
            $member = Member::get()->filter('Email', $email)->first();
            if (!$member) {
                return $this->jsonError('Pengguna dengan email ini tidak ditemukan.', 404);
            }

            if (!($member instanceof AppMember)) {
                $member->ClassName = AppMember::class;
            }

            $member->changePassword($newPassword);
            $member->write();
        } catch (\SilverStripe\ORM\ValidationException $e) {
            $errorMessage = $e->getResult()->getMessages()[0]['message']
                ?? 'Password baru tidak boleh sama dengan password yang pernah digunakan.';

            return $this->jsonError($errorMessage, 400);
        } catch (\Throwable $e) {
            Injector::inst()->get(LoggerInterface::class)->error($e->getMessage());
            return $this->jsonError('Terjadi kesalahan internal pada sistem. Tim kami telah diberitahu.', 500);
        }
        return $this->jsonResponse(['message' => 'Password Anda telah berhasil diubah.']);
    }

    /* ===================== View Helper ===================== */

    private function generateForgotPasswordEmailBody($email, $resetLink)
    {
        $data = ArrayData::create([
            'Email'       => $email,
            'ResetLink'   => $resetLink,
            'Timestamp'   => date('j F Y, H:i:s'),
            'CurrentYear' => date('Y')
        ]);
        $viewer = SSViewer::create('Email/ForgotPasswordEmail');
        return $viewer->process($data);
    }
}
