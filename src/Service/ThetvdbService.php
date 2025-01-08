<?php

namespace App\Service;

use http\Env\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class ThetvdbService
{
    // Clé d'API (v3 auth)
    //      f7e3c5fe794d565b471334c9c5ecaf96
    // Jeton d'accès en lecture à l'API (v4 auth)
    //      eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJmN2UzYzVmZTc5NGQ1NjViNDcxMzM0YzljNWVjYWY5NiIsInN1YiI6IjYyMDJiZjg2ZTM4YmQ4MDA5MWVjOWIzOSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.9-8i4TOkKXtPZE_nkXk1ZvAlbDYgAdtcrCR6R8Dv3Wg

    private HttpClientInterface $client;
    private string $api_key = "a1e4780f-729e-475e-a6a0-d4e8d0b494ce";

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function series(int $id): ?string
    {
        try {
            $response = $this->client->request('GET', 'https://api.thetvdb.com/series/' . $id, [
                'headers' => [
                    'accept: application/json',
                    'Authorization: Bearer a1e4780f-729e-475e-a6a0-d4e8d0b494ce'
                ]
            ]);
            return $response->getContent();
        } catch (Throwable $e) {
            return null;
        }
    }
}
