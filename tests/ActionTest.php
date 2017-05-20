<?php declare(strict_types=1);
/**
 * This file is part of the TelegramBotManager package.
 *
 * (c) Armando Lüscher <armando@noplanman.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TelegramBot\TelegramBotManager\Tests;

use TelegramBot\TelegramBotManager\Action;

class ActionTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        self::assertEquals('set', (new Action('set'))->getAction());
        self::assertEquals('unset', (new Action('unset'))->getAction());
        self::assertEquals('reset', (new Action('reset'))->getAction());
        self::assertEquals('handle', (new Action('handle'))->getAction());
        self::assertEquals('cron', (new Action('cron'))->getAction());
        self::assertEquals('webhookinfo', (new Action('webhookinfo'))->getAction());
    }

    /**
     * @expectedException \TelegramBot\TelegramBotManager\Exception\InvalidActionException
     * @expectedExceptionMessageRegExp /^Invalid action: non-existent$/
     */
    public function testConstructFail()
    {
        new Action('non-existent');
    }

    public function testIsAction()
    {
        $action = new Action('set');
        self::assertTrue($action->isAction('set'));
        self::assertTrue($action->isAction(['set', 'unset']));
        self::assertFalse($action->isAction('unset'));
        self::assertFalse($action->isAction(['unset', 'reset']));

        // Random action.
        $valid_actions = Action::getValidActions();
        $random_action = $valid_actions[array_rand($valid_actions)];
        $action        = new Action($random_action);
        self::assertTrue($action->isAction(Action::getValidActions()));

        // Test some weird values.
        self::assertFalse($action->isAction(null));
        self::assertFalse($action->isAction(true));
        self::assertFalse($action->isAction(1));
        self::assertFalse($action->isAction('non-existent'));
    }
}
