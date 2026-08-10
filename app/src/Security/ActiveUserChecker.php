<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        $this->checkUser($user);
    }

    public function checkPostAuth(UserInterface $user): void
    {
        $this->checkUser($user);
    }

    private function checkUser(UserInterface $user): void
    {
        if (!$user instanceof User || !$user->isDeleted()) {
            return;
        }

        $exception = new DisabledException('This account is no longer active.');
        $exception->setUser($user);

        throw $exception;
    }
}
