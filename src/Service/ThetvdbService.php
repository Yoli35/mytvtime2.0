<?php

namespace App\Service;

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
        return $this->getResults("https://api4.thetvdb.com/v4/series/" . $id);
    }

    public function seriesExtended(int $id): ?string
    {
        return $this->getResults("https://api4.thetvdb.com/v4/series/$id/extended?meta=episodes&short=false");
    }

    private function getResults(string $url): ?string
    {
        try {
            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'accept: application/json',
                    'Authorization: Bearer ' . "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZ2UiOiIiLCJhcGlrZXkiOiJhMWU0NzgwZi03MjllLTQ3NWUtYTZhMC1kNGU4ZDBiNDk0Y2UiLCJjb21tdW5pdHlfc3VwcG9ydGVkIjp0cnVlLCJleHAiOjE3ODk3Njk0NTUsImdlbmRlciI6IiIsImhpdHNfcGVyX2RheSI6MTAwMDAwMDAwLCJoaXRzX3Blcl9tb250aCI6MTAwMDAwMDAwLCJpZCI6IjIzNzc5MTkiLCJpc19tb2QiOmZhbHNlLCJpc19zeXN0ZW1fa2V5IjpmYWxzZSwiaXNfdHJ1c3RlZCI6ZmFsc2UsInBpbiI6IkFGQTZBNTE4Iiwicm9sZXMiOltdLCJ0ZW5hbnQiOiJ0dmRiIiwidXVpZCI6IiJ9.aG5X2TU-EOGD5uPHctZNY9G1znF2bpovUg0Im-eflBoL4mBLTN3kPO2g5o6XkJ8f1_OJHvqLuPfz_vfyOGVpFHDIoNQx2I607B1yc4gVrbpL8tynxSbwW_6E1o2Zlv6a0TZGEGvM9eqIraYZ65eMm-94CSqlLXiut0cdDAb56JFnEeOjZib-vvC4ioecXAA3ZxARUCfYQtHk9yEy4hzpz3sCCaGhWmaU5okIh0To6qNqVbIurosFGZQF5bYPGjm_1U6Z7yMoOtvBbdv1vb02X9K3SK_XbxGGLH_1NKRPGv1qnsDEpjCMa5uRe--OVhDQlLmH3zHkH009NQGHNHb6iSJ8-07EXmW1QLjYHeg0fnQhiFtDxnR20S7hyIrcJLUS-FII3QQdDZ_Zc23cb_6GfwyRhJBdU7vyZYSUyaiUX63B89UcnFCOJ0QK4bZ11kHiPVfLJMgvWK4ph_c7L7HqmkZrY8_or6IQ9AcA_HeWNoijAM1MCW6BbceLMm2Etzw_5apvilYxz9DOyJ1NXiUwHoLFijV8l7V-Z91_w-8EMZ-u970WEi7sw5JmGem-2XXsqO02iZJ45kc0h9aMXO8t4ebjgDCw_alSjBxEWkKJ7MJFENwtHSzCU7ProhVGqNuVnW0VjHPGaOdMHGBOdtB5MxX4AfvNoIvv3B9FPc_hYwY"
                ]
            ]);
            return $response->getContent();
        } catch (Throwable $e) {
            return null;
        }
    }
}
