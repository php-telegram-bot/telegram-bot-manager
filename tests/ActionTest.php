<?php
/**
 * This file is part of the TelegramBotManager package.
 *
 * (c) Armando Lüscher <armando@noplanman.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NPM\TelegramBotManager;

class ActionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid action
     */
    public function testConstruct()
    {
        self::assertEquals('set', (new Action('set'))->getAction());
        self::assertEquals('unset', (new Action('unset'))->getAction());
        self::assertEquals('reset', (new Action('reset'))->getAction());
        self::assertEquals('handle', (new Action('handle'))->getAction());
        new Action('non-existent');
    }

    public function testIsAction()
    {
        $action = new Action('set');
        self::assertTrue($action->isAction('set'));
        self::assertTrue($action->isAction(['set', 'unset']));
        self::assertFalse($action->isAction('unset'));
        self::assertFalse($action->isAction(['unset', 'reset']));

        // Test some weird values.
        self::assertFalse($action->isAction(null));
        self::assertFalse($action->isAction(true));
        self::assertFalse($action->isAction(1));
        self::assertFalse($action->isAction('non-existent'));
    }
}
