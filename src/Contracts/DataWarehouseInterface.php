<?php

namespace BPA\InfrastructureLib\Contracts;

interface DataWarehouseInterface
{
    public function query(string $sql, array $params = []): array;
    public function insert(string $table, array $data): bool;
    public function export(string $source, string $destination): bool;
    public function createTable(string $table, array $schema): bool;
}
