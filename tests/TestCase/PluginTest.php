<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Test\TestCase;

use Cake\Http\BaseApplication;
use Autopage\PgSearch\Plugin as PluginPgSearch;
use Cake\Core\Configure;
use Cake\Database\TypeFactory;
use Cake\TestSuite\TestCase;
use PDO;

/**
 * Test for the Plugin setup.
 */
class PluginTest extends TestCase
{
    /**
     * Setup
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     *
     * @return void
     */
    public function testBootstrap(): void
    {
        $app = $this->getMockForAbstractClass(BaseApplication::class, [CONFIG]);
        $plugin = new PluginPgSearch();

        TypeFactory::clear();
        $this->assertNull(TypeFactory::getMap('tsvector'));

        $plugin->bootstrap($app);
        $this->assertNotNull(TypeFactory::getMap('tsvector'));
    }
}