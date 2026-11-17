<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuses the login of accounts whose email address has not been verified.
 *
 * The check is done after authentication (checkPostAuth), i.e. once the password
 * has been validated: an attacker can't use it to find out which usernames exist.
 */
class UserChecker implements UserCheckerInterface
{
    /** Session key used by RegistrationController::resendVerificationEmail */
    public const string UNVERIFIED_USER_ID = 'unverified_user_id';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isVerified()) {
            // The password is valid: allow this user to ask for a new verification email
            $this->requestStack->getCurrentRequest()?->getSession()->set(self::UNVERIFIED_USER_ID, $user->getId());
            throw new CustomUserMessageAccountStatusException('Your email address has not been verified yet. Please check your inbox and click the confirmation link.');
        }
    }
}
