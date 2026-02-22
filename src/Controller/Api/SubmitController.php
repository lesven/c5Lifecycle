<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\UseCase\SubmitEvidenceUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SubmitController
{
    public function __construct(
        private readonly SubmitEvidenceUseCase $submitEvidence,
    ) {}

    #[Route('/api/submit/{eventType}', name: 'api_submit', methods: ['POST'], requirements: ['eventType' => '[\w_-]+'])]
    public function __invoke(Request $request, string $eventType): JsonResponse
    {
        $eventType = str_replace('-', '_', $eventType);

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse([
                'error' => 'Ungültiger JSON-Body',
                'request_id' => '',
            ], 400);
        }

        $result = $this->submitEvidence->execute($eventType, $data);

        if (!$result->success) {
            $payload = [
                'error' => $result->error,
                'request_id' => $result->requestId,
            ];

            if ($result->validationErrors !== null) {
                $payload['fields'] = $result->validationErrors;
            }

            if ($result->mailSent) {
                $payload['mail_sent'] = true;
            }

            if ($result->netboxErrorTrace !== null) {
                $payload['netbox_error_trace'] = $result->netboxErrorTrace;
            }

            return new JsonResponse($payload, $result->httpStatus);
        }

        $response = [
            'success' => true,
            'request_id' => $result->requestId,
            'mail_sent' => true,
            'event_type' => $result->eventType,
            'asset_id' => $result->assetId,
            'jira_ticket' => $result->jiraTicket,
            'netbox_synced' => $result->netboxSynced,
        ];

        if ($result->netboxStatus !== null) {
            $response['netbox_status'] = $result->netboxStatus;
        }
        if ($result->netboxError !== null) {
            $response['netbox_error'] = $result->netboxError;
        }
        if ($result->netboxErrorTrace !== null) {
            $response['netbox_error_trace'] = $result->netboxErrorTrace;
        }

        return new JsonResponse($response);
    }
}
