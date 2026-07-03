<?php

namespace App\Command;

use App\Service\UserService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Permanently deletes accounts whose 30-day deletion grace period has elapsed.
 * Meant to run daily via an external scheduler (host cron / Windows Task
 * Scheduler) — UserService::verifyCredentials() also purges lazily on the
 * account's next login attempt, so correctness doesn't depend on this command.
 */
#[AsCommand(name: 'app:prune-deleted-accounts', description: 'Permanently delete accounts past their 30-day deletion grace period')]
class PruneDeletedAccountsCommand extends Command
{
    public function __construct(private UserService $userService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $deleted = $this->userService->purgeExpiredDeletions();

        $io->success("Permanently deleted {$deleted} account(s) past their grace period.");

        return Command::SUCCESS;
    }
}
