<?php

namespace App\Command;

use App\Entity\HomepageSlot;
use App\Service\Activity\ActivityLogger;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Configuration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-database',
    description: 'Creates 10 categories with 50 items each (mock data)',
)]
final class SeedDatabaseCommand extends Command
{
    private const CATEGORY_COUNT = 10;
    private const ITEMS_PER_CATEGORY = 50;
    private const INSERT_BATCH = 50;

    public function __construct(
        private readonly Connection $connection,
        private readonly ActivityLogger $activityLogger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Delete existing data before seeding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $conn = $this->connection;

        $config = $conn->getConfiguration();
        if ($config instanceof Configuration) {
            $config->setMiddlewares([]);
        }

        if ($input->getOption('force')) {
            $io->warning('Removing existing data...');
            $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
            $conn->executeStatement('TRUNCATE TABLE homepage_slot');
            $conn->executeStatement('TRUNCATE TABLE item');
            $conn->executeStatement('TRUNCATE TABLE category');
            $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }

        $existing = (int) $conn->fetchOne('SELECT COUNT(*) FROM category');
        if ($existing > 0) {
            $io->error('Categories already exist. Use --force to regenerate.');

            return Command::FAILURE;
        }

        $io->title('Seeding categories and items');
        $this->activityLogger->log('console', 'seed_start', 'Command app:seed-database started', [
            'command' => 'app:seed-database',
            'categories' => self::CATEGORY_COUNT,
            'items_per_category' => self::ITEMS_PER_CATEGORY,
            'force' => (bool) $input->getOption('force'),
        ]);
        $io->progressStart(self::CATEGORY_COUNT);

        for ($c = 1; $c <= self::CATEGORY_COUNT; ++$c) {
            $name = sprintf('Category %02d', $c);
            $slug = sprintf('category-%02d', $c);

            $conn->executeStatement(
                'INSERT INTO category (name, slug) VALUES (?, ?)',
                [$name, $slug]
            );
            $categoryId = (int) $conn->lastInsertId();

            for ($batchStart = 1; $batchStart <= self::ITEMS_PER_CATEGORY; $batchStart += self::INSERT_BATCH) {
                $values = [];
                $params = [];
                $batchEnd = min($batchStart + self::INSERT_BATCH - 1, self::ITEMS_PER_CATEGORY);

                for ($i = $batchStart; $i <= $batchEnd; ++$i) {
                    $values[] = '(?, ?)';
                    $params[] = $categoryId;
                    $params[] = sprintf('%s - Item %04d', $name, $i);
                }

                $conn->executeStatement(
                    'INSERT INTO item (category_id, name) VALUES '.implode(', ', $values),
                    $params
                );

                unset($values, $params);
            }

            $io->progressAdvance();

            if ($c % 5 === 0) {
                gc_collect_cycles();
            }
        }

        $io->progressFinish();

        for ($slot = 1; $slot <= HomepageSlot::COUNT; ++$slot) {
            $conn->executeStatement(
                'INSERT INTO homepage_slot (slot_number, category_id) VALUES (?, ?)',
                [$slot, $slot]
            );
        }

        $totalItems = self::CATEGORY_COUNT * self::ITEMS_PER_CATEGORY;
        $slotNumbers = range(1, HomepageSlot::COUNT);
        $this->activityLogger->log('console', 'seed_done', sprintf('Seed complete: %d categories, %d items', self::CATEGORY_COUNT, $totalItems), [
            'command' => 'app:seed-database',
            'categories' => self::CATEGORY_COUNT,
            'items' => $totalItems,
            'homepage_slots' => $slotNumbers,
        ]);
        $io->success(sprintf(
            'Seeded: %d categories, %d items, %d homepage lists.',
            self::CATEGORY_COUNT,
            $totalItems,
            HomepageSlot::COUNT
        ));

        return Command::SUCCESS;
    }
}
