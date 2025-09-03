<?php

namespace App\Services;

use SilverStripe\Core\Environment;

class RajaOngkirService
{
    protected $apiKey;
    protected $baseUrl = "https://api.rajaongkir.com/starter";

    public function __construct()
    {
        // Simpan API key di .env
        $this->apiKey = Environment::getEnv('RAJAONGKIR_API_KEY');
    }

    protected function request($endpoint, $params = [])
    {
        $url = $this->baseUrl . $endpoint;

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [
                "content-type: application/x-www-form-urlencoded",
                "key: {$this->apiKey}"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return ["error" => $err];
        }

        return json_decode($response, true);
    }

    public function getCost($courier, $origin, $destination, $weight)
    {
        $response = $this->request("/cost", [
            "origin" => $origin,
            "destination" => $destination,
            "weight" => $weight,
            "courier" => $courier,
        ]);

        $costs = [];
        if (isset($response['rajaongkir']['results'][0]['costs'])) {
            foreach ($response['rajaongkir']['results'][0]['costs'] as $service) {
                $costs[] = [
                    "service" => $service['service'],
                    "description" => $service['description'],
                    "cost" => $service['cost'][0]['value'],
                    "etd" => $service['cost'][0]['etd']
                ];
            }
        }

        return $costs;
    }
}
