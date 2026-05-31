<?php

namespace App\Command;

use App\Service\Activity\ActivityLogger;
use App\Service\Elasticsearch\ItemSearchService;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:index-elasticsearch',
    description: 'Indexes all items in Elasticsearch',
)]
final class IndexElasticsearchCommand extends Command
{
    private const BATCH = 2000;

    public function __construct(
        private readonly Connection $connection,
        private readonly ItemSearchService $searchService,
        private readonly ActivityLogger $activityLogger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('reset', null, InputOption::VALUE_NONE, 'Recreate the index before indexing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $conn = $this->connection;

        $config = $conn->getConfiguration();
        if ($config instanceof Configuration) {
            $config->setMiddlewares([]);
        }

        if ($input->getOption('reset')) {
            $io->note('Recreating Elasticsearch index...');
            $this->searchService->resetIndex();
        } else {
            $this->searchService->ensureIndex();
        }

        $total = (int) $conn->fetchOne('SELECT COUNT(*) FROM item');
        $io->title(sprintf('Indexing %d items', $total));

        $this->activityLogger->log('console', 'index_start', sprintf('Command app:index-elasticsearch (%d items)', $total), [
            'command' => 'app:index-elasticsearch',
            'reset' => $input->getOption('reset'),
            'batch_size' => self::BATCH,
            'total' => $total,
        ]);

        $io->progressStart($total);
        $batchNum = 0;
        $lastId = 0;
        while (true) {
            $rows = $conn->fetchAllAssociative(
                sprintf(
                    'SELECT i.id, i.name, i.category_id, c.name AS category_name
                     FROM item i
                     INNER JOIN category c ON c.id = i.category_id
                     WHERE i.id > :lastId
                     ORDER BY i.id ASC
                     LIMIT %d',
                    self::BATCH
                ),
                ['lastId' => $lastId]
            );

            if ($rows === []) {
                break;
            }

            $docs = array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'category_id' => (int) $row['category_id'],
                'category_name' => $row['category_name'],
            ], $rows);

            $this->searchService->bulkIndex($docs);
            $lastId = (int) $rows[array_key_last($rows)]['id'];
            ++$batchNum;
            $io->progressAdvance(count($rows));

            if ($batchNum % 10 === 0) {
                $indexed = min($batchNum * self::BATCH, $total);
                $this->activityLogger->log('console', 'index_progress', sprintf('Indexing progress: ~%d / %d', $indexed, $total), [
                    'command' => 'app:index-elasticsearch',
                    'batch' => $batchNum,
                    'last_id' => $lastId,
                ]);
            }
        }

        $io->progressFinish();
        $this->activityLogger->log('console', 'index_done', 'Elasticsearch indexing complete', [
            'command' => 'app:index-elasticsearch',
            'total' => $total,
            'batches' => $batchNum,
        ]);
        $io->success('Indexing complete.');

        return Command::SUCCESS;
    }
}
