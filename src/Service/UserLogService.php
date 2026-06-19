<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\UserLog;
use App\Entity\UserLogType;
use App\Repository\UserLogRepository;
use App\Repository\UserLogTypeRepository;
use App\Repository\UserRepository;

class UserLogService
{
    public function __construct(
        private readonly UserLogRepository $userLogRepository,
        private readonly UserLogTypeRepository $userLogTypeRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Records a user log entry. The log type is resolved by slug and created on
     * first use, so callers can reference UserLogType::* constants freely.
     *
     * @param array<string, mixed> $context
     */
    public function log(
        string $typeSlug,
        string $message,
        ?string $username = null,
        ?string $userAgent = null,
        ?string $ipAddress = null,
        array $context = [],
    ): UserLog {
        $userLog = new UserLog();
        $userLog->setUserLogType($this->resolveType($typeSlug));
        $userLog->setMessage($message);
        $userLog->setUsername($username);
        $userLog->setUserAgent($userAgent);
        $userLog->setIpAddress($ipAddress);
        $userLog->setContext($context === [] ? null : $context);

        // Correlate to a stable user id (resolved from the email at log time, so it
        // survives a later email change) for the admin activity feed.
        if (!is_null($username)) {
            $userLog->setUser($this->userRepository->findOneByEmail($username));
        }

        $this->userLogRepository->save($userLog, true);

        return $userLog;
    }

    private function resolveType(string $slug): UserLogType
    {
        $type = $this->userLogTypeRepository->findOneBySlug($slug);

        if ($type instanceof UserLogType) {
            return $type;
        }

        $type = new UserLogType();
        $type->setSlug($slug);
        $type->setName(ucwords(str_replace('_', ' ', $slug)));
        $this->userLogTypeRepository->save($type, true);

        return $type;
    }
}
