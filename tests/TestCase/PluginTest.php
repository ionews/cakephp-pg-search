<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Test\TestCase;

use Autopage\PgSearch\Plugin as PluginPgSearch;
use Cake\Database\TypeFactory;
use Cake\Http\BaseApplication;
use Cake\TestSuite\TestCase;

/**
 * Test for the Plugin setup.
 */
class PluginTest extends TestCase
{
    /**
     * Test initialization
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
