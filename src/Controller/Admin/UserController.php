<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Persistence\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('', name: 'admin_users_index', methods: ['GET'])]
    public function index(): Response
    {
        $users = $this->userRepository->findAll();

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $errors = $this->validateUserForm($request);

            if (empty($errors)) {
                $email = trim($request->request->getString('email'));
                $displayName = trim($request->request->getString('display_name'));
                $plainPassword = $request->request->getString('password');
                $role = $request->request->getString('role', 'ROLE_USER');

                // Check duplicate email
                if ($this->userRepository->findByEmail($email) !== null) {
                    $errors['email'] = 'Diese E-Mail-Adresse wird bereits verwendet.';
                } else {
                    $roles = $role === 'ROLE_ADMIN' ? ['ROLE_ADMIN'] : ['ROLE_USER'];

                    $user = new User($email, $displayName, '', $roles);
                    $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);

                    $this->userRepository->save($user);

                    $this->addFlash('success', sprintf('Benutzer „%s" wurde angelegt.', $displayName));

                    return $this->redirectToRoute('admin_users_index');
                }
            }

            return $this->render('admin/users/form.html.twig', [
                'mode' => 'new',
                'errors' => $errors,
                'form_data' => [
                    'email' => $request->request->getString('email'),
                    'display_name' => $request->request->getString('display_name'),
                    'role' => $request->request->getString('role', 'ROLE_USER'),
                ],
            ]);
        }

        return $this->render('admin/users/form.html.twig', [
            'mode' => 'new',
            'errors' => [],
            'form_data' => ['email' => '', 'display_name' => '', 'role' => 'ROLE_USER'],
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            throw $this->createNotFoundException('Benutzer nicht gefunden.');
        }

        if ($request->isMethod('POST')) {
            $errors = $this->validateUserForm($request, isEdit: true);

            if (empty($errors)) {
                $email = trim($request->request->getString('email'));
                $displayName = trim($request->request->getString('display_name'));
                $role = $request->request->getString('role', 'ROLE_USER');
                $plainPassword = $request->request->getString('password');

                // Check duplicate email (only if changed)
                if ($email !== $user->getEmail() && $this->userRepository->findByEmail($email) !== null) {
                    $errors['email'] = 'Diese E-Mail-Adresse wird bereits verwendet.';
                } else {
                    $user->setEmail($email);
                    $user->setDisplayName($displayName);
                    $roles = $role === 'ROLE_ADMIN' ? ['ROLE_ADMIN'] : ['ROLE_USER'];
                    $user->setRoles($roles);

                    if ($plainPassword !== '') {
                        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                        $user->setPassword($hashedPassword);
                    }

                    $this->userRepository->save($user);

                    $this->addFlash('success', sprintf('Benutzer „%s" wurde aktualisiert.', $displayName));

                    return $this->redirectToRoute('admin_users_index');
                }
            }

            return $this->render('admin/users/form.html.twig', [
                'mode' => 'edit',
                'user' => $user,
                'errors' => $errors,
                'form_data' => [
                    'email' => $request->request->getString('email'),
                    'display_name' => $request->request->getString('display_name'),
                    'role' => $request->request->getString('role', 'ROLE_USER'),
                ],
            ]);
        }

        return $this->render('admin/users/form.html.twig', [
            'mode' => 'edit',
            'user' => $user,
            'errors' => [],
            'form_data' => [
                'email' => $user->getEmail(),
                'display_name' => $user->getDisplayName(),
                'role' => $user->isAdmin() ? 'ROLE_ADMIN' : 'ROLE_USER',
            ],
        ]);
    }

    #[Route('/{id}/toggle-active', name: 'admin_users_toggle_active', methods: ['POST'])]
    public function toggleActive(int $id): Response
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            throw $this->createNotFoundException('Benutzer nicht gefunden.');
        }

        // Prevent deactivating yourself
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if ($user->getId() === $currentUser->getId()) {
            $this->addFlash('error', 'Sie können Ihren eigenen Account nicht deaktivieren.');

            return $this->redirectToRoute('admin_users_index');
        }

        $user->setActive(!$user->isActive());
        $this->userRepository->save($user);

        $status = $user->isActive() ? 'aktiviert' : 'deaktiviert';
        $this->addFlash('success', sprintf('Benutzer „%s" wurde %s.', $user->getDisplayName(), $status));

        return $this->redirectToRoute('admin_users_index');
    }

    /**
     * @return array<string, string>
     */
    private function validateUserForm(Request $request, bool $isEdit = false): array
    {
        $errors = [];

        $email = trim($request->request->getString('email'));
        if ($email === '') {
            $errors['email'] = 'E-Mail-Adresse ist erforderlich.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Ungültige E-Mail-Adresse.';
        }

        $displayName = trim($request->request->getString('display_name'));
        if ($displayName === '') {
            $errors['display_name'] = 'Anzeigename ist erforderlich.';
        }

        $password = $request->request->getString('password');
        if (!$isEdit && $password === '') {
            $errors['password'] = 'Passwort ist erforderlich.';
        } elseif ($password !== '' && strlen($password) < 8) {
            $errors['password'] = 'Passwort muss mindestens 8 Zeichen lang sein.';
        }

        $role = $request->request->getString('role');
        if (!in_array($role, ['ROLE_USER', 'ROLE_ADMIN'], true)) {
            $errors['role'] = 'Ungültige Rolle.';
        }

        return $errors;
    }
}
