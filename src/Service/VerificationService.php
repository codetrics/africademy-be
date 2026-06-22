<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\VerificationCode;
use App\Enum\UserStatus;
use App\Enum\VerificationPurpose;
use App\Repository\UserRepository;
use App\Repository\VerificationCodeRepository;
use DateTime;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class VerificationService
{
    public const int CODE_TTL_MINUTES = 15;
    public const int LOGIN_OTP_TTL_MINUTES = 5;

    public function __construct(
        private readonly VerificationCodeRepository $verificationCodeRepository,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly NotificationService $notificationService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly WelcomeMailer $welcomeMailer,
    ) {
    }

    /**
     * Issues and emails an email-verification code. No-op if already verified.
     */
    public function requestEmailVerification(User $user): void
    {
        if (!is_null($user->getEmailVerifiedAt())) {
            return;
        }

        $code = $this->issueCode($user, VerificationPurpose::EmailVerification);

        $this->notificationService->createEmailNotification(
            [$user->getEmail()],
            'Verify your Africademy email',
            'email/verification_code.html.twig',
            [
                'first_name' => $user->getProfile()->getFirstName(),
                'code' => $code,
                'ttl_minutes' => self::CODE_TTL_MINUTES,
            ],
        );
    }

    /**
     * Resolves the user by email and issues a verification code. Silent when the
     * email is unknown so the endpoint does not leak account existence.
     */
    public function requestEmailVerificationByEmail(string $email): void
    {
        $user = $this->userRepository->findOneByEmail($email);

        if ($user instanceof User) {
            $this->requestEmailVerification($user);
        }
    }

    public function verifyEmail(string $email, string $code): bool
    {
        $user = $this->userRepository->findOneByEmail($email);

        if (is_null($user) || !$this->consumeCode($user, VerificationPurpose::EmailVerification, $code)) {
            return false;
        }

        $user->setEmailVerifiedAt(new DateTime());
        $this->userRepository->save($user, true);

        // A facilitator only learns their account is pending approval once they've
        // proven they own the address.
        if (
            $user->getStatus() === UserStatus::PendingReview
            && in_array(User::ROLE_FACILITATOR, $user->getRawRoles(), true)
        ) {
            $this->welcomeMailer->sendFacilitatorPendingApproval($user);
        }

        return true;
    }

    public function requestPasswordReset(string $email): void
    {
        $user = $this->userRepository->findOneByEmail($email);

        if (is_null($user)) {
            return;
        }

        $code = $this->issueCode($user, VerificationPurpose::PasswordReset);

        $this->notificationService->createEmailNotification(
            [$email],
            'Reset your Africademy password',
            'email/password_reset.html.twig',
            [
                'first_name' => $user->getProfile()->getFirstName(),
                'code' => $code,
                'ttl_minutes' => self::CODE_TTL_MINUTES,
            ],
        );
    }

    public function resetPassword(string $email, string $code, string $newPassword): bool
    {
        $user = $this->userRepository->findOneByEmail($email);

        if (is_null($user) || !$this->consumeCode($user, VerificationPurpose::PasswordReset, $code)) {
            return false;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        // A reset replaces a possibly-compromised credential: drop the OTP trust
        // window so the next login re-verifies, and revoke existing sessions.
        $user->setLastOtpAt(null);
        $this->userRepository->save($user, true);
        $this->refreshTokenService->revokeAllForUser($user);

        return true;
    }

    /**
     * Issues and emails a login OTP (second factor). The caller is expected to
     * have already verified the password.
     */
    public function requestLoginOtp(User $user): void
    {
        $code = $this->issueCode($user, VerificationPurpose::LoginOtp, self::LOGIN_OTP_TTL_MINUTES);

        $this->notificationService->createEmailNotification(
            [$user->getEmail()],
            'Your Africademy login code',
            'email/login_otp.html.twig',
            [
                'first_name' => $user->getProfile()->getFirstName(),
                'code' => $code,
                'ttl_minutes' => self::LOGIN_OTP_TTL_MINUTES,
            ],
        );
    }

    public function verifyLoginOtp(User $user, string $code): bool
    {
        return $this->consumeCode($user, VerificationPurpose::LoginOtp, $code);
    }

    private function issueCode(User $user, VerificationPurpose $purpose, int $ttlMinutes = self::CODE_TTL_MINUTES): string
    {
        $this->verificationCodeRepository->invalidateActive($user, $purpose);

        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $verificationCode = new VerificationCode();
        $verificationCode->setUser($user);
        $verificationCode->setPurpose($purpose);
        $verificationCode->setCodeHash(password_hash($plainCode, PASSWORD_BCRYPT));
        $verificationCode->setExpiresAt(new DateTime(sprintf('+%d minutes', $ttlMinutes)));
        $this->verificationCodeRepository->save($verificationCode, true);

        return $plainCode;
    }

    private function consumeCode(User $user, VerificationPurpose $purpose, string $plainCode): bool
    {
        $verificationCode = $this->verificationCodeRepository->findLatestActive($user, $purpose);

        if (is_null($verificationCode) || $verificationCode->isExpired()) {
            return false;
        }

        // Burn the code after too many wrong guesses so a short-lived 6-digit
        // code cannot be brute-forced within its TTL.
        if ($verificationCode->hasReachedMaxAttempts()) {
            $verificationCode->setUsedAt(new DateTime());
            $this->verificationCodeRepository->save($verificationCode, true);

            return false;
        }

        $verificationCode->incrementAttempts();

        if (!password_verify($plainCode, $verificationCode->getCodeHash())) {
            $this->verificationCodeRepository->save($verificationCode, true);

            return false;
        }

        $verificationCode->setUsedAt(new DateTime());
        $this->verificationCodeRepository->save($verificationCode, true);

        return true;
    }
}
