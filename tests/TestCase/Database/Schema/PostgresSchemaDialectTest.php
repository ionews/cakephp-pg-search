<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Test\TestCase\Database\Schema;

use Autopage\PgSearch\Database\Driver\Postgres;
use Autopage\PgSearch\Database\Schema\PostgresSchemaDialect;
use Autopage\PgSearch\Database\Schema\TableSchema;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use PDO;

/**
 * Test for the Postgres schema dialect.
 */
class PostgresSchemaDialectTest extends TestCase
{
    /**
     * Helper method for skipping tests that need a real connection.
     *
     * @return void
     */
    protected function _needsConnection()
    {
        $config = ConnectionManager::getConfig('test');
        $this->skipIf(strpos($config['driver'], 'Postgres') === false, 'Not using Postgres for test config');
    }

    /**
     * Data provider for convert column testing
     *
     * @return array
     */
    public static function convertColumnProvider()
    {
        return [
            // Timestamp
            [
                ['type' => 'TIMESTAMP', 'datetime_precision' => 6],
                ['type' => 'timestampfractional', 'length' => null, 'precision' => 6],
            ],
            [
                ['type' => 'TIMESTAMP', 'datetime_precision' => 0],
                ['type' => 'timestamp', 'length' => null, 'precision' => 0],
            ],
            [
                ['type' => 'TIMESTAMP WITHOUT TIME ZONE', 'datetime_precision' => 6],
                ['type' => 'timestampfractional', 'length' => null, 'precision' => 6],
            ],
            [
                ['type' => 'TIMESTAMP WITH TIME ZONE', 'datetime_precision' => 6],
                ['type' => 'timestamptimezone', 'length' => null, 'precision' => 6],
            ],
            [
                ['type' => 'TIMESTAMPTZ', 'datetime_precision' => 6],
                ['type' => 'timestamptimezone', 'length' => null, 'precision' => 6],
            ],
            // Date & time
            [
                ['type' => 'DATE'],
                ['type' => 'date', 'length' => null],
            ],
            [
                ['type' => 'TIME'],
                ['type' => 'time', 'length' => null],
            ],
            [
                ['type' => 'TIME WITHOUT TIME ZONE'],
                ['type' => 'time', 'length' => null],
            ],
            // Integer
            [
                ['type' => 'SMALLINT'],
                ['type' => 'smallinteger', 'length' => 5],
            ],
            [
                ['type' => 'INTEGER'],
                ['type' => 'integer', 'length' => 10],
            ],
            [
                ['type' => 'SERIAL'],
                ['type' => 'integer', 'length' => 10],
            ],
            [
                ['type' => 'BIGINT'],
                ['type' => 'biginteger', 'length' => 20],
            ],
            [
                ['type' => 'BIGSERIAL'],
                ['type' => 'biginteger', 'length' => 20],
            ],
            // Decimal
            [
                ['type' => 'NUMERIC'],
                ['type' => 'decimal', 'length' => null, 'precision' => null],
            ],
            [
                ['type' => 'NUMERIC', 'default' => 'NULL::numeric'],
                ['type' => 'decimal', 'length' => null, 'precision' => null, 'default' => null],
            ],
            [
                ['type' => 'DECIMAL(10,2)', 'column_precision' => 10, 'column_scale' => 2],
                ['type' => 'decimal', 'length' => 10, 'precision' => 2],
            ],
            // String
            [
                ['type' => 'VARCHAR'],
                ['type' => 'string', 'length' => null, 'collate' => 'pt_BR.utf8'],
            ],
            [
                ['type' => 'VARCHAR(10)'],
                ['type' => 'string', 'length' => 10, 'collate' => 'pt_BR.utf8'],
            ],
            [
                ['type' => 'CHARACTER VARYING'],
                ['type' => 'string', 'length' => null, 'collate' => 'pt_BR.utf8'],
            ],
            [
                ['type' => 'CHARACTER VARYING(10)'],
                ['type' => 'string', 'length' => 10, 'collate' => 'pt_BR.utf8'],
            ],
            [
                ['type' => 'CHARACTER VARYING(255)', 'default' => 'NULL::character varying'],
                ['type' => 'string', 'length' => 255, 'default' => null, 'collate' => 'pt_BR.utf8'],
            ],
            [
                ['type' => 'CHAR(10)'],
                ['type' => 'char', 'length' => 10, 'collate' => 'pt_BR.utf8'],
            ],
            [
                ['type' => 'CHAR(36)'],
                ['type' => 'char', 'length' => 36, 'collate' => 'pt_BR.utf8'],
            ],
            [
                ['type' => 'CHARACTER(10)'],
                ['type' => 'string', 'length' => 10, 'collate' => 'pt_BR.utf8'],
            ],
            [
                ['type' => 'MONEY'],
                ['type' => 'string', 'length' => null],
            ],
            // UUID
            [
                ['type' => 'UUID'],
                ['type' => 'uuid', 'length' => null],
            ],
            [
                ['type' => 'INET'],
                ['type' => 'string', 'length' => 39],
            ],
            // Text
            [
                ['type' => 'TEXT'],
                ['type' => 'text', 'length' => null, 'collate' => 'pt_BR.utf8'],
            ],
            // Blob
            [
                ['type' => 'BYTEA'],
                ['type' => 'binary', 'length' => null],
            ],
            // Float
            [
                ['type' => 'REAL'],
                ['type' => 'float', 'length' => null],
            ],
            [
                ['type' => 'DOUBLE PRECISION'],
                ['type' => 'float', 'length' => null],
            ],
            // JSON
            [
                ['type' => 'JSON'],
                ['type' => 'json', 'length' => null],
            ],
            [
                ['type' => 'JSONB'],
                ['type' => 'json', 'length' => null],
            ],
            // TSVECTOR
            [
                ['type' => 'TSVECTOR'],
                ['type' => 'tsvector', 'length' => null],
            ],
        ];
    }

    /**
     * Test parsing Postgres column types from field description.
     *
     * @dataProvider convertColumnProvider
     * @return void
     */
    public function testConvertColumn($field, $expected)
    {
        $field += [
            'name' => 'field',
            'null' => 'YES',
            'default' => 'Default value',
            'comment' => 'Comment section',
            'char_length' => null,
            'column_precision' => null,
            'column_scale' => null,
            'collation_name' => 'pt_BR.utf8',
        ];
        $expected += [
            'null' => true,
            'default' => 'Default value',
            'comment' => 'Comment section',
        ];

        $driver = $this->getMockBuilder(Postgres::class)->getMock();
        $dialect = new PostgresSchemaDialect($driver);

        $table = new TableSchema('table');
        $dialect->convertColumnDescription($table, $field);

        $actual = array_intersect_key($table->getColumn('field'), $expected);
        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);
    }

    /**
     * Column provider for creating column sql
     *
     * @return array
     */
    public static function columnSqlProvider()
    {
        return [
            // strings
            [
                'title',
                ['type' => 'string', 'length' => 25, 'null' => false],
                '"title" VARCHAR(25) NOT NULL',
            ],
            [
                'title',
                ['type' => 'string', 'length' => 25, 'null' => true, 'default' => 'ignored'],
                '"title" VARCHAR(25) DEFAULT \'ignored\'',
            ],
            [
                'id',
                ['type' => 'char', 'length' => 32, 'null' => false],
                '"id" CHAR(32) NOT NULL',
            ],
            [
                'title',
                ['type' => 'string', 'length' => 36, 'null' => false],
                '"title" VARCHAR(36) NOT NULL',
            ],
            [
                'id',
                ['type' => 'uuid', 'length' => 36, 'null' => false],
                '"id" UUID NOT NULL',
            ],
            [
                'id',
                ['type' => 'binaryuuid', 'length' => null, 'null' => false],
                '"id" UUID NOT NULL',
            ],
            [
                'role',
                ['type' => 'string', 'length' => 10, 'null' => false, 'default' => 'admin'],
                '"role" VARCHAR(10) NOT NULL DEFAULT \'admin\'',
            ],
            [
                'title',
                ['type' => 'string'],
                '"title" VARCHAR',
            ],
            [
                'title',
                ['type' => 'string', 'length' => 36],
                '"title" VARCHAR(36)',
            ],
            [
                'title',
                ['type' => 'string', 'length' => 255, 'null' => false, 'collate' => 'C'],
                '"title" VARCHAR(255) COLLATE "C" NOT NULL',
            ],
            // Text
            [
                'body',
                ['type' => 'text', 'null' => false],
                '"body" TEXT NOT NULL',
            ],
            [
                'body',
                ['type' => 'text', 'length' => TableSchema::LENGTH_TINY, 'null' => false],
                sprintf('"body" VARCHAR(%s) NOT NULL', TableSchema::LENGTH_TINY),
            ],
            [
                'body',
                ['type' => 'text', 'length' => TableSchema::LENGTH_MEDIUM, 'null' => false],
                '"body" TEXT NOT NULL',
            ],
            [
                'body',
                ['type' => 'text', 'length' => TableSchema::LENGTH_LONG, 'null' => false],
                '"body" TEXT NOT NULL',
            ],
            [
                'body',
                ['type' => 'text', 'null' => false, 'collate' => 'C'],
                '"body" TEXT COLLATE "C" NOT NULL',
            ],
            // Integers
            [
                'post_id',
                ['type' => 'tinyinteger', 'length' => 11],
                '"post_id" SMALLINT',
            ],
            [
                'post_id',
                ['type' => 'smallinteger', 'length' => 11],
                '"post_id" SMALLINT',
            ],
            [
                'post_id',
                ['type' => 'integer', 'length' => 11],
                '"post_id" INTEGER',
            ],
            [
                'post_id',
                ['type' => 'biginteger', 'length' => 20],
                '"post_id" BIGINT',
            ],
            [
                'post_id',
                ['type' => 'integer', 'autoIncrement' => true, 'length' => 11],
                '"post_id" SERIAL',
            ],
            [
                'post_id',
                ['type' => 'biginteger', 'autoIncrement' => true, 'length' => 20],
                '"post_id" BIGSERIAL',
            ],
            // Decimal
            [
                'value',
                ['type' => 'decimal'],
                '"value" DECIMAL',
            ],
            [
                'value',
                ['type' => 'decimal', 'length' => 11],
                '"value" DECIMAL(11,0)',
            ],
            [
                'value',
                ['type' => 'decimal', 'length' => 12, 'precision' => 5],
                '"value" DECIMAL(12,5)',
            ],
            // Float
            [
                'value',
                ['type' => 'float'],
                '"value" FLOAT',
            ],
            [
                'value',
                ['type' => 'float', 'length' => 11, 'precision' => 3],
                '"value" FLOAT(3)',
            ],
            // Binary
            [
                'img',
                ['type' => 'binary'],
                '"img" BYTEA',
            ],
            // Boolean
            [
                'checked',
                ['type' => 'boolean', 'default' => false],
                '"checked" BOOLEAN DEFAULT FALSE',
            ],
            [
                'checked',
                ['type' => 'boolean', 'default' => true, 'null' => false],
                '"checked" BOOLEAN NOT NULL DEFAULT TRUE',
            ],
            // Boolean
            [
                'checked',
                ['type' => 'boolean', 'default' => 0],
                '"checked" BOOLEAN DEFAULT FALSE',
            ],
            [
                'checked',
                ['type' => 'boolean', 'default' => 1, 'null' => false],
                '"checked" BOOLEAN NOT NULL DEFAULT TRUE',
            ],
            // Date & Time
            [
                'start_date',
                ['type' => 'date'],
                '"start_date" DATE',
            ],
            [
                'start_time',
                ['type' => 'time'],
                '"start_time" TIME',
            ],
            // Datetime
            [
                'created',
                ['type' => 'datetime', 'null' => true],
                '"created" TIMESTAMP DEFAULT NULL',
            ],
            [
                'created_without_precision',
                ['type' => 'datetime', 'precision' => 0],
                '"created_without_precision" TIMESTAMP(0)',
            ],
            [
                'created_without_precision',
                ['type' => 'datetimefractional', 'precision' => 0],
                '"created_without_precision" TIMESTAMP(0)',
            ],
            [
                'created_with_precision',
                ['type' => 'datetimefractional', 'precision' => 3],
                '"created_with_precision" TIMESTAMP(3)',
            ],
            // Timestamp
            [
                'created',
                ['type' => 'timestamp', 'null' => true],
                '"created" TIMESTAMP DEFAULT NULL',
            ],
            [
                'created_without_precision',
                ['type' => 'timestamp', 'precision' => 0],
                '"created_without_precision" TIMESTAMP(0)',
            ],
            [
                'created_without_precision',
                ['type' => 'timestampfractional', 'precision' => 0],
                '"created_without_precision" TIMESTAMP(0)',
            ],
            [
                'created_with_precision',
                ['type' => 'timestampfractional', 'precision' => 3],
                '"created_with_precision" TIMESTAMP(3)',
            ],
            [
                'open_date',
                ['type' => 'timestampfractional', 'null' => false, 'default' => '2016-12-07 23:04:00'],
                '"open_date" TIMESTAMP NOT NULL DEFAULT \'2016-12-07 23:04:00\'',
            ],
            [
                'null_date',
                ['type' => 'timestampfractional', 'null' => true],
                '"null_date" TIMESTAMP DEFAULT NULL',
            ],
            [
                'current_timestamp',
                ['type' => 'timestamp', 'null' => false, 'default' => 'CURRENT_TIMESTAMP'],
                '"current_timestamp" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
            ],
            [
                'current_timestamp_fractional',
                ['type' => 'timestampfractional', 'null' => false, 'default' => 'CURRENT_TIMESTAMP'],
                '"current_timestamp_fractional" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
            ],
            // Tsvector
            [
                'body',
                ['type' => 'tsvector', 'length' => null, 'null' => false],
                '"body" TSVECTOR NOT NULL',
            ],
            [
                'body',
                ['type' => 'tsvector', 'null' => true],
                '"body" TSVECTOR DEFAULT NULL',
            ],
            [
                'body',
                ['type' => 'tsvector', 'null' => true, 'default' => null],
                '"body" TSVECTOR DEFAULT NULL',
            ],
            [
                'body',
                ['type' => 'tsvector', 'null' => false, 'default' => null],
                '"body" TSVECTOR NOT NULL',
            ],
        ];
    }

    /**
     * Test generating column definitions
     *
     * @dataProvider columnSqlProvider
     * @return void
     */
    public function testColumnSql($name, $data, $expected)
    {
        $driver = $this->_getMockedDriver();
        $schema = new PostgresSchemaDialect($driver);

        $table = (new TableSchema('schema_articles'))->addColumn($name, $data);
        $this->assertEquals($expected, $schema->columnSql($table, $name));
    }

    /**
     * Index provider for creating index sql
     *
     * @return array
     */
    public static function indexSqlProvider()
    {
        return [
            // index default
            [
                'idx_index',
                ['type' => 'index', 'columns' => ['field_name']],
                'field_name',
                ['type' => 'integer'],
                'CREATE INDEX "idx_index" ON "schema_articles" ("field_name")',
            ],
            // index gin
            [
                'idx_index',
                ['type' => 'gin', 'columns' => ['field_name']],
                'field_name',
                ['type' => 'tsvector'],
                'CREATE INDEX "idx_index" ON "schema_articles" GIN ("field_name")',
            ],
            // index gist
            [
                'idx_index',
                ['type' => 'gist', 'columns' => ['field_name']],
                'field_name',
                ['type' => 'tsvector'],
                'CREATE INDEX "idx_index" ON "schema_articles" GIST ("field_name")',
            ],
        ];
    }

    /**
     * Test generating index definitions
     *
     * @dataProvider indexSqlProvider
     * @return void
     */
    public function testIndexSql($idxName, $idxData, $colName, $colData, $expected)
    {
        $driver = $this->_getMockedDriver();
        $schema = new PostgresSchemaDialect($driver);

        $table = (new TableSchema('schema_articles'))
            ->addColumn($colName, $colData)
            ->addIndex($idxName, $idxData);

        $this->assertEquals($expected, $schema->indexSql($table, $idxName));
    }

    /**
     * Get a schema instance with a mocked driver/pdo instances
     *
     * @return \Cake\Database\Driver
     */
    protected function _getMockedDriver()
    {
        $driver = new Postgres();
        $mock = $this->getMockBuilder(PDO::class)
            ->onlyMethods(['quote'])
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->any())
            ->method('quote')
            ->will($this->returnCallback(function ($value) {
                return "'$value'";
            }));
        $driver->setConnection($mock);

        return $driver;
    }
}
