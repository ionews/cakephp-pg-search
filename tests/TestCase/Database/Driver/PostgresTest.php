<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Test\TestCase\Database\Driver;

use Autopage\PgSearch\Database\Schema\PostgresSchemaDialect;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

/**
 * Test for the Postgres driver.
 */
class PostgresDriverTest extends TestCase
{
    /**
     * Setup
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $config = ConnectionManager::getConfig('test');
        $this->skipIf(strpos($config['driver'], 'Postgres') === false, 'Not using Postgres for test config');
    }

    /**
     * Test if schema dialect are overrided
     *
     * @return void
     */
    public function testSchemaDialect()
    {
        $conn = ConnectionManager::get('test');
        $driver = $conn->getDriver();

        $this->assertInstanceOf(PostgresSchemaDialect::class, $driver->schemaDialect());
    }
}
