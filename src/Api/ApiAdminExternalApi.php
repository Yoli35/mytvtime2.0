<?php

namespace App\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

#[Route('/api/admin/external', name: 'api_admin_external_api_')]
readonly class ApiAdminExternalApi
{
    public function __construct(
        private HttpClientInterface $client,
    )
    {
    }

    #[Route('/data/gouv', name: 'data_gouv', methods: ['POST'])]
    public function dataGouv(Request $request): JsonResponse
    {
        $payload = $request->getPayload();
        $url = $payload->get('url');
        try {
            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'accept: application/json',
                ]
            ]);
            return new JsonResponse($response->toArray());
        } catch (Throwable $e) {
            $code = $e->getCode();
            $message = $e->getMessage();
            return new JsonResponse(['status' => 'error', 'code' => $code,'message' => $message]);
        }
    }
}