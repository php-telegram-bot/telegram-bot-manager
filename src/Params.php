<?php declare(strict_types=1);
/**
 * This file is part of the TelegramBotManager package.
 *
 * (c) Armando Lüscher <armando@noplanman.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NPM\TelegramBotManager;

use NPM\TelegramBotManager\Exception\InvalidParamsException;

class Params
{
    /**
     * @var array List of valid script parameters.
     */
    private static $valid_script_params = [
        's',
        'a',
        'l',
        'i',
    ];

    /**
     * @var array List of vital parameters that must be passed.
     */
    private static $valid_vital_bot_params = [
        'api_key',
        'bot_username',
        'secret',
    ];

    /**
     * @var array List of valid extra parameters that can be passed.
     */
    private static $valid_extra_bot_params = [
        'validate_request',
        'webhook',
        'certificate',
        'max_connections',
        'allowed_updates',
        'logging',
        'limiter',
        'admins',
        'mysql',
        'download_path',
        'upload_path',
        'commands_paths',
        'command_configs',
        'botan_token',
        'custom_input',
    ];

    /**
     * @var array List of all params passed to the script.
     */
    private $script_params = [];

    /**
     * @var array List of all params passed at construction, predefined with defaults.
     */
    private $bot_params = [
        'validate_request' => true,
    ];

    /**
     * Params constructor.
     *
     * api_key (string) Telegram Bot API key
     * bot_username (string) Telegram Bot username
     * secret (string) Secret string to validate calls
     * validate_request (bool) Only allow webhook access from valid Telegram API IPs
     * webhook (string) URI of the webhook
     * certificate (string) Path to the self-signed certificate
     * max_connections (int) Maximum allowed simultaneous HTTPS connections to the webhook
     * allowed_updates (array) List the types of updates you want your bot to receive
     * logging (array) Array of logger files to set.
     * limiter (bool|array) Set limiter, as bool or options array.
     * admins (array) List of admins to enable.
     * mysql (array) MySQL credentials to use.
     * download_path (string) Custom download path to set.
     * upload_path (string) Custom upload path to set.
     * commands_paths (array) Custom commands paths to set.
     * command_configs (array) List of custom command configs.
     * botan_token (string) Botan token to enable botan.io support.
     * custom_input (string) Custom raw JSON string to use as input.
     *
     * @param array $params All params to set the bot up with.
     *
     * @throws \NPM\TelegramBotManager\Exception\InvalidParamsException
     */
    public function __construct(array $params)
    {
        $this->validateAndSetBotParams($params);
        $this->validateAndSetScriptParams();
    }

    /**
     * Validate and set up the vital and extra params.
     *
     * @param $params
     *
     * @return \NPM\TelegramBotManager\Params
     * @throws \NPM\TelegramBotManager\Exception\InvalidParamsException
     */
    private function validateAndSetBotParams($params): self
    {
        // Set all vital params.
        foreach (self::$valid_vital_bot_params as $vital_key) {
            if (!array_key_exists($vital_key, $params)) {
                throw new InvalidParamsException('Some vital info is missing: ' . $vital_key);
            }

            $this->bot_params[$vital_key] = $params[$vital_key];
        }

        // Set all extra params.
        foreach (self::$valid_extra_bot_params as $extra_key) {
            if (!array_key_exists($extra_key, $params)) {
                continue;
            }

            $this->bot_params[$extra_key] = $params[$extra_key];
        }

        return $this;
    }

    /**
     * Handle all script params, via web server handler or CLI.
     *
     * https://url/entry.php?s=<secret>&a=<action>&l=<loop>
     * $ php entry.php s=<secret> a=<action> l=<loop>
     *
     * @return \NPM\TelegramBotManager\Params
     */
    private function validateAndSetScriptParams(): self
    {
        $this->script_params = $_GET;

        // If we're running from CLI, properly set script parameters.
        if ('cli' === PHP_SAPI) {
            // We don't need the first arg (the file name).
            $args = array_slice($_SERVER['argv'], 1);

            /** @var array $args */
            foreach ($args as $arg) {
                @list($key, $val) = explode('=', $arg);
                isset($key, $val) && $this->script_params[$key] = $val;
            }
        }

        // Keep only valid ones.
        $this->script_params = array_intersect_key(
            $this->script_params,
            array_fill_keys(self::$valid_script_params, null)
        );

        return $this;
    }

    /**
     * Get a specific bot param.
     *
     * @param string $param
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getBotParam(string $param, $default = null)
    {
        return $this->bot_params[$param] ?? $default;
    }

    /**
     * Get an array of all bot params.
     *
     * @return array
     */
    public function getBotParams(): array
    {
        return $this->bot_params;
    }

    /**
     * Get a specific script param.
     *
     * @param string $param
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getScriptParam(string $param, $default = null)
    {
        return $this->script_params[$param] ?? $default;
    }

    /**
     * Get an array of all script params.
     *
     * @return array
     */
    public function getScriptParams(): array
    {
        return $this->script_params;
    }
}
