<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/preferences')]
class PreferenceController extends AbstractController
{
    #[Route('', name: 'app_preferences', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('update_preferences', $request->getPayload()->getString('_token'))) {
                $user->setEmailNotificationsEnabled($request->getPayload()->has('email_notifications_enabled'));
                $user->ensureEmailUnsubscribeToken();
                $entityManager->flush();

                $this->addFlash('success', 'Notification preferences updated.');
            }

            return $this->redirectToRoute('app_preferences');
        }

        $user->ensureEmailUnsubscribeToken();
        $entityManager->flush();

        return $this->render('preferences/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/email/unsubscribe/{token}', name: 'app_email_unsubscribe', methods: ['GET'])]
    public function unsubscribe(string $token, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepository->findOneBy(['emailUnsubscribeToken' => $token]);

        if (!$user instanceof User || $user->isDeleted()) {
            throw $this->createNotFoundException('Invalid unsubscribe link.');
        }

        $user->setEmailNotificationsEnabled(false);
        $entityManager->flush();

        return $this->render('preferences/unsubscribed.html.twig', [
            'user' => $user,
        ]);
    }
}
