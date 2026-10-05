<?php

declare(strict_types=1);

namespace App\Controller;

use App\Install\InstallState;
use App\Security\UserProvisioner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InstallController extends AbstractController
{
    // Open to whoever arrives first; the route closes itself once a user exists.
    #[Route('/install', name: 'app_install')]
    public function __invoke(
        Request $request,
        InstallState $state,
        UserProvisioner $provisioner,
        Security $security,
    ): Response {
        if ($state->isInstalled()) {
            return $this->redirectToRoute('app_login');
        }

        $error = null;
        $email = (string) $request->request->get('email');

        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password');

            try {
                if ($password !== (string) $request->request->get('password_confirm')) {
                    throw new \InvalidArgumentException('Passwords do not match.');
                }

                $user = $provisioner->create($email, $password);
                $security->login($user, 'form_login', 'main');

                return $this->redirectToRoute('admin');
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('install/index.html.twig', [
            'error' => $error,
            'email' => $email,
        ]);
    }
}
