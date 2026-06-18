<?php

declare(strict_types=1);

namespace App\Command;

use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractCommand extends Command
{
    private LoggerInterface $logger;
    private SharedLockInterface $lock;
    private LockFactory $lockFactory;
    private KernelInterface $kernel;

    #[Required]
    public function setLockFactory(LockFactory $lockFactory): void
    {
        $this->lockFactory = $lockFactory;
    }

    #[Required]
    public function setKernel(KernelInterface $kernel): void
    {
        $this->kernel = $kernel;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->lock = $this->lockFactory->createLock($this->getLockName($input));

        if (!$this->lock->acquire()) {
            throw new Exception(sprintf('Another instance of %s is already running', $this->getName()));
        }

        $this->logger = new Logger($this->getName());

        $logFileName = sprintf('%s/%s.log', $this->kernel->getLogDir(), new ReflectionClass($this)->getShortName());
        $handler = new StreamHandler($logFileName, Level::Debug);

        $formatter = new LineFormatter("%datetime% [%level_name%]: %message%\n", 'Y-m-d H:i:s', true, true);
        $handler->setFormatter($formatter);

        $this->logger->pushHandler($handler);
    }

    protected function finalize(): void
    {
        if ($this->lock->isAcquired()) {
            $this->lock->release();
        }
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            $result = parent::run($input, $output);
        } finally {
            $this->finalize();
        }

        return $result;
    }

    protected function getLockName(InputInterface $input): string
    {
        return $this->getName();
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
