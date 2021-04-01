<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Test\App;

use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;

/**
 * Application setup class.
 *
 * This defines the bootstrapping logic you
 * want to use in your application.
 */
class Application extends BaseApplication
{
    public function bootstrap(): void
    {
        parent::bootstrap();

        $this->addPlugin('Autopage/PgSearch', [
            'path' => ROOT . DS,
        ]);
    }

    /**
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to set in your App Class
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue;
    }
}