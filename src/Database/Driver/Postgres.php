<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Database\Driver;

use Autopage\PgSearch\Database\Schema\PostgresSchemaDialect;
use Cake\Database\Driver\Postgres as Driver;
use Cake\Database\Schema\SchemaDialect;

/**
 * Class Postgres
 */
class Postgres extends Driver
{
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
