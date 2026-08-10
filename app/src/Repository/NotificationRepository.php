<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @param string[] $titles
     *
     * @return Notification[]
     */
    public function findPendingEmailReminders(
        array $titles,
        int $limit = 50,
        int $maxAttempts = 3,
        ?string $recipient = null
    ): array {
        $queryBuilder = $this->createQueryBuilder('notification')
            ->innerJoin('notification.user', 'user')
            ->leftJoin('notification.assignment', 'assignment')
            ->leftJoin('assignment.course', 'course')
            ->andWhere('notification.channel = :channel')
            ->andWhere('notification.emailSentAt IS NULL')
            ->andWhere('notification.emailAttempts < :maxAttempts')
            ->andWhere('notification.title IN (:titles)')
            ->andWhere('user.deletedAt IS NULL')
            ->andWhere('user.emailNotificationsEnabled = :enabled')
            ->andWhere('user.email IS NOT NULL')
            ->andWhere("user.email <> ''")
            ->andWhere('(assignment.id IS NULL OR (assignment.deletedAt IS NULL AND assignment.status <> :completed AND course.deletedAt IS NULL))')
            ->setParameter('channel', 'in_app')
            ->setParameter('maxAttempts', $maxAttempts)
            ->setParameter('titles', $titles)
            ->setParameter('enabled', true)
            ->setParameter('completed', 'completed')
            ->orderBy('notification.createdAt', 'ASC')
            ->setMaxResults(max(1, $limit));

        if ($recipient !== null && $recipient !== '') {
            $queryBuilder
                ->andWhere('LOWER(user.email) = :recipient')
                ->setParameter('recipient', mb_strtolower($recipient));
        }

        return $queryBuilder->getQuery()->getResult();
    }

    //    /**
    //     * @return Notification[] Returns an array of Notification objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('n')
    //            ->andWhere('n.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('n.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Notification
    //    {
    //        return $this->createQueryBuilder('n')
    //            ->andWhere('n.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
