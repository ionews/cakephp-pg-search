<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Test\TestCase\Database\Driver;

use Autopage\PgSearch\Database\Driver\Postgres;
use Autopage\PgSearch\Database\Schema\PostgresSchemaDialect;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

/**
 * Test for the Postgres driver.
 */
class PostgresDriverTest extends TestCase
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
     * Test if schema dialect are overrided
     *
     * @return void
     */
    public function testSchemaDialect()
    {
        $this->_needsConnection();

        $conn = ConnectionManager::get('test');
        $driver = $conn->getDriver();

        $this->assertInstanceOf(PostgresSchemaDialect::class, $driver->schemaDialect());
    }
}