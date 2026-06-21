<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Drives the email-OTP second factor on login: minting/decoding the short-lived
 * pre-auth token, issuing/verifying the code, the 2-day trust window, and
 * producing the real access + refresh tokens once OTP is satisfied.
 */
class OtpLoginService
{
    /** How long a successful OTP keeps the account trusted (skips OTP). */
    public const int OTP_TRUST_TTL_SECONDS = 172800; // 2 days

    /** Lifetime of the pre-auth token handed out between password and OTP. */
    public const int PRE_AUTH_TTL_SECONDS = 300; // 5 minutes

    public function __construct(
        private readonly JWTEncoderInterface $jwtEncoder,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
        private readonly UserRepository $userRepository,
        private readonly VerificationService $verificationService,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(param: 'gesdinet_jwt_refresh_token.ttl')]
        private readonly int $refreshTokenTtl,
    ) {
    }

    /**
     * Decides the login outcome after the password has been verified: returns the
     * real tokens when the account is still within its trust window, otherwise
     * issues an OTP and returns the pre-auth token to exchange for it.
     *
     * @return array<string, mixed>
     */
    public function startSession(User $user): array
    {
        if ($this->isWithinTrustWindow($user)) {
            return ['otp_pending' => false] + $this->issueTokens($user);
        }

        $this->verificationService->requestLoginOtp($user);

        return [
            'otp_pending' => true,
            'pre_auth_token' => $this->issuePreAuthToken($user),
        ];
    }

    /**
     * Verifies the OTP for the user behind a pre-auth token. Returns the user and
     * the real tokens on success, or null when the token or code is invalid.
     *
     * @return array{user: User, tokens: array{token: string, refresh_token: string}}|null
     */
    public function completeOtp(
        string $preAuthToken,
        #[SensitiveParameter]
        string $code,
    ): ?array {
        $user = $this->resolveUserFromPreAuthToken($preAuthToken);

        if (!$user instanceof User) {
            return null;
        }

        if (!$this->verificationService->verifyLoginOtp($user, $code)) {
            return null;
        }

        $user->setLastOtpAt(new DateTime());
        $this->userRepository->save($user, true);

        return ['user' => $user, 'tokens' => $this->issueTokens($user)];
    }

    /**
     * Re-issues a fresh OTP for the user behind a pre-auth token. Returns false
     * when the pre-auth token is invalid or expired.
     */
    public function resendOtp(string $preAuthToken): bool
    {
        $user = $this->resolveUserFromPreAuthToken($preAuthToken);

        if (!$user instanceof User) {
            return false;
        }

        $this->verificationService->requestLoginOtp($user);

        return true;
    }

    public function isWithinTrustWindow(User $user): bool
    {
        $lastOtpAt = $user->getLastOtpAt();

        if (!$lastOtpAt instanceof DateTime) {
            return false;
        }

        return (new DateTime()->getTimestamp() - $lastOtpAt->getTimestamp()) < self::OTP_TRUST_TTL_SECONDS;
    }

    private function issuePreAuthToken(User $user): string
    {
        return $this->jwtEncoder->encode([
            'otp_pending' => true,
            'otp_email' => $user->getUserIdentifier(),
            'exp' => new DateTime()->getTimestamp() + self::PRE_AUTH_TTL_SECONDS,
        ]);
    }

    private function resolveUserFromPreAuthToken(string $preAuthToken): ?User
    {
        if ($preAuthToken === '') {
            return null;
        }

        try {
            $payload = $this->jwtEncoder->decode($preAuthToken);
        } catch (JWTDecodeFailureException) {
            return null;
        }

        if (($payload['otp_pending'] ?? false) !== true) {
            return null;
        }

        $email = $payload['otp_email'] ?? null;

        if (!is_string($email) || $email === '') {
            return null;
        }

        return $this->userRepository->findOneByEmail($email);
    }

    /**
     * @return array{token: string, refresh_token: string}
     */
    private function issueTokens(User $user): array
    {
        $token = $this->jwtTokenManager->create($user);

        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, $this->refreshTokenTtl);
        $this->refreshTokenManager->save($refreshToken);

        return [
            'token' => $token,
            'refresh_token' => $refreshToken->getRefreshToken(),
        ];
    }
}
