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

class ParamsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var array Demo vital parameters.
     */
    public static $demo_vital_params = [
        'api_key' => 'api_key_12345',
        'botname' => 'test_bot',
        'secret'  => 'secret_12345',
    ];

    /**
     * @var array Demo extra parameters.
     */
    public static $demo_extra_params = [
        'webhook'         => 'https://php.telegram.bot/manager.php',
        'selfcrt'         => __DIR__ . '/server.crt',
        'admins'          => [1],
        'mysql'           => ['host' => '127.0.0.1', 'user' => 'root', 'password' => 'root', 'database' => 'telegram_bot'],
        'download_path'   => __DIR__ . '/Download',
        'upload_path'     => __DIR__ . '/Upload',
        'commands_paths'  => __DIR__ . '/CustomCommands',
        'command_configs' => [
            'weather'       => ['owm_api_key' => 'owm_api_key_12345'],
            'sendtochannel' => ['your_channel' => '@my_channel'],
        ],
        'botan_token'     => 'botan_12345',
        'custom_input'    => '{"some":"raw", "json":"update"}',
    ];

    public function testConstruct()
    {
        new Params(self::$demo_vital_params);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Some vital info is missing: api_key
     */
    public function testConstructWithoutApiKey()
    {
        new Params([
            'botname' => 'test_bot',
            'secret'  => 'secret_12345',
        ]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Some vital info is missing: botname
     */
    public function testConstructWithoutBotname()
    {
        new Params([
            'api_key' => 'api_key_12345',
            'secret'  => 'secret_12345',
        ]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Some vital info is missing: secret
     */
    public function testConstructWithoutSecret()
    {
        new Params([
            'api_key' => 'api_key_12345',
            'botname' => 'test_bot',
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
        $params = new Params(self::$demo_vital_params);
        self::assertEmpty($params->getScriptParams());

        $_SERVER['argv'] = [basename(__FILE__), 'l='];
        $params = new Params(self::$demo_vital_params);
        self::assertEquals('', $params->getScriptParam('l'));
        self::assertEquals(['l' => ''], $params->getScriptParams());

        $_SERVER['argv'] = [basename(__FILE__), 'a=handle', 's=secret_12345'];
        $params = new Params(self::$demo_vital_params);
        self::assertEquals('handle', $params->getScriptParam('a'));
        self::assertEquals('secret_12345', $params->getScriptParam('s'));
        self::assertEquals(['a' => 'handle', 's' => 'secret_12345'], $params->getScriptParams());

        // Test some weird values.
        self::assertNull($params->getScriptParam(null));
        self::assertNull($params->getScriptParam(true));
        self::assertNull($params->getScriptParam(1));
        self::assertNull($params->getScriptParam('non-existent'));
    }

    public function testSetAndGetBotParams()
    {
        $all_params = array_merge(self::$demo_vital_params, self::$demo_extra_params);
        $params = new Params($all_params);

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

        // Test some weird values.
        self::assertNull($params->getBotParam(null));
        self::assertNull($params->getBotParam(true));
        self::assertNull($params->getBotParam(1));
        self::assertNull($params->getBotParam('non-existent'));
    }
}
