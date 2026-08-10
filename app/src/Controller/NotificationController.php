<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    #[Route(name: 'app_notification_index', methods: ['GET'])]
    public function index(NotificationRepository $notificationRepository): Response
    {
        $user = $this->getCurrentUser();

        return $this->render('notification/index.html.twig', [
            'notifications' => $notificationRepository->findBy(
                ['user' => $user, 'channel' => 'in_app'],
                ['createdAt' => 'DESC']
            ),
        ]);
    }

    #[Route('/{id}/read', name: 'app_notification_read', methods: ['POST'])]
    public function markAsRead(
        Request $request,
        Notification $notification,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessToNotificationOwnerOnly($notification);

        if (
            $notification->getChannel() === 'in_app'
            && $this->isCsrfTokenValid('read'.$notification->getId(), $request->getPayload()->getString('_token'))
        ) {
            $notification->setStatus('read');
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_notification_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/read-all', name: 'app_notification_read_all', methods: ['POST'])]
    public function markAllAsRead(
        Request $request,
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getCurrentUser();

        if ($this->isCsrfTokenValid('read_all_notifications', $request->getPayload()->getString('_token'))) {
            $notifications = $notificationRepository->findBy([
                'user' => $user,
                'channel' => 'in_app',
                'status' => 'unread',
            ]);

            foreach ($notifications as $notification) {
                $notification->setStatus('read');
            }

            $entityManager->flush();
        }

        return $this->redirectToRoute('app_notification_index', [], Response::HTTP_SEE_OTHER);
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        return $user;
    }

    private function denyAccessToNotificationOwnerOnly(Notification $notification): void
    {
        $user = $this->getCurrentUser();

        if ($notification->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You cannot access this notification.');
        }
    }
}
