<?php

namespace BPA\InfrastructureLib\Services\DataWarehouse;

use BPA\InfrastructureLib\Contracts\DataWarehouseInterface;
use Google\Cloud\BigQuery\BigQueryClient;

class DataWarehouseService implements DataWarehouseInterface
{
    private $client;

    public function __construct()
    {
        $this->client = new BigQueryClient([
            'projectId' => config('core_dependencies.data_warehouse.project_id'),
            'keyFilePath' => config('core_dependencies.data_warehouse.credentials_path'),
        ]);
    }

    public function query(string $sql, array $params = []): array
    {
        $query = $this->client->query($sql)
            ->parameters($params);
        
        $results = $query->runSync();
        
        return iterator_to_array($results);
    }

    public function insert(string $table, array $data): bool
    {
        $dataset = $this->client->dataset(config('core_dependencies.data_warehouse.dataset'));
        $table = $dataset->table($table);
        
        return $table->insertRows([['data' => $data]]);
    }

    public function export(string $source, string $destination): bool
    {
        $dataset = $this->client->dataset(config('core_dependencies.data_warehouse.dataset'));
        $table = $dataset->table($source);
        
        $job = $table->export($destination);
        $job->waitUntilComplete();
        
        return $job->isComplete();
    }

    public function createTable(string $table, array $schema): bool
    {
        $dataset = $this->client->dataset(config('core_dependencies.data_warehouse.dataset'));
        
        return $dataset->createTable($table, ['schema' => ['fields' => $schema]]);
    }
}
