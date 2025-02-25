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

namespace CodeIgniter\Events;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockEvents;
use Config\Modules;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use stdClass;

/**
 * @internal
 */
#[Group('SeparateProcess')]
final class EventsTest extends CIUnitTestCase
{
    /**
     * Accessible event manager instance
     *
     * @var MockEvents
     */
    private Events $manager;

    #[WithoutErrorHandler]
    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new MockEvents();

        Events::removeAllListeners();
    }

    protected function tearDown(): void
    {
        Events::simulate(false);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function testInitialize(): void
    {
        /**
         * @var Modules $config
         */
        $config          = new Modules();
        $config->aliases = [];

        // it should start out empty
        MockEvents::setFiles([]);
        $this->assertEmpty(Events::getFiles());

        // make sure we have a default events file
        $default = [APPPATH . 'Config' . DIRECTORY_SEPARATOR . 'Events.php'];
        $this->manager->unInitialize();
        MockEvents::initialize();
        $this->assertSame($default, Events::getFiles());

        // but we should be able to change it through the backdoor
        MockEvents::setFiles(['/peanuts']);
        $this->assertSame(['/peanuts'], Events::getFiles());

        // re-initializing should have no effect
        MockEvents::initialize();
        $this->assertSame(['/peanuts'], Events::getFiles());
    }

    public function testPerformance(): void
    {
        $result = null;
        Events::on('foo', static function ($arg) use (&$result): void {
            $result = $arg;
        });
        Events::trigger('foo', 'bar');

        $logged = Events::getPerformanceLogs();
        // there should be some event activity logged
        $this->assertGreaterThan(0, count($logged));
    }

    public function testListeners(): void
    {
        $callback1 = static function (): void {
        };
        $callback2 = static function (): void {
        };

        Events::on('foo', $callback1, Events::PRIORITY_HIGH);
        Events::on('foo', $callback2, Events::PRIORITY_NORMAL);

        $this->assertSame([$callback1, $callback2], Events::listeners('foo'));
    }

    public function testHandleEvent(): void
    {
        $result = null;

        Events::on('foo', static function ($arg) use (&$result): void {
            $result = $arg;
        });

        $this->assertTrue(Events::trigger('foo', 'bar'));

        $this->assertSame('bar', $result);
    }

    public function testCancelEvent(): void
    {
        $result = 0;

        // This should cancel the flow of events, and leave
        // $result = 1.
        Events::on('foo', static function ($arg) use (&$result): bool {
            $result = 1;

            return false;
        });
        Events::on('foo', static function ($arg) use (&$result): void {
            $result = 2;
        });

        $this->assertFalse(Events::trigger('foo', 'bar'));
        $this->assertSame(1, $result);
    }

    public function testPriority(): void
    {
        $result = 0;

        Events::on('foo', static function () use (&$result): bool {
            $result = 1;

            return false;
        }, Events::PRIORITY_NORMAL);
        // Since this has a higher priority, it will
        // run first.
        Events::on('foo', static function () use (&$result): bool {
            $result = 2;

            return false;
        }, Events::PRIORITY_HIGH);

        $this->assertFalse(Events::trigger('foo', 'bar'));
        $this->assertSame(2, $result);
    }

    public function testPriorityWithMultiple(): void
    {
        $result = [];

        Events::on('foo', static function () use (&$result): void {
            $result[] = 'a';
        }, Events::PRIORITY_NORMAL);

        Events::on('foo', static function () use (&$result): void {
            $result[] = 'b';
        }, Events::PRIORITY_LOW);

        Events::on('foo', static function () use (&$result): void {
            $result[] = 'c';
        }, Events::PRIORITY_HIGH);

        Events::on('foo', static function () use (&$result): void {
            $result[] = 'd';
        }, 75);

        Events::trigger('foo');
        $this->assertSame(['c', 'd', 'a', 'b'], $result);
    }

    public function testRemoveListener(): void
    {
        $user = $this->getEditableObject();

        $callback = static function () use (&$user): void {
            $user->name = 'Boris';
            $user->age  = 40;
        };

        Events::on('foo', $callback);

        $this->assertTrue(Events::trigger('foo'));
        $this->assertSame('Boris', $user->name);

        $user = $this->getEditableObject();

        $this->assertTrue(Events::removeListener('foo', $callback));

        $this->assertTrue(Events::trigger('foo'));
        $this->assertSame('Ivan', $user->name);
    }

    public function testRemoveListenerTwice(): void
    {
        $user = $this->getEditableObject();

        $callback = static function () use (&$user): void {
            $user->name = 'Boris';
            $user->age  = 40;
        };

        Events::on('foo', $callback);

        $this->assertTrue(Events::trigger('foo'));
        $this->assertSame('Boris', $user->name);

        $user = $this->getEditableObject();

        $this->assertTrue(Events::removeListener('foo', $callback));
        $this->assertFalse(Events::removeListener('foo', $callback));

        $this->assertTrue(Events::trigger('foo'));
        $this->assertSame('Ivan', $user->name);
    }

    public function testRemoveUnknownListener(): void
    {
        $user = $this->getEditableObject();

        $callback = static function () use (&$user): void {
            $user->name = 'Boris';
            $user->age  = 40;
        };

        Events::on('foo', $callback);

        $this->assertTrue(Events::trigger('foo'));
        $this->assertSame('Boris', $user->name);

        $user = $this->getEditableObject();

        $this->assertFalse(Events::removeListener('bar', $callback));
        $this->assertTrue(Events::trigger('foo'));
        $this->assertSame('Boris', $user->name);
    }

    public function testRemoveAllListenersWithSingleEvent(): void
    {
        $result = false;

        $callback = static function () use (&$result): void {
            $result = true;
        };

        Events::on('foo', $callback);

        Events::removeAllListeners('foo');

        $listeners = Events::listeners('foo');

        $this->assertSame([], $listeners);
    }

    public function testRemoveAllListenersWithMultipleEvents(): void
    {
        $result = false;

        $callback = static function () use (&$result): void {
            $result = true;
        };

        Events::on('foo', $callback);
        Events::on('bar', $callback);

        Events::removeAllListeners();

        $this->assertSame([], Events::listeners('foo'));
        $this->assertSame([], Events::listeners('bar'));
    }

    // Basically if it doesn't crash this should be good...
    public function testHandleEventCallableInternalFunc(): void
    {
        Events::on('foo', 'strlen');

        $this->assertTrue(Events::trigger('foo', 'bar'));
    }

    public function testHandleEventCallableClass(): void
    {
        $box = new class () {
            public string $logged;

            public function hold(string $value): void
            {
                $this->logged = $value;
            }
        };

        Events::on('foo', $box->hold(...));

        $this->assertTrue(Events::trigger('foo', 'bar'));

        $this->assertSame('bar', $box->logged);
    }

    public function testSimulate(): void
    {
        $result = 0;

        $callback = static function () use (&$result): void {
            $result += 2;
        };

        Events::on('foo', $callback);

        Events::simulate(true);
        Events::trigger('foo');

        $this->assertSame(0, $result);
    }

    private function getEditableObject(): stdClass
    {
        $user       = new stdClass();
        $user->name = 'Ivan';
        $user->age  = 30;

        return clone $user;
    }
}
