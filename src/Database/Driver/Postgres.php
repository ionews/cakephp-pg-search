<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Database\Driver;

use Autopage\PgSearch\Database\Schema\PostgresSchemaDialect;
use Autopage\PgSearch\Database\Schema\TableSchema;
use Cake\Database\Driver\Postgres as Driver;
use Cake\Database\Schema\SchemaDialect;
use Cake\Database\Schema\TableSchema as BaseSchema;

/**
 * Class Postgres
 */
class Postgres extends Driver
{
    /**
     * @inheritDoc
     */
    public function newTableSchema(string $table, array $columns = []): BaseSchema
    {
        $className = TableSchema::class;
        if (isset($this->_config['tableSchema'])) {
            /** @var class-string<\Cake\Database\Schema\TableSchema> $className */
            $className = $this->_config['tableSchema'];
        }

        return new $className($table, $columns);
    }

    /**
     * @inheritDoc
     */
    public function schemaDialect(): SchemaDialect
    {
        if ($this->_schemaDialect === null) {
            $this->_schemaDialect = new PostgresSchemaDialect($this);
        }

        return $this->_schemaDialect;
    }
}
