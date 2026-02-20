<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class FormController extends AbstractController
{
    private const FORM_MAP = [
        'rz-provision' => 'forms/rz_provision.html.twig',
        'rz-retire' => 'forms/rz_retire.html.twig',
        'rz-owner-confirm' => 'forms/rz_owner_confirm.html.twig',
        'admin-provision' => 'forms/admin_provision.html.twig',
        'admin-user-commitment' => 'forms/admin_user_commitment.html.twig',
        'admin-return' => 'forms/admin_return.html.twig',
        'admin-access-cleanup' => 'forms/admin_access_cleanup.html.twig',
    ];

    #[Route('/', name: 'app_index')]
    public function index(): Response
    {
        return $this->render('index.html.twig');
    }

    #[Route('/forms/{slug}', name: 'app_form')]
    public function form(string $slug): Response
    {
        $template = self::FORM_MAP[$slug] ?? null;

        if ($template === null) {
            throw new NotFoundHttpException("Formular '{$slug}' nicht gefunden.");
        }

        return $this->render($template);
    }
}
