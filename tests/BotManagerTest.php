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

use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\TelegramLog;

/**
 * Class BotManagerTest.php
 *
 * Leave all member variables public to allow easy modification.
 *
 * @package NPM\TelegramBotManager
 */
class BotManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testSetParameters()
    {
        $botManager = new BotManager(array_merge(ParamsTest::$demo_vital_params, [
            'admins'      => [1],            // valid
            'upload_path' => '/upload/path', // valid
            'paramX'      => 'something'     // invalid
        ]));
        $params     = $botManager->getParams();
        self::assertEquals([1], $params->getBotParam('admins'));
        self::assertEquals('/upload/path', $params->getBotParam('upload_path'));
        self::assertNull($params->getBotParam('paramX'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Some vital info is missing: api_key
     */
    public function testNoVitalsFail()
    {
        new BotManager([]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Some vital info is missing: secret
     */
    public function testSomeVitalsFail()
    {
        new BotManager(['api_key' => 'abc123', 'botname' => 'testbot']);
    }

    public function testVitalsSuccess()
    {
        new BotManager(ParamsTest::$demo_vital_params);
    }


    public function testInitLogging()
    {
        $botManager = new BotManager(array_merge(ParamsTest::$demo_vital_params, [
            'logging' => [
                'debug'  => '/tmp/php-telegram-bot-debuglog.log',
                'error'  => '/tmp/php-telegram-bot-errorlog.log',
                'update' => '/tmp/php-telegram-bot-updatelog.log',
            ],
        ]));

        self::assertFalse(TelegramLog::isDebugLogActive());
        self::assertFalse(TelegramLog::isErrorLogActive());
        self::assertFalse(TelegramLog::isUpdateLogActive());

        $botManager->initLogging();

        self::assertTrue(TelegramLog::isDebugLogActive());
        self::assertTrue(TelegramLog::isErrorLogActive());
        self::assertTrue(TelegramLog::isUpdateLogActive());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid access
     */
    public function testValidateSecretFail()
    {
        $_GET       = ['s' => 'NOT_my_secret_12345'];
        $botManager = new BotManager(array_merge(ParamsTest::$demo_vital_params, [
            'secret' => 'my_secret_12345',
        ]));

        $botManager->validateSecret(true);
    }

    public function testValidateSecretSuccess()
    {
        // Force validation to test non-CLI scenario.
        $_GET = ['s' => 'my_secret_12345'];
        (new BotManager(array_merge(ParamsTest::$demo_vital_params, [
            'secret' => 'my_secret_12345',
        ])))->validateSecret(true);

        // Calling from CLI doesn't require a secret.
        $_GET = ['s' => 'whatever_on_cli'];
        (new BotManager(array_merge(ParamsTest::$demo_vital_params, [
            'secret' => 'my_secret_12345',
        ])))->validateSecret();
    }

    public function testValidateAndSetWebhookSuccess()
    {
        $botManager = new BotManager(array_merge(ParamsTest::$demo_vital_params, [
            'webhook' => 'https://web/hook.php',
        ]));

        TestHelpers::setObjectProperty(
            $botManager,
            'telegram',
            $this->getMockBuilder(Telegram::class)
                 ->disableOriginalConstructor()
                 ->setMethods(['setWebHook', 'unsetWebHook', 'getDescription'])
                 ->getMock()
        );

        $telegram = $botManager->getTelegram();
        $telegram->expects(static::any())
                 ->method('setWebHook')
                 ->with('https://web/hook.php?a=handle&s=secret_12345')
                 ->will(static::returnSelf());
        $telegram->expects(static::any())
                 ->method('unsetWebHook')
                 ->will(static::returnSelf());
        $telegram->expects(static::any())
                 ->method('getDescription')
                 ->will(static::onConsecutiveCalls(
                 // set
                     'Webhook set',
                     'Webhook already set',
                     // unset
                     'Webhook deleted',
                     'Webhook does not exist',
                     // reset
                     'Webhook deleted',
                     'Webhook set'
                 ));

        TestHelpers::setObjectProperty($botManager->getAction(), 'action', 'set');
        $botManager->validateAndSetWebhook();
        self::assertSame('Webhook set', $botManager->test_output);

        $botManager->validateAndSetWebhook();
        self::assertSame('Webhook already set', $botManager->test_output);

        TestHelpers::setObjectProperty($botManager->getAction(), 'action', 'unset');
        $botManager->validateAndSetWebhook();
        self::assertSame('Webhook deleted', $botManager->test_output);

        $botManager->validateAndSetWebhook();
        self::assertSame('Webhook does not exist', $botManager->test_output);

        TestHelpers::setObjectProperty($botManager->getAction(), 'action', 'reset');
        $botManager->validateAndSetWebhook();
        self::assertSame('Webhook set', $botManager->test_output);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid webhook
     */
    public function testValidateAndSetWebhookFailSetWithoutWebhook()
    {
        $_GET = ['a' => 'set'];
        (new BotManager(array_merge(ParamsTest::$demo_vital_params, [
            'webhook' => null,
        ])))->validateAndSetWebhook();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid webhook
     */
    public function testValidateAndSetWebhookFailResetWithoutWebhook()
    {
        $_GET = ['a' => 'reset'];
        (new BotManager(array_merge(ParamsTest::$demo_vital_params, [
            'webhook' => null,
        ])))->validateAndSetWebhook();
    }

    public function testGetLoopTime()
    {
        // Parameter not set.
        self::assertSame(0, (new BotManager(ParamsTest::$demo_vital_params))->getLoopTime());

        $_GET = ['l' => ''];
        self::assertSame(604800, (new BotManager(ParamsTest::$demo_vital_params))->getLoopTime());

        $_GET = ['l' => '     '];
        self::assertSame(604800, (new BotManager(ParamsTest::$demo_vital_params))->getLoopTime());

        $_GET = ['l' => 'text-string'];
        self::assertSame(0, (new BotManager(ParamsTest::$demo_vital_params))->getLoopTime());

        $_GET = ['l' => 0];
        self::assertSame(0, (new BotManager(ParamsTest::$demo_vital_params))->getLoopTime());

        $_GET = ['l' => -12345];
        self::assertSame(0, (new BotManager(ParamsTest::$demo_vital_params))->getLoopTime());

        $_GET = ['l' => 12345];
        self::assertSame(12345, (new BotManager(ParamsTest::$demo_vital_params))->getLoopTime());

        $_GET = ['l' => '-12345'];
        self::assertSame(0, (new BotManager(ParamsTest::$demo_vital_params))->getLoopTime());

        $_GET = ['l' => '12345'];
        self::assertSame(12345, (new BotManager(ParamsTest::$demo_vital_params))->getLoopTime());
    }

    public function testSetBotExtras()
    {
        $extras     = [
            'admins'          => [1, 2, 3],
            'download_path'   => __DIR__ . '/Download',
            'upload_path'     => __DIR__ . '/Upload',
            'command_configs' => [
                'weather' => ['owm_api_key' => 'owm_api_key_12345'],
            ],
        ];
        $botManager = new BotManager(array_merge(ParamsTest::$demo_vital_params, $extras));

        TestHelpers::setObjectProperty(
            $botManager,
            'telegram',
            new Telegram(
                ParamsTest::$demo_vital_params['api_key'],
                ParamsTest::$demo_vital_params['botname']
            )
        );

        $botManager->setBotExtras();
        $telegram = $botManager->getTelegram();

        self::assertEquals($extras['admins'], $telegram->getAdminList());
        self::assertEquals($extras['download_path'], $telegram->getDownloadPath());
        self::assertEquals($extras['upload_path'], $telegram->getUploadPath());
        self::assertEquals($extras['command_configs']['weather'], $telegram->getCommandConfig('weather'));
    }
}
