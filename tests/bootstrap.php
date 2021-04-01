<?php
declare(strict_types=1);

/**
 * Test suite bootstrap for PgSearch.
 *
 * This function is used to find the location of CakePHP whether CakePHP
 * has been installed as a dependency of the plugin, or the plugin is itself
 * installed as a dependency of an application.
 */
$findRoot = function ($root) {
    do {
        $lastRoot = $root;
        $root = dirname($root);
        if (is_dir($root . '/vendor/cakephp/cakephp')) {
            return $root;
        }
    } while ($root !== $lastRoot);

    throw new Exception("Cannot find the root of the application, unable to run tests");
};
$root = $findRoot(__FILE__);
unset($findRoot);

chdir($root);

function _define($name, $value)
{
    if (!defined($name)) {
        define($name, $value);
    }
}

_define('ROOT', $root);
_define('APP_DIR', 'App');
_define('WEBROOT_DIR', 'webroot');
_define('APP', ROOT . '/tests/App/');
_define('CONFIG', ROOT . '/tests/Config/');
_define('WWW_ROOT', ROOT . DS . WEBROOT_DIR . DS);
_define('TESTS', ROOT . DS . 'tests' . DS);
_define('TMP', ROOT . DS . 'tmp' . DS);
_define('LOGS', TMP . 'logs' . DS);
_define('CACHE', TMP . 'cache' . DS);
_define('CAKE_CORE_INCLUDE_PATH', ROOT . '/vendor/cakephp/cakephp');
_define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);
_define('CAKE', CORE_PATH . 'src' . DS);
_define('CORE_TESTS', CORE_PATH . 'tests' . DS);
_define('CORE_TEST_CASES', CORE_TESTS . 'TestCase');
_define('TEST_APP', CORE_TESTS . 'test_app' . DS);

require_once ROOT . '/vendor/autoload.php';
require_once ROOT . '/vendor/cakephp/cakephp/src/basics.php';

$_SERVER['PHP_SELF'] = '/';

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Engine\FileLog;
use Cake\Log\Log;
use Cake\Routing\Router;
use Cake\Database\TypeFactory;
use Autopage\PgSearch\Database\Type\TsvectorType;

Configure::write('App', [
    'namespace' => 'Autopage\\PgSearch\\Test\\App',
    'encoding' => 'UTF-8',
    'base' => false,
    'baseUrl' => false,
    'dir' => 'src',
    'webroot' => WEBROOT_DIR,
    'wwwRoot' => WWW_ROOT,
    'fullBaseUrl' => 'http://localhost',
    'imageBaseUrl' => 'img/',
    'jsBaseUrl' => 'js/',
    'cssBaseUrl' => 'css/',
    'paths' => [
        'plugins' => [dirname(APP) . DS . 'plugins' . DS],
    ],
]);

Configure::write('debug', true);
Configure::write('Error.errorLevel', E_ALL & ~E_USER_DEPRECATED);

$TMP = new \Cake\Filesystem\Folder(TMP);
$TMP->create(TMP . 'cache/models', 0777);
$TMP->create(TMP . 'cache/persistent', 0777);
$TMP->create(TMP . 'cache/views', 0777);

$cache = [
    'default' => [
        'engine' => 'File',
    ],
    '_cake_core_' => [
        'className' => 'File',
        'prefix' => 'pgsearch_cake_core_',
        'path' => CACHE . 'persistent/',
        'serialize' => true,
        'duration' => '+10 seconds',
    ],
    '_cake_model_' => [
        'className' => 'File',
        'prefix' => 'pgsearch_cake_model_',
        'path' => CACHE . 'models/',
        'serialize' => 'File',
        'duration' => '+10 seconds',
    ],
    '_cake_method_' => [
        'className' => 'File',
        'prefix' => 'pgsearch_cake_method_',
        'path' => CACHE . 'models/',
        'serialize' => 'File',
        'duration' => '+10 seconds',
    ],
];

Cache::setConfig($cache);
Configure::write('Session', [
    'defaults' => 'php',
]);

$log = [
    'debug' => [
        'className' => FileLog::class,
        'path' => LOGS,
        'file' => 'debug',
        'scopes' => false,
        'levels' => ['notice', 'info', 'debug'],
    ],
    'error' => [
        'className' => FileLog::class,
        'path' => LOGS,
        'file' => 'error',
        'scopes' => false,
        'levels' => ['warning', 'error', 'critical', 'alert', 'emergency'],
    ],
    'queries' => [
        'className' => FileLog::class,
        'path' => LOGS,
        'file' => 'queries',
        'scopes' => ['queriesLog'],
    ],
];

Log::setConfig($log);

// Ensure default test connection is defined
if (!getenv('db_dsn')) {
    putenv('db_dsn=postgres://postgres@postgres/pgsearch?encoding=utf8');
}

ConnectionManager::setConfig('test', [
    'url' => getenv('db_dsn'),
    'timezone' => 'UTC',
    'log' => true,
]);

if (file_exists($root . '/config/bootstrap.php')) {
    require $root . '/config/bootstrap.php';
}

TypeFactory::map('tsvector', TsvectorType::class);

$application = new \Autopage\PgSearch\Test\App\Application(CONFIG);
$application->bootstrap();
$application->pluginBootstrap();