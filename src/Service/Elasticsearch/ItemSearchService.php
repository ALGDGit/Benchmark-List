<?php

namespace App\Service\Elasticsearch;

use App\Service\Activity\ActivityLogger;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

final class ItemSearchService
{
    public const INDEX = 'items';

    private ?Client $client = null;

    public function __construct(
        private readonly string $elasticsearchUrl,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    private function client(): Client
    {
        if ($this->client === null) {
            $this->client = ClientBuilder::create()
                ->setHosts([$this->elasticsearchUrl])
                ->build();
        }

        return $this->client;
    }

    public function ensureIndex(): void
    {
        $client = $this->client();

        if ($client->indices()->exists(['index' => self::INDEX])->asBool()) {
            return;
        }

        $this->activityLogger->log('elasticsearch', 'create_index', 'Elasticsearch index "items" created', [
            'index' => self::INDEX,
            'url' => $this->elasticsearchUrl,
        ]);

        $client->indices()->create([
            'index' => self::INDEX,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => [
                            'autocomplete' => [
                                'tokenizer' => 'autocomplete',
                                'filter' => ['lowercase'],
                            ],
                            'autocomplete_search' => [
                                'tokenizer' => 'lowercase',
                            ],
                        ],
                        'tokenizer' => [
                            'autocomplete' => [
                                'type' => 'edge_ngram',
                                'min_gram' => 2,
                                'max_gram' => 20,
                                'token_chars' => ['letter', 'digit'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => [
                            'type' => 'text',
                            'analyzer' => 'autocomplete',
                            'search_analyzer' => 'autocomplete_search',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'category_id' => ['type' => 'integer'],
                        'category_name' => ['type' => 'keyword'],
                    ],
                ],
            ],
        ]);
    }

    public function resetIndex(): void
    {
        $client = $this->client();
        if ($client->indices()->exists(['index' => self::INDEX])->asBool()) {
            $client->indices()->delete(['index' => self::INDEX]);
            $this->activityLogger->log('elasticsearch', 'delete_index', 'Elasticsearch index "items" deleted', [
                'index' => self::INDEX,
            ]);
        }
        $this->client = null;
        $this->ensureIndex();
    }

    /**
     * @param list<array{id:int,name:string,category_id:int,category_name:string}> $documents
     */
    public function bulkIndex(array $documents): void
    {
        if ($documents === []) {
            return;
        }

        $body = [];
        foreach ($documents as $doc) {
            $body[] = ['index' => ['_index' => self::INDEX, '_id' => (string) $doc['id']]];
            $body[] = $doc;
        }

        $this->client()->bulk(['body' => $body, 'refresh' => false]);
    }

    public function indexOne(int $id, string $name, int $categoryId, string $categoryName): void
    {
        $this->ensureIndex();

        $this->client()->index([
            'index' => self::INDEX,
            'id' => (string) $id,
            'body' => [
                'id' => $id,
                'name' => $name,
                'category_id' => $categoryId,
                'category_name' => $categoryName,
            ],
            'refresh' => true,
        ]);

        $this->activityLogger->log('elasticsearch', 'index_one', sprintf('Document #%d indexed', $id), [
            'index' => self::INDEX,
            'id' => $id,
            'name' => $name,
            'category_id' => $categoryId,
        ]);
    }

    public function deleteOne(int $id): void
    {
        try {
            $this->client()->delete([
                'index' => self::INDEX,
                'id' => (string) $id,
                'refresh' => true,
            ]);
            $this->activityLogger->log('elasticsearch', 'delete_one', sprintf('Document #%d removed from index', $id), [
                'index' => self::INDEX,
                'id' => $id,
            ]);
        } catch (\Throwable $e) {
            $this->activityLogger->log('elasticsearch', 'delete_one_error', sprintf('Could not delete document #%d', $id), [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return list<array{id:int,name:string,category_id:int,category_name:string}>
     */
    public function autocomplete(string $query, int $limit = 12): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $response = $this->client()->search([
            'index' => self::INDEX,
            'body' => [
                'size' => $limit,
                'query' => [
                    'bool' => [
                        'should' => [
                            ['match' => ['name' => ['query' => $query, 'operator' => 'and']]],
                            ['prefix' => ['name.keyword' => ['value' => mb_strtolower($query)]]],
                        ],
                    ],
                ],
                '_source' => ['id', 'name', 'category_id', 'category_name'],
            ],
        ]);

        $hits = $response['hits']['hits'] ?? [];

        return array_map(
            static fn (array $hit): array => $hit['_source'],
            is_array($hits) ? $hits : []
        );
    }
}
