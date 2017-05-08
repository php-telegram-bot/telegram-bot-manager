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

use TelegramBot\TelegramBotManager\Params;

class ParamsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var array Demo vital parameters.
     */
    public static $demo_vital_params = [
        'api_key' => '12345:api_key',
    ];

    /**
     * @var array Demo extra parameters.
     */
    public static $demo_extra_params = [
        'bot_username'     => 'test_bot',
        'secret'           => 'secret_12345',
        'validate_request' => true,
        'webhook'          => [
            'url'             => 'https://php.telegram.bot/manager.php',
            'certificate'     => __DIR__ . '/server.crt',
            'max_connections' => 20,
            'allowed_updates' => ['message', 'edited_channel_post', 'callback_query'],
        ],
        'limiter'          => [
            'enabled' => false,
            'options' => [
                'interval' => 0.5,
            ],
        ],
        'admins'           => [1],
        'mysql'            => [
            'host'     => '127.0.0.1',
            'user'     => 'root',
            'password' => 'root',
            'database' => 'telegram_bot',
        ],
        'paths'            => [
            'download' => __DIR__ . '/Download',
            'upload'   => __DIR__ . '/Upload',
        ],
        'commands'         => [
            'paths'   => [
                __DIR__ . '/CustomCommands',
            ],
            'configs' => [
                'weather'       => ['owm_api_key' => 'owm_api_key_12345'],
                'sendtochannel' => ['your_channel' => '@my_channel'],
            ],
        ],
        'botan'            => [
            'token' => 'botan_12345',
        ],
        'cron'             => [
            'groups' => [
                'default'     => [
                    '/default_cron_command',
                ],
                'maintenance' => [
                    '/db_cleanup',
                    '/db_repair',
                    '/log_rotate',
                ],
            ],
        ],
        'custom_input'     => '{"some":"raw", "json":"update"}',
    ];

    public function setUp()
    {
        // Make sure we start with a clean slate.
        $_GET = [];
    }

    public function testConstruct()
    {
        new Params(self::$demo_vital_params);
        self::assertTrue(true);
    }

    /**
     * @expectedException \TelegramBot\TelegramBotManager\Exception\InvalidParamsException
     * @expectedExceptionMessage Some vital info is missing: api_key
     */
    public function testConstructWithoutApiKey()
    {
        new Params([]);
    }

    /**
     * @expectedException \TelegramBot\TelegramBotManager\Exception\InvalidParamsException
     * @expectedExceptionMessage Some vital info is missing: secret
     */
    public function testConstructWithWebhookWithoutSecret()
    {
        new Params([
            'api_key' => '12345:api_key',
            'webhook' => self::$demo_extra_params['webhook'],
        ]);
    }

    public function testScriptParamInvalidParameterFormat()
    {
        $_SERVER['argv'] = [basename(__FILE__), 'invalid-param-format'];

        self::assertEmpty($_GET);

        $params = new Params(self::$demo_vital_params);

        self::assertEmpty($params->getScriptParams());
    }

    public function testSetAndGetScriptParams()
    {
        $_SERVER['argv'] = [basename(__FILE__)];
        $params          = new Params(self::$demo_vital_params);
        self::assertEmpty($params->getScriptParams());

        $_SERVER['argv'] = [basename(__FILE__), 'l='];
        $params          = new Params(self::$demo_vital_params);
        self::assertEquals('', $params->getScriptParam('l'));
        self::assertEquals(['l' => ''], $params->getScriptParams());

        $_SERVER['argv'] = [basename(__FILE__), 'a=handle', 's=secret_12345'];
        $params          = new Params(self::$demo_vital_params);
        self::assertEquals('handle', $params->getScriptParam('a'));
        self::assertEquals('secret_12345', $params->getScriptParam('s'));
        self::assertEquals(['a' => 'handle', 's' => 'secret_12345'], $params->getScriptParams());

        self::assertNull($params->getScriptParam('non-existent'));
    }

    public function testSetAndGetBotParams()
    {
        $all_params = array_merge(self::$demo_vital_params, self::$demo_extra_params);
        $params     = new Params($all_params);

        // All params.
        self::assertEquals($all_params, $params->getBotParams());

        // Vitals.
        foreach (self::$demo_vital_params as $vital_param_key => $vital_param) {
            self::assertEquals(self::$demo_vital_params[$vital_param_key], $params->getBotParam($vital_param_key));
        }

        // Extras.
        foreach (self::$demo_extra_params as $extra_param_key => $extra_param) {
            self::assertEquals(self::$demo_extra_params[$extra_param_key], $params->getBotParam($extra_param_key));
        }

        // Test default return values.
        self::assertNull($params->getBotParam('non-existent'));
        self::assertTrue($params->getBotParam('non-existent', true));
        self::assertSame('some-default', $params->getBotParam('non-existent', 'some-default'));

        // Test array-dot notation.
        self::assertSame([__DIR__ . '/CustomCommands'], $params->getBotParam('commands.paths'));
        self::assertSame('owm_api_key_12345', $params->getBotParam('commands.configs.weather.owm_api_key'));
    }
}
