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
    /**
     * @var array Test vital parameters for BotManager.
     */
    private $vitalParams = [];

    /**
     * @var array Test optional parameters for BotManager.
     */
    private $optionalParams = [];

    /**
     * Set up BotManager instance.
     */
    public function setUp()
    {
        $this->vitalParams = [
            'api_key' => 'api_key_12345',
            'botname' => 'testbot',
            'secret'  => 'secret_12345',
        ];

        $this->optionalParams = [
            'webhook'         => 'https://php.telegram.bot/' . basename(__FILE__),
            'selfcrt'         => __DIR__ . '/server.crt',
            'admins'          => 1,
            'mysql'           => ['host' => '127.0.0.1', 'user' => 'root', 'password' => 'root', 'database' => 'telegram_bot'],
            'download_path'   => __DIR__ . '/Download',
            'upload_path'     => __DIR__ . '/Upload',
            'commands_paths'  => __DIR__ . '/CustomCommands',
            'command_configs' => [
                'weather'       => ['owm_api_key' => 'owm_api_key_12345'],
                'sendtochannel' => ['your_channel' => '@my_channel'],
            ],
            'botan_token'     => 'botan12345',
            'custom_input'    => '{"some":"raw", "json":"update"}',
        ];
    }

    public function testSetParameters()
    {
        $botManager = new BotManager(array_merge($this->vitalParams, [
            'admins'      => [1],            // valid
            'upload_path' => '/upload/path', // valid
            'paramX'      => 'something'     // invalid
        ]));
        self::assertEquals([1], $botManager->admins);
        self::assertEquals('/upload/path', $botManager->upload_path);
        self::assertObjectNotHasAttribute('paramX', $botManager);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Some vital info is missing (api_key, botname or secret)
     */
    public function testNoVitalsFail()
    {
        new BotManager([]);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Some vital info is missing (api_key, botname or secret)
     */
    public function testSomeVitalsFail()
    {
        new BotManager(['api_key' => 'abc123', 'botname' => 'testbot']);
    }

    public function testVitalsSuccess()
    {
        new BotManager($this->vitalParams);
    }

    public function testMakeCliFriendlyInvalidParameterFormatFail()
    {
        $_SERVER['argv'] = [basename(__FILE__), 'invalid-param-format'];

        $botManager = new BotManager($this->vitalParams);

        self::assertEmpty($_GET);

        $botManager->makeCliFriendly();

        self::assertEmpty($_GET);
    }

    public function testMakeCliFriendlySuccess()
    {
        $botManager = new BotManager($this->vitalParams);

        self::assertEmpty($_GET);

        $_SERVER['argv'] = [basename(__FILE__)];
        $botManager->makeCliFriendly();
        self::assertEmpty($_GET);

        $_SERVER['argv'] = [basename(__FILE__), 'l='];
        $botManager->makeCliFriendly();
        self::assertEquals(['l' => ''], $_GET);

        $_GET = [];
        $_SERVER['argv'] = [basename(__FILE__), 's=secret_12345', 'a=handle'];
        $botManager->makeCliFriendly();
        self::assertEquals(['s' => 'secret_12345', 'a' => 'handle'], $_GET);
    }

    public function testInitLogging()
    {
        $logging = [
            'logging' => [
                'debug'  => '/tmp/php-telegram-bot-debuglog.log',
                'error'  => '/tmp/php-telegram-bot-errorlog.log',
                'update' => '/tmp/php-telegram-bot-updatelog.log',
            ]
        ];
        $botManager = new BotManager(array_merge($this->vitalParams, $logging));

        self::assertFalse(TelegramLog::isDebugLogActive());
        self::assertFalse(TelegramLog::isErrorLogActive());
        self::assertFalse(TelegramLog::isUpdateLogActive());

        $botManager->initLogging();

        self::assertTrue(TelegramLog::isDebugLogActive());
        self::assertTrue(TelegramLog::isErrorLogActive());
        self::assertTrue(TelegramLog::isUpdateLogActive());
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Invalid access
     */
    public function testValidateSecretFail()
    {
        $_GET = ['s' => 'NOT_my_secret_12345'];
        $botManager = new BotManager(array_merge($this->vitalParams, ['secret' => 'my_secret_12345']));

        $botManager->validateSecret(true);
    }

    public function testValidateSecretSuccess()
    {
        $botManager = new BotManager(array_merge($this->vitalParams, ['secret' => 'my_secret_12345']));

        // Force validation to test non-CLI scenario.
        $_GET = ['s' => 'my_secret_12345'];
        $botManager->validateSecret(true);

        // Calling from CLI doesn't require a secret.
        $_GET = ['s' => 'whatever_on_cli'];
        $botManager->validateSecret();
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Invalid action
     */
    public function testValidateAndSetActionFailWithInvalidAction()
    {
        $_GET = ['a' => 'non-existent'];
        (new BotManager($this->vitalParams))->validateAndSetAction();
    }

    public function testValidateAndSetActionSuccess()
    {
        $botManager = new BotManager($this->vitalParams);

        // Default value.
        self::assertEquals('handle', $botManager->validateAndSetAction()->action);

        $validActions = ['set', 'unset', 'reset', 'handle'];
        foreach ($validActions as $action) {
            $_GET = ['a' => $action];
            self::assertEquals($action, $botManager->validateAndSetAction()->action);
        }
    }

    public function testValidateAndSetWebhookSuccess()
    {
        $botManager = new BotManager(array_merge($this->vitalParams, ['webhook' => 'https://web/hook.php']));

        $botManager->telegram = $this->getMockBuilder(Telegram::class)
            ->disableOriginalConstructor()
            ->setMethods(['setWebHook', 'unsetWebHook', 'getDescription'])
            ->getMock();
        $botManager->telegram->expects(static::any())
            ->method('setWebHook')
            ->with('https://web/hook.php?a=handle&s=secret_12345')
            ->will(static::returnSelf());
        $botManager->telegram->expects(static::any())
            ->method('unsetWebHook')
            ->will(static::returnSelf());
        $botManager->telegram->expects(static::any())
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

        $botManager->action = 'set';
        $botManager->validateAndSetWebhook();
        self::assertSame('Webhook set', $botManager->test_output);

        $botManager->validateAndSetWebhook();
        self::assertSame('Webhook already set', $botManager->test_output);

        $botManager->action = 'unset';
        $botManager->validateAndSetWebhook();
        self::assertSame('Webhook deleted', $botManager->test_output);

        $botManager->validateAndSetWebhook();
        self::assertSame('Webhook does not exist', $botManager->test_output);

        $botManager->action = 'reset';
        $botManager->validateAndSetWebhook();
        self::assertSame('Webhook set', $botManager->test_output);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Invalid webhook
     */
    public function testValidateAndSetWebhookFailSetWithoutWebhook()
    {
        $botManager = new BotManager(array_merge($this->vitalParams, ['webhook' => null]));
        $botManager->action = 'set';
        $botManager->validateAndSetWebhook();
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Invalid webhook
     */
    public function testValidateAndSetWebhookFailResetWithoutWebhook()
    {
        $botManager = new BotManager(array_merge($this->vitalParams, ['webhook' => null]));
        $botManager->action = 'reset';
        $botManager->validateAndSetWebhook();
    }

    public function testIsAction()
    {
        $botManager = new BotManager($this->vitalParams);

        $botManager->action = 'set';
        self::assertTrue($botManager->isAction('set'));
        self::assertTrue($botManager->isAction(['set']));
        self::assertTrue($botManager->isAction(['set', 'handle']));

        self::assertFalse($botManager->isAction('handle'));
        self::assertFalse($botManager->isAction(['unset', 'reset']));
    }

    public function testGetLoopTime()
    {
        $botManager = new BotManager($this->vitalParams);

        // Parameter not set.
        self::assertSame(0, $botManager->getLoopTime());

        $_GET = ['l' => 'text-string'];
        self::assertSame(604800, $botManager->getLoopTime());

        $_GET = ['l' => 0];
        self::assertSame(604800, $botManager->getLoopTime());

        $_GET = ['l' => -12345];
        self::assertSame(604800, $botManager->getLoopTime());

        $_GET = ['l' => 12345];
        self::assertSame(12345, $botManager->getLoopTime());

        $_GET = ['l' => '12345'];
        self::assertSame(12345, $botManager->getLoopTime());
    }
}
