<?php
namespace App\Controllers;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;
use App\Services\RajaOngkirService;

class RajaOngkirController extends Controller
{
    private static $allowed_actions = ['cities','districts'];

    public function cities(HTTPRequest $request)
    {
        $provinceId = $request->param('ID');
        $service = RajaOngkirService::create();
        $cities = $service->getCities($provinceId);

        $data = [];
        foreach ($cities as $city) {
            $data[$city['city_id']] = $city['city_name'];
        }
        return HTTPResponse::create(json_encode($data))
            ->addHeader('Content-Type', 'application/json');
    }

    public function districts(HTTPRequest $request)
    {
        $cityId = $request->param('ID');
        $service = RajaOngkirService::create();
        $districts = $service->getDistricts($cityId);

        $data = [];
        foreach ($districts as $dist) {
            $data[$dist['subdistrict_id']] = $dist['subdistrict_name'];
        }
        return HTTPResponse::create(json_encode($data))
            ->addHeader('Content-Type', 'application/json');
    }
}
