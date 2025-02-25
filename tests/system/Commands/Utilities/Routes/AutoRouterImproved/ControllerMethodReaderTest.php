<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved;

use CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\Controllers\Dash_folder\Dash_controller;
use CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\Controllers\Home;
use CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\Controllers\SubDir\BlogController;
use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Routing;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Controllers\Newautorouting;
use Tests\Support\Controllers\Remap;

/**
 * @internal
 */
#[Group('Others')]
final class ControllerMethodReaderTest extends CIUnitTestCase
{
    private function createControllerMethodReader(
        string $namespace = 'Tests\Support\Controllers',
    ): ControllerMethodReader {
        $methods = [
            'get',
            'post',
        ];

        return new ControllerMethodReader($namespace, $methods);
    }

    public function testRead(): void
    {
        $reader = $this->createControllerMethodReader();

        $routes = $reader->read(Newautorouting::class);

        $expected = [
            0 => [
                'method'       => 'get',
                'route'        => 'newautorouting',
                'route_params' => '[/..]',
                'handler'      => '\Tests\Support\Controllers\Newautorouting::getIndex',
                'params'       => [
                    'm' => false,
                ],
            ],
            [
                'method'       => 'post',
                'route'        => 'newautorouting/save',
                'route_params' => '/../..[/..]',
                'handler'      => '\Tests\Support\Controllers\Newautorouting::postSave',
                'params'       => [
                    'a' => true,
                    'b' => true,
                    'c' => false,
                ],
            ],
        ];

        $this->assertSame($expected, $routes);
    }

    public function testReadTranslateURIDashes(): void
    {
        $config                          = config(Routing::class);
        $config->translateURIDashes      = true;
        $config->translateUriToCamelCase = false;
        Factories::injectMock('config', Routing::class, $config);

        $reader = $this->createControllerMethodReader(
            'CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\Controllers',
        );

        $routes = $reader->read(Dash_controller::class);

        $expected = [
            0 => [
                'method'       => 'get',
                'route'        => 'dash-folder/dash-controller/somemethod',
                'route_params' => '[/..]',
                'handler'      => '\CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\Controllers\Dash_folder\Dash_controller::getSomemethod',
                'params'       => [
                    'p1' => false,
                ],
            ],
            [
                'method'       => 'get',
                'route'        => 'dash-folder/dash-controller/dash-method',
                'route_params' => '/..[/..]',
                'handler'      => '\CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\Controllers\Dash_folder\Dash_controller::getDash_method',
                'params'       => [
                    'p1' => true,
                    'p2' => false,
                ],
            ],
        ];

        $this->assertSame($expected, $routes);
    }

    public function testReadTranslateUriToCamelCase(): void
    {
        $config                          = config(Routing::class);
        $config->translateUriToCamelCase = true;
        Factories::injectMock('config', Routing::class, $config);

        $reader = $this->createControllerMethodReader(
            'CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\Controllers',
        );

        $routes = $reader->read(BlogController::class);

        $expected = [
            0 => [
                'method'       => 'get',
                'route'        => 'sub-dir/blog-controller',
                'route_params' => '',
                'handler'      => '\CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\Controllers\SubDir\BlogController::getIndex',
                'params'       => [
                ],
            ],
            [
                'method'       => 'get',
                'route'        => 'sub-dir/blog-controller/some-method',
                'route_params' => '[/..]',
                'handler'      => '\CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\Controllers\SubDir\BlogController::getSomeMethod',
                'params'       => [
                    'first' => false,
                ],
            ],
        ];

        $this->assertSame($expected, $routes);
    }

    public function testReadDefaultController(): void
    {
        $reader = $this->createControllerMethodReader(
            'CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\Controllers',
        );

        $routes = $reader->read(Home::class);

        $expected = [
            0 => [
                'method'       => 'get',
                'route'        => '/',
                'route_params' => '',
                'handler'      => '\CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\Controllers\Home::getIndex',
                'params'       => [],
            ],
            [
                'method'       => 'post',
                'route'        => '/',
                'route_params' => '',
                'handler'      => '\CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\Controllers\Home::postIndex',
                'params'       => [],
            ],
            [
                'method'       => 'get',
                'route'        => 'x home/foo',
                'route_params' => '',
                'handler'      => '\CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\Controllers\Home::getFoo',
                'params'       => [],
            ],
        ];

        $this->assertSame($expected, $routes);
    }

    public function testReadControllerWithRemap(): void
    {
        $reader = $this->createControllerMethodReader();

        $routes = $reader->read(Remap::class);

        $expected = [];
        $this->assertSame($expected, $routes);
    }
}
