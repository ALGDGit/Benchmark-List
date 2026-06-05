<?php

namespace App\Command;

use App\Service\Benchmark\BenchmarkReportWriter;
use App\Service\Benchmark\HttpBenchmarkRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:benchmark',
    description: 'Run HTTP load scenarios and write JSON/Markdown reports',
)]
final class BenchmarkCommand extends Command
{
    public function __construct(
        private readonly HttpBenchmarkRunner $runner,
        private readonly BenchmarkReportWriter $writer,
        private readonly string $projectDir,
        private readonly string $defaultBaseUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Run all scenarios from benchmarks/scenarios.json')
            ->addOption('scenario', null, InputOption::VALUE_REQUIRED, 'Run a single scenario id from scenarios.json')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'Ad-hoc URL to benchmark')
            ->addOption('requests', null, InputOption::VALUE_REQUIRED, 'Total requests', '200')
            ->addOption('concurrency', 'c', InputOption::VALUE_REQUIRED, 'Parallel requests per batch', '10')
            ->addOption('warmup', 'w', InputOption::VALUE_REQUIRED, 'Warmup requests before measuring', '5')
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Replace {{base}} in scenario URLs')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output basename without extension', 'benchmarks/results/latest');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $baseUrl = (string) ($input->getOption('base-url') ?: $this->defaultBaseUrl);
        $outputBase = (string) $input->getOption('output');
        if (!str_starts_with($outputBase, '/')) {
            $outputBase = $this->projectDir.'/'.$outputBase;
        }

        $scenarios = $this->resolveScenarios($input, $baseUrl);
        if ($scenarios === []) {
            $io->error('No scenarios to run. Use --all, --scenario=id, or --url=...');

            return Command::FAILURE;
        }

        $results = [];
        foreach ($scenarios as $scenario) {
            $io->section($scenario['name']);
            $io->text(sprintf('URL: %s', $scenario['url']));
            $result = $this->runner->run(
                $scenario['id'],
                $scenario['name'],
                $scenario['url'],
                (int) $scenario['requests'],
                (int) $scenario['concurrency'],
                (int) ($scenario['warmup'] ?? 0),
            );
            $results[] = $result;
            $data = $result->toArray();
            $io->table(
                ['Metric', 'Value'],
                [
                    ['RPS', (string) $data['rps']],
                    ['p50', $data['latency_ms']['p50'].' ms'],
                    ['p95', $data['latency_ms']['p95'].' ms'],
                    ['p99', $data['latency_ms']['p99'].' ms'],
                    ['Errors', (string) $data['errors']],
                ]
            );
        }

        $meta = [
            'base_url' => $baseUrl,
            'host' => php_uname('n'),
            'php' => PHP_VERSION,
        ];

        $this->writer->writeJson($outputBase.'.json', $results, $meta);
        $this->writer->writeMarkdown($outputBase.'.md', $results, $meta);

        $io->success([
            'Benchmark complete.',
            'JSON: '.$outputBase.'.json',
            'Markdown: '.$outputBase.'.md',
        ]);

        return Command::SUCCESS;
    }

    /**
     * @return list<array{id: string, name: string, url: string, requests: int|string, concurrency: int|string, warmup?: int|string}>
     */
    private function resolveScenarios(InputInterface $input, string $baseUrl): array
    {
        if ($input->getOption('url')) {
            return [[
                'id' => 'adhoc',
                'name' => 'Ad-hoc URL',
                'url' => (string) $input->getOption('url'),
                'requests' => $input->getOption('requests'),
                'concurrency' => $input->getOption('concurrency'),
                'warmup' => $input->getOption('warmup'),
            ]];
        }

        $path = $this->projectDir.'/benchmarks/scenarios.json';
        if (!is_file($path)) {
            return [];
        }

        /** @var list<array<string, mixed>> $all */
        $all = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $selectedId = $input->getOption('scenario');

        $picked = [];
        foreach ($all as $scenario) {
            if (!isset($scenario['id'], $scenario['name'], $scenario['url'])) {
                continue;
            }
            if ($input->getOption('all') || ($selectedId && $scenario['id'] === $selectedId)) {
                $url = str_replace('{{base}}', rtrim($baseUrl, '/'), (string) $scenario['url']);
                $url = str_replace('{{rand}}', bin2hex(random_bytes(4)), $url);
                $scenario['url'] = $url;
                $picked[] = $scenario;
            }
        }

        return $picked;
    }
}
