<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Service\FieldLabelRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class FieldLabelsController
{
    public function __construct(
        private readonly FieldLabelRegistry $labelRegistry,
    ) {
    }

    #[Route('/api/field-labels', name: 'api_field_labels', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse($this->labelRegistry->all());
    }
}
