<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Database\Schema;

use Autopage\PgSearch\Database\Schema\TableSchema;
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
        $length = null;
        if (isset($matches[2])) {
            $length = (int)$matches[2];
        }

        if (in_array($col, ['date', 'time', 'boolean'], true)) {
            return ['type' => $col, 'length' => null];
        }
        if (in_array($col, ['timestamptz', 'timestamp with time zone'], true)) {
            return ['type' => TableSchema::TYPE_TIMESTAMP_TIMEZONE, 'length' => null];
        }
        if (strpos($col, 'timestamp') !== false) {
            return ['type' => TableSchema::TYPE_TIMESTAMP_FRACTIONAL, 'length' => null];
        }
        if (strpos($col, 'time') !== false) {
            return ['type' => TableSchema::TYPE_TIME, 'length' => null];
        }
        if ($col === 'serial' || $col === 'integer') {
            return ['type' => TableSchema::TYPE_INTEGER, 'length' => 10];
        }
        if ($col === 'bigserial' || $col === 'bigint') {
            return ['type' => TableSchema::TYPE_BIGINTEGER, 'length' => 20];
        }
        if ($col === 'smallint') {
            return ['type' => TableSchema::TYPE_SMALLINTEGER, 'length' => 5];
        }
        if ($col === 'inet') {
            return ['type' => TableSchema::TYPE_STRING, 'length' => 39];
        }
        if ($col === 'uuid') {
            return ['type' => TableSchema::TYPE_UUID, 'length' => null];
        }
        if ($col === 'char') {
            return ['type' => TableSchema::TYPE_CHAR, 'length' => $length];
        }
        if (strpos($col, 'character') !== false) {
            return ['type' => TableSchema::TYPE_STRING, 'length' => $length];
        }
        // money is 'string' as it includes arbitrary text content
        // before the number value.
        if (strpos($col, 'money') !== false || $col === 'string') {
            return ['type' => TableSchema::TYPE_STRING, 'length' => $length];
        }
        if (strpos($col, 'text') !== false) {
            return ['type' => TableSchema::TYPE_TEXT, 'length' => null];
        }
        if ($col === 'bytea') {
            return ['type' => TableSchema::TYPE_BINARY, 'length' => null];
        }
        if ($col === 'real' || strpos($col, 'double') !== false) {
            return ['type' => TableSchema::TYPE_FLOAT, 'length' => null];
        }
        if (
            strpos($col, 'numeric') !== false ||
            strpos($col, 'decimal') !== false
        ) {
            return ['type' => TableSchema::TYPE_DECIMAL, 'length' => null];
        }
        if (strpos($col, 'json') !== false) {
            return ['type' => TableSchema::TYPE_JSON, 'length' => null];
        }
        if (strpos($col, 'tsvector') !== false) {
            return ['type' => TableSchema::TYPE_TSVECTOR, 'length' => null];
        }

        $length = is_numeric($length) ? $length : null;

        return ['type' => TableSchema::TYPE_STRING, 'length' => $length];
    }

    /**
     * @inheritDoc
     */
    public function columnSql(BaseSchema $schema, string $name): string
    {
        /** @var array $data */
        $data = $schema->getColumn($name);
        $out = $this->_driver->quoteIdentifier($name);
        $typeMap = [
            TableSchema::TYPE_TINYINTEGER => ' SMALLINT',
            TableSchema::TYPE_SMALLINTEGER => ' SMALLINT',
            TableSchema::TYPE_BINARY_UUID => ' UUID',
            TableSchema::TYPE_BOOLEAN => ' BOOLEAN',
            TableSchema::TYPE_FLOAT => ' FLOAT',
            TableSchema::TYPE_DECIMAL => ' DECIMAL',
            TableSchema::TYPE_DATE => ' DATE',
            TableSchema::TYPE_TIME => ' TIME',
            TableSchema::TYPE_DATETIME => ' TIMESTAMP',
            TableSchema::TYPE_DATETIME_FRACTIONAL => ' TIMESTAMP',
            TableSchema::TYPE_TIMESTAMP => ' TIMESTAMP',
            TableSchema::TYPE_TIMESTAMP_FRACTIONAL => ' TIMESTAMP',
            TableSchema::TYPE_TIMESTAMP_TIMEZONE => ' TIMESTAMPTZ',
            TableSchema::TYPE_UUID => ' UUID',
            TableSchema::TYPE_CHAR => ' CHAR',
            TableSchema::TYPE_JSON => ' JSONB',
            TableSchema::TYPE_TSVECTOR => ' TSVECTOR',
        ];

        if (isset($typeMap[$data['type']])) {
            $out .= $typeMap[$data['type']];
        }

        if ($data['type'] === TableSchema::TYPE_INTEGER || $data['type'] === TableSchema::TYPE_BIGINTEGER) {
            $type = $data['type'] === TableSchema::TYPE_INTEGER ? ' INTEGER' : ' BIGINT';
            if ($schema->getPrimaryKey() === [$name] || $data['autoIncrement'] === true) {
                $type = $data['type'] === TableSchema::TYPE_INTEGER ? ' SERIAL' : ' BIGSERIAL';
                unset($data['null'], $data['default']);
            }
            $out .= $type;
        }

        if ($data['type'] === TableSchema::TYPE_TEXT && $data['length'] !== TableSchema::LENGTH_TINY) {
            $out .= ' TEXT';
        }
        if ($data['type'] === TableSchema::TYPE_BINARY) {
            $out .= ' BYTEA';
        }

        if ($data['type'] === TableSchema::TYPE_CHAR) {
            $out .= '(' . $data['length'] . ')';
        }

        if (
            $data['type'] === TableSchema::TYPE_STRING ||
            (
                $data['type'] === TableSchema::TYPE_TEXT &&
                $data['length'] === TableSchema::LENGTH_TINY
            )
        ) {
            $out .= ' VARCHAR';
            if (isset($data['length']) && $data['length'] !== '') {
                $out .= '(' . $data['length'] . ')';
            }
        }

        $hasCollate = [TableSchema::TYPE_TEXT, TableSchema::TYPE_STRING, TableSchema::TYPE_CHAR];
        if (in_array($data['type'], $hasCollate, true) && isset($data['collate']) && $data['collate'] !== '') {
            $out .= ' COLLATE "' . $data['collate'] . '"';
        }

        $hasPrecision = [
            TableSchema::TYPE_FLOAT,
            TableSchema::TYPE_DATETIME,
            TableSchema::TYPE_DATETIME_FRACTIONAL,
            TableSchema::TYPE_TIMESTAMP,
            TableSchema::TYPE_TIMESTAMP_FRACTIONAL,
            TableSchema::TYPE_TIMESTAMP_TIMEZONE,
        ];
        if (in_array($data['type'], $hasPrecision) && isset($data['precision'])) {
            $out .= '(' . $data['precision'] . ')';
        }

        if (
            $data['type'] === TableSchema::TYPE_DECIMAL &&
            (
                isset($data['length']) ||
                isset($data['precision'])
            )
        ) {
            $out .= '(' . $data['length'] . ',' . (int)$data['precision'] . ')';
        }

        if (isset($data['null']) && $data['null'] === false) {
            $out .= ' NOT NULL';
        }

        $datetimeTypes = [
            TableSchema::TYPE_DATETIME,
            TableSchema::TYPE_DATETIME_FRACTIONAL,
            TableSchema::TYPE_TIMESTAMP,
            TableSchema::TYPE_TIMESTAMP_FRACTIONAL,
            TableSchema::TYPE_TIMESTAMP_TIMEZONE,
        ];
        if (
            isset($data['default']) &&
            in_array($data['type'], $datetimeTypes) &&
            strtolower($data['default']) === 'current_timestamp'
        ) {
            $out .= ' DEFAULT CURRENT_TIMESTAMP';
        } elseif (isset($data['default'])) {
            $defaultValue = $data['default'];
            if ($data['type'] === 'boolean') {
                $defaultValue = (bool)$defaultValue;
            }
            $out .= ' DEFAULT ' . $this->_driver->schemaValue($defaultValue);
        } elseif (isset($data['null']) && $data['null'] !== false) {
            $out .= ' DEFAULT NULL';
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
