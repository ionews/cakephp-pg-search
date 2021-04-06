<?php
declare(strict_types=1);

namespace Autopage\PgSearch;

use Autopage\PgSearch\Database\Type\TsvectorType;
use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Database\TypeFactory;

/**
 * Plugin for PgSearch
 */
class Plugin extends BasePlugin
{
    /**
     * Load all the plugin configuration and bootstrap logic.
     *
     * The host application is provided as an argument. This allows you to load
     * additional plugin dependencies, or attach events.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        if (TypeFactory::getMap('tsvector') === null) {
            TypeFactory::map('tsvector', TsvectorType::class);
        }
    }
}
