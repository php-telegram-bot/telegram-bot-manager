<?php declare(strict_types=1);
/**
 * This file is part of the TelegramBotManager package.
 *
 * (c) Armando Lüscher <armando@noplanman.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TelegramBot\TelegramBotManager;

use TelegramBot\TelegramBotManager\Exception\InvalidParamsException;

class Params
{
    /**
     * @var array List of valid script parameters.
     */
    private static $valid_script_params = [
        's', // secret
        'a', // action
        'l', // loop
        'i', // interval
        'g', // group (for cron)
    ];

    /**
     * @var array List of vital parameters that must be passed.
     */
    private static $valid_vital_bot_params = [
        'api_key',
    ];

    /**
     * @var array List of valid extra parameters that can be passed.
     */
    private static $valid_extra_bot_params = [
        'bot_username',
        'secret',
        'validate_request',
        'valid_ips',
        'webhook',
        'logging',
        'limiter',
        'admins',
        'mysql',
        'paths',
        'commands',
        'botan',
        'cron',
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
     * validate_request (bool) Only allow webhook access from valid Telegram API IPs and defined valid_ips
     * valid_ips (array) Any IPs, besides Telegram API IPs, that are allowed to access the webhook
     * webhook (array)
     * - url (string) URI of the webhook
     * - certificate (string) Path to the self-signed certificate
     * - max_connections (int) Maximum allowed simultaneous HTTPS connections to the webhook
     * - allowed_updates (array) List the types of updates you want your bot to receive
     * logging (array) Array of logger files to set.
     * limiter (array)
     * - enabled (bool) Set limiter.
     * - options (array) Limiter options.
     * admins (array) List of admins to enable.
     * mysql (array) MySQL credentials to use.
     * paths (array)
     * - download (string) Custom download path to set.
     * - upload (string) Custom upload path to set.
     * commands (array)
     * - paths (array) Custom commands paths to set.
     * - configs (array) List of custom command configs.
     * botan (array)
     * - token (string) Botan token to enable botan.io support.
     * - options (array) Botan options.
     * custom_input (string) Custom raw JSON string to use as input.
     * cron (array)
     * - groups (array) Groups of cron commands to run.
     *   - default (array) Default group of cron commands.
     *
     * @param array $params All params to set the bot up with.
     *
     * @throws \TelegramBot\TelegramBotManager\Exception\InvalidParamsException
     */
    public function __construct(array $params)
    {
        $this->validateAndSetBotParams($params);
        $this->validateAndSetScriptParams();
    }

    /**
     * Validate and set up the vital and extra params.
     *
     * @param array $params
     *
     * @return \TelegramBot\TelegramBotManager\Params
     * @throws \TelegramBot\TelegramBotManager\Exception\InvalidParamsException
     */
    private function validateAndSetBotParams(array $params): self
    {
        $this->validateAndSetBotParamsVital($params);
        $this->validateAndSetBotParamsSpecial($params);
        $this->validateAndSetBotParamsExtra($params);

        return $this;
    }

    /**
     * Set all vital params.
     *
     * @param array $params
     *
     * @throws \TelegramBot\TelegramBotManager\Exception\InvalidParamsException
     */
    private function validateAndSetBotParamsVital(array $params)
    {
        foreach (self::$valid_vital_bot_params as $vital_key) {
            if (!array_key_exists($vital_key, $params)) {
                throw new InvalidParamsException('Some vital info is missing: ' . $vital_key);
            }

            $this->bot_params[$vital_key] = $params[$vital_key];
        }
    }

    /**
     * Special case parameters.
     *
     * @param array $params
     *
     * @throws \TelegramBot\TelegramBotManager\Exception\InvalidParamsException
     */
    private function validateAndSetBotParamsSpecial(array $params)
    {
        // Special case, where secret MUST be defined if we have a webhook.
        if (($params['webhook'] ?? null) && !($params['secret'] ?? null)) {
            throw new InvalidParamsException('Some vital info is missing: secret');
        }
    }

    /**
     * Set all extra params.
     *
     * @param array $params
     */
    private function validateAndSetBotParamsExtra(array $params)
    {
        foreach (self::$valid_extra_bot_params as $extra_key) {
            if (!array_key_exists($extra_key, $params)) {
                continue;
            }

            $this->bot_params[$extra_key] = $params[$extra_key];
        }
    }

    /**
     * Handle all script params, via web server handler or CLI.
     *
     * https://url/entry.php?s=<secret>&a=<action>&l=<loop>
     * $ php entry.php s=<secret> a=<action> l=<loop>
     *
     * @return \TelegramBot\TelegramBotManager\Params
     */
    private function validateAndSetScriptParams(): self
    {
        $this->setScriptParams();
        $this->validateScriptParams();

        return $this;
    }

    /**
     * Set script parameters from query string or CLI.
     */
    private function setScriptParams()
    {
        $this->script_params = $_GET;

        // If we're not running from CLI, script parameters are already set from $_GET.
        if ('cli' !== PHP_SAPI) {
            return;
        }

        // We don't need the first arg (the file name).
        $args = array_slice($_SERVER['argv'], 1);

        /** @var array $args */
        foreach ($args as $arg) {
            @list($key, $val) = explode('=', $arg);
            isset($key, $val) && $this->script_params[$key] = $val;
        }
    }

    /**
     * Keep only valid script parameters.
     */
    private function validateScriptParams()
    {
        $this->script_params = array_intersect_key(
            $this->script_params,
            array_fill_keys(self::$valid_script_params, null)
        );
    }

    /**
     * Get a specific bot param, allowing array-dot notation.
     *
     * @param string $param
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getBotParam(string $param, $default = null)
    {
        $param_path = explode('.', $param);

        $value = $this->bot_params[array_shift($param_path)] ?? null;
        foreach ($param_path as $sub_param_key) {
            $value = $value[$sub_param_key] ?? null;
            if (null === $value) {
                break;
            }
        }

        return $value ?? $default;
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
