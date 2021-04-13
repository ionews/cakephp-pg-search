<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Database\Schema;

use Cake\Database\Exception\DatabaseException;
use Cake\Database\Schema\PostgresSchemaDialect as SchemaDialect;
use Cake\Database\Schema\TableSchema as BaseSchema;

/**
 * Estende o dialeto do Postgres para dar suporte
 * a busca textual.
 */
class PostgresSchemaDialect extends SchemaDialect
{
    /**
     * Convert a column definition to the abstract types.
     *
     * The returned type will be a type that
     * Cake\Database\TypeFactory can handle.
     *
     * @param string $column The column type + length
     * @throws \Cake\Database\Exception\DatabaseException when column cannot be parsed.
     * @return array Array of column information.
     */
    protected function _convertColumn(string $column): array
    {
        preg_match('/([a-z\s]+)(?:\(([0-9,]+)\))?/i', $column, $matches);
        if (empty($matches)) {
            throw new DatabaseException(sprintf('Unable to parse column type from "%s"', $column));
        }

        $col = strtolower($matches[1]);
        if (strpos($col, 'tsvector') === false) {
            return parent::_convertColumn($column);
        }

        return ['type' => TableSchema::TYPE_TSVECTOR, 'length' => null];
    }

    /**
     * @inheritDoc
     */
    public function columnSql(BaseSchema $schema, string $name): string
    {
        /** @var array $data */
        $data = $schema->getColumn($name);
        if ($data['type'] !== TableSchema::TYPE_TSVECTOR) {
            return parent::columnSql($schema, $name);
        }

        $out = $this->_driver->quoteIdentifier($name);
        $out .= ' TSVECTOR';

        if (isset($data['default'])) {
            $defaultValue = $data['default'];
            $out .= ' DEFAULT ' . $this->_driver->schemaValue($defaultValue);
        } elseif (isset($data['null']) && $data['null'] !== false) {
            $out .= ' DEFAULT NULL';
        }

        if (isset($data['null']) && $data['null'] === false) {
            $out .= ' NOT NULL';
        }

        return $out;
    }

    /**
     * @inheritDoc
     */
    public function indexSql(BaseSchema $schema, string $name): string
    {
        /** @var array $data */
        $data = $schema->getIndex($name);
        $columns = array_map(
            [$this->_driver, 'quoteIdentifier'],
            $data['columns']
        );

        $type = '';
        if ($data['type'] === TableSchema::INDEX_GIN) {
            $type = 'GIN ';
        }
        if ($data['type'] === TableSchema::INDEX_GIST) {
            $type = 'GIST ';
        }

        return sprintf(
            'CREATE INDEX %s ON %s %s(%s)',
            $this->_driver->quoteIdentifier($name),
            $this->_driver->quoteIdentifier($schema->name()),
            $type,
            implode(', ', $columns)
        );
    }
}
