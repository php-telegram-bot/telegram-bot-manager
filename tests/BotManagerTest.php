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

use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use TelegramBot\TelegramBotManager\BotManager;
use TelegramBot\TelegramBotManager\Exception\InvalidAccessException;
use TelegramBot\TelegramBotManager\Exception\InvalidParamsException;

/**
 * Class BotManagerTest.php
 *
 * Leave all member variables public to allow easy modification.
 *
 * @package TelegramBot\TelegramBotManager
 */
class BotManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var array Live params for testing with live bot (get set in BotManagerTest->setUp()).
     */
    public static $live_params = [];

    public static function setUpBeforeClass(): void
    {
        self::$live_params = [
            'api_key'      => getenv('API_KEY'),
            'bot_username' => getenv('BOT_USERNAME'),
            'secret'       => 'super-secret',
            'mysql'        => [
                'host'     => PHPUNIT_DB_HOST,
                'port'     => PHPUNIT_DB_PORT,
                'user'     => PHPUNIT_DB_USER,
                'password' => PHPUNIT_DB_PASSWORD,
                'database' => PHPUNIT_DB_DATABASE,
            ],
        ];
    }

    /**
     * To test the live commands, act as if we're being called by Telegram.
     */
    protected function makeRequestValid(): void
    {
        $_SERVER['REMOTE_ADDR'] = '149.154.167.197';
    }

    public function testSetParameters(): void
    {
        $botManager = new BotManager(array_merge(ParamsTest::$demo_vital_params, [
            'admins' => [1],            // valid
            'paths'  => [               // valid
                'upload' => '/upload/path',
            ],
            'paramX' => 'something'     // invalid
        ]));
        $params     = $botManager->getParams();
        self::assertEquals([1], $params->getBotParam('admins'));
        self::assertEquals('/upload/path', $params->getBotParam('paths.upload'));
        self::assertNull($params->getBotParam('paramX'));
    }

    public function testInTest(): void
    {
        self::assertTrue(BotManager::inTest());
    }

    public function testNoVitalsFail(): void
    {
        $this->expectException(InvalidParamsException::class);
        $this->expectExceptionMessage('Some vital info is missing: api_key');
        new BotManager([]);
    }

    public function testIncompleteVitalsFail(): void
    {
        $this->expectException(InvalidParamsException::class);
        $this->expectExceptionMessage('Some vital info is missing: secret');

        new BotManager([
            'api_key' => '12345:api_key',
            'webhook' => ['url' => 'https://web/hook.php'],
        ]);
    }

    public function testVitalsSuccess(): void
    {
        new BotManager(ParamsTest::$demo_vital_params);
        self::assertTrue(true);
    }

    public function testValidTelegramObject(): void
    {
        $bot      = new BotManager(ParamsTest::$demo_vital_params);
        $telegram = $bot->getTelegram();

        self::assertSame(ParamsTest::$demo_vital_params['api_key'], $telegram->getApiKey());
    }

    public function testValidateSecretFail(): void
    {
        $this->expectException(InvalidAccessException::class);
        $this->expectExceptionMessage('Invalid access');

        $_GET       = ['s' => 'NOT_my_secret_12345'];
        $botManager = new BotManager(array_merge(ParamsTest::$demo_vital_params, [
            'secret' => 'my_secret_12345',
        ]));

        $botManager->validateSecret(true);
    }

    public function testValidateSecretSuccess(): void
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

        self::assertTrue(true);
    }

    public function testValidateAndSetWebhookSuccess(): void
    {
        self::markTestSkipped('Mocking requires rewrite, skip for now...');

        $botManager = new BotManager(array_merge(ParamsTest::$demo_vital_params, [
            'webhook' => ['url' => 'https://web/hook.php'],
            'secret'  => 'secret_12345',
        ]));

        TestHelpers::setObjectProperty(
            $botManager,
            'telegram',
            $this->getMockBuilder(Telegram::class)
                ->disableOriginalConstructor()
                ->setMethods(['setWebhook', 'deleteWebhook', 'getDescription'])
                ->getMock()
        );

        $telegram = $botManager->getTelegram();

        /** @var \PHPUnit_Framework_MockObject_MockObject $telegram */
        $telegram
            ->method('setWebhook')
            ->with('https://web/hook.php?a=handle&s=secret_12345')
            ->will(static::returnSelf());
        $telegram
            ->method('deleteWebhook')
            ->will(static::returnSelf());
        $telegram
            ->method('getDescription')
            ->will(static::onConsecutiveCalls(
                'Webhook was set', // set
                'Webhook is already set',
                'Webhook was deleted', // reset
                'Webhook was set',
                'Webhook was deleted', //unset
                'Webhook is already deleted'
            ));

        TestHelpers::setObjectProperty($botManager->getAction(), 'action', 'set');
        $output = $botManager->validateAndSetWebhook()->getOutput();
        self::assertSame('Webhook was set' . PHP_EOL, $output);

        $output = $botManager->validateAndSetWebhook()->getOutput();
        self::assertSame('Webhook is already set' . PHP_EOL, $output);

        TestHelpers::setObjectProperty($botManager->getAction(), 'action', 'reset');
        $output = $botManager->validateAndSetWebhook()->getOutput();
        self::assertSame('Webhook was deleted' . PHP_EOL . 'Webhook was set' . PHP_EOL, $output);

        TestHelpers::setObjectProperty($botManager->getAction(), 'action', 'unset');
        $output = $botManager->validateAndSetWebhook()->getOutput();
        self::assertSame('Webhook was deleted' . PHP_EOL, $output);

        $output = $botManager->validateAndSetWebhook()->getOutput();
        self::assertSame('Webhook is already deleted' . PHP_EOL, $output);
    }

    /**
     * @group live
     */
    public function testValidateAndSetWebhookSuccessLiveBot(): void
    {
        $botManager = new BotManager(array_merge(self::$live_params, [
            'webhook' => ['url' => 'https://example.com/hook.php'],
        ]));

        // Make sure the webhook isn't set to start with.
        TestHelpers::setObjectProperty($botManager->getAction(), 'action', 'unset');
        $botManager->validateAndSetWebhook()->getOutput();
        sleep(1);
        TestHelpers::setObjectProperty($botManager->getAction(), 'action', 'set');
        $output = $botManager->validateAndSetWebhook()->getOutput();
        self::assertSame('Webhook was set' . PHP_EOL, $output);
        sleep(1);
        $output = $botManager->validateAndSetWebhook()->getOutput();
        self::assertSame('Webhook is already set' . PHP_EOL, $output);
        sleep(1);
        TestHelpers::setObjectProperty($botManager->getAction(), 'action', 'reset');
        $output = $botManager->validateAndSetWebhook()->getOutput();
        self::assertSame('Webhook was deleted' . PHP_EOL . 'Webhook was set' . PHP_EOL, $output);
        sleep(1);
        TestHelpers::setObjectProperty($botManager->getAction(), 'action', 'unset');
        $output = $botManager->validateAndSetWebhook()->getOutput();
        self::assertSame('Webhook was deleted' . PHP_EOL, $output);
        sleep(1);
        $output = $botManager->validateAndSetWebhook()->getOutput();
        self::assertSame('Webhook is already deleted' . PHP_EOL, $output);
    }

    /**
     * @group live
     * @runInSeparateProcess
     */
    public function testDeleteWebhookViaRunLiveBot(): void
    {
        $this->makeRequestValid();
        $_GET       = ['a' => 'unset'];
        $botManager = new BotManager(array_merge(self::$live_params, [
            'webhook' => ['url' => 'https://example.com/hook.php'],
        ]));
        $output     = $botManager->run()->getOutput();

        self::assertRegExp('/Webhook.+deleted/', $output);
    }

    public function testValidateAndSetWebhookFailSetWithoutWebhook(): void
    {
        $this->expectException(\TelegramBot\TelegramBotManager\Exception\InvalidWebhookException::class);
        $this->expectExceptionMessage('Invalid webhook');

        $_GET = ['a' => 'set'];
        (new BotManager(array_merge(ParamsTest::$demo_vital_params, [
            'webhook' => null,
        ])))->validateAndSetWebhook();
    }

    public function testValidateAndSetWebhookFailResetWithoutWebhook(): void
    {
        $this->expectException(\TelegramBot\TelegramBotManager\Exception\InvalidWebhookException::class);
        $this->expectExceptionMessage('Invalid webhook');

        $_GET = ['a' => 'reset'];
        (new BotManager(array_merge(ParamsTest::$demo_vital_params, [
            'webhook' => null,
        ])))->validateAndSetWebhook();
    }

    public function testGetLoopTime(): void
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

    public function testGetLoopInterval(): void
    {
        // Parameter not set.
        self::assertSame(2, (new BotManager(ParamsTest::$demo_vital_params))->getLoopInterval());

        $_GET = ['i' => ''];
        self::assertSame(2, (new BotManager(ParamsTest::$demo_vital_params))->getLoopInterval());

        $_GET = ['i' => '     '];
        self::assertSame(2, (new BotManager(ParamsTest::$demo_vital_params))->getLoopInterval());

        $_GET = ['i' => 'text-string'];
        self::assertSame(1, (new BotManager(ParamsTest::$demo_vital_params))->getLoopInterval());

        $_GET = ['i' => 0];
        self::assertSame(1, (new BotManager(ParamsTest::$demo_vital_params))->getLoopInterval());

        $_GET = ['i' => -12345];
        self::assertSame(1, (new BotManager(ParamsTest::$demo_vital_params))->getLoopInterval());

        $_GET = ['i' => 12345];
        self::assertSame(12345, (new BotManager(ParamsTest::$demo_vital_params))->getLoopInterval());

        $_GET = ['i' => '-12345'];
        self::assertSame(1, (new BotManager(ParamsTest::$demo_vital_params))->getLoopInterval());

        $_GET = ['i' => '12345'];
        self::assertSame(12345, (new BotManager(ParamsTest::$demo_vital_params))->getLoopInterval());
    }

    public function testSetBotExtras(): void
    {
        $extras     = [
            'limiter'  => [
                'enabled' => false,
            ],
            'admins'   => [1, 2, 3],
            'paths'    => [
                'download' => __DIR__ . '/Download',
                'upload'   => __DIR__ . '/Upload',
            ],
            'commands' => [
                'configs' => [
                    'weather' => [
                        'owm_api_key' => 'owm_api_key_12345',
                    ],
                ],
            ],
        ];
        $botManager = new BotManager(array_merge(ParamsTest::$demo_vital_params, $extras));

        $botManager->setBotExtras();
        $telegram = $botManager->getTelegram();

        // @todo Find a way to test that the limiter is enabled.
        // self::assertAttributeEquals($extras['limiter']['enabled'], 'limiter_enabled', Request::class);

        self::assertEquals($extras['admins'], $telegram->getAdminList());
        self::assertEquals($extras['paths']['download'], $telegram->getDownloadPath());
        self::assertEquals($extras['paths']['upload'], $telegram->getUploadPath());
        self::assertEquals($extras['commands']['configs']['weather'], $telegram->getCommandConfig('weather'));
    }

    public function testGetOutput(): void
    {
        $botManager = new BotManager(ParamsTest::$demo_vital_params);
        self::assertEmpty($botManager->getOutput());

        TestHelpers::setObjectProperty($botManager, 'output', 'some demo output');

        self::assertEquals('some demo output', $botManager->getOutput());
        self::assertEmpty($botManager->getOutput());

        TestHelpers::callObjectMethod($botManager, 'handleOutput', ['some more demo output...']);
        TestHelpers::callObjectMethod($botManager, 'handleOutput', ['...and even more!!']);
        self::assertEquals('some more demo output......and even more!!', $botManager->getOutput());
        self::assertEmpty($botManager->getOutput());
    }

    public function testIsValidRequestValidateByDefault(): void
    {
        $botManager = new BotManager(ParamsTest::$demo_vital_params);
        self::assertTrue($botManager->getParams()->getBotParam('validate_request'));
    }

    public function testIsValidRequestFailValidation(): void
    {
        $botManager = new BotManager(ParamsTest::$demo_vital_params);

        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP'], $_SERVER['REMOTE_ADDR']);

        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            $_SERVER[$key] = '1.1.1.1';
            self::assertFalse($botManager->isValidRequest());
            unset($_SERVER[$key]);
        }
    }

    public function testIsValidRequestSkipValidation(): void
    {
        $botManager = new BotManager(array_merge(ParamsTest::$demo_vital_params, [
            'validate_request' => false,
        ]));

        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP'], $_SERVER['REMOTE_ADDR']);

        self::assertTrue($botManager->isValidRequest());
    }

    public function testIsValidRequestValidate(): void
    {
        $botManager = new BotManager(ParamsTest::$demo_vital_params);

        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP'], $_SERVER['REMOTE_ADDR']);

        // Lower range.
        $_SERVER['REMOTE_ADDR'] = '149.154.159.255';
        self::assertFalse($botManager->isValidRequest());
        $_SERVER['REMOTE_ADDR'] = '149.154.160.0';
        self::assertTrue($botManager->isValidRequest());
        $_SERVER['REMOTE_ADDR'] = '91.108.3.255';
        self::assertFalse($botManager->isValidRequest());
        $_SERVER['REMOTE_ADDR'] = '91.108.4.0';
        self::assertTrue($botManager->isValidRequest());

        // Upper range.
        $_SERVER['REMOTE_ADDR'] = '149.154.175.255';
        self::assertTrue($botManager->isValidRequest());
        $_SERVER['REMOTE_ADDR'] = '149.154.176.0';
        self::assertFalse($botManager->isValidRequest());
        $_SERVER['REMOTE_ADDR'] = '91.108.7.255';
        self::assertTrue($botManager->isValidRequest());
        $_SERVER['REMOTE_ADDR'] = '91.108.8.0';
        self::assertFalse($botManager->isValidRequest());
    }

    /**
     * @group live
     * @runInSeparateProcess
     */
    public function testGetUpdatesLiveBot(): void
    {
        $this->makeRequestValid();
        $botManager = new BotManager(self::$live_params);
        $output     = $botManager->run()->getOutput();
        self::assertStringContainsString('Updates processed: 0', $output);
    }

    /**
     * @group live
     * @runInSeparateProcess
     */
    public function testGetUpdatesLoopLiveBot(): void
    {
        $this->makeRequestValid();
        // Webhook MUST NOT be set for this to work!
        $this->testDeleteWebhookViaRunLiveBot();

        // Looping for 5 seconds should be enough to get a result.
        $_GET       = ['l' => 5];
        $botManager = new BotManager(self::$live_params);
        $output     = $botManager->run()->getOutput();
        self::assertStringContainsString('Looping getUpdates until', $output);
        self::assertStringContainsString('Updates processed: 0', $output);
    }
}
