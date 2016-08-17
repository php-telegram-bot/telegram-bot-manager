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
use Longman\TelegramBot\Entities;

/**
 * Class BotManager.php
 *
 * Leave all member variables public to allow easy modification.
 *
 * @package NPM\TelegramBotManager
 */
class BotManager
{
    /**
     * @var Telegram
     */
    public $telegram;

    /**
     * @var string The output for testing, instead of echoing
     */
    public $test_output;

    /**
     * @var string Telegram Bot API key
     */
    protected $api_key = '';

    /**
     * @var string Telegram Bot name
     */
    public $botname;

    /**
     * @var string Secret string to validate calls
     */
    public $secret;

    /**
     * @var string Action to be executed
     */
    public $action = 'handle';

    /**
     * @var string URI of the webhook
     */
    public $webhook;

    /**
     * @var string Path to the self-signed certificate
     */
    public $selfcrt;

    /**
     * @var array List of valid actions that can be called
     */
    private static $valid_actions = [
        'set',
        'unset',
        'reset',
        'handle'
    ];

    /**
     * @var array List of valid extra parameters that can be passed
     */
    private static $valid_params = [
        'api_key',
        'botname',
        'secret',
        'webhook',
        'selfcrt',
        'logging',
        'admins',
        'mysql',
        'download_path',
        'upload_path',
        'commands_paths',
        'command_configs',
        'botan_token',
        'custom_input'
    ];


    /**
     * BotManager constructor that assigns all necessary member variables.
     *
     * @param array $vars
     *
     * @throws \Exception
     */
    public function __construct(array $vars)
    {
        if (!isset($vars['api_key'], $vars['botname'], $vars['secret'])) {
            throw new \Exception('Some vital info is missing (api_key, botname or secret)');
        }

        // Set all vital and extra parameters.
        foreach ($vars as $var => $value) {
            in_array($var, self::$valid_params, true) && $this->$var = $value;
        }
    }

    /**
     * Run this thing in all its glory!
     *
     * @throws \Exception
     */
    public function run()
    {
        // If this script is called via CLI, make it work just the same.
        $this->makeCliFriendly();

        // Initialise logging.
        $this->initLogging();

        // Make sure this is a valid call.
        $this->validateSecret();

        // Check for a valid action and set member variable.
        $this->validateAndSetAction();

        // Set up a new Telegram instance.
        $this->telegram = new Telegram($this->api_key, $this->botname);

        if ($this->isAction(['set', 'unset', 'reset'])) {
            $this->validateAndSetWebhook();
        } elseif ($this->isAction('handle')) {
            // Set any extras.
            $this->setBotExtras();
            $this->handleRequest();
        }

        return $this;
    }

    /**
     * Check if this script is being called from CLI.
     *
     * @return bool
     */
    public function isCli()
    {
        return PHP_SAPI === 'cli';
    }

    /**
     * Allow this script to be called via CLI.
     *
     * $ php entry.php s=<secret> a=<action> l=<loop>
     */
    public function makeCliFriendly()
    {
        // If we're running from CLI, properly set $_GET.
        if ($this->isCli()) {
            // We don't need the first arg (the file name).
            $args = array_slice($_SERVER['argv'], 1);

            foreach ($args as $arg) {
                @list($key, $val) = explode('=', $arg);
                isset($key, $val) && $_GET[$key] = $val;
            }
        }

        return $this;
    }

    /**
     * Initialise all loggers.
     */
    public function initLogging()
    {
        if (isset($this->logging) && is_array($this->logging)) {
            foreach ($this->logging as $logger => $logfile) {
                ('debug' === $logger) && TelegramLog::initDebugLog($logfile);
                ('error' === $logger) && TelegramLog::initErrorLog($logfile);
                ('update' === $logger) && TelegramLog::initUpdateLog($logfile);
            }
        }

        return $this;
    }

    /**
     * Make sure the passed secret is valid.
     *
     * @param bool $force Force validation, even on CLI.
     *
     * @return $this
     * @throws \Exception
     */
    public function validateSecret($force = false)
    {
        // If we're running from CLI, secret isn't necessary.
        if ($force || !$this->isCli()) {
            $secretGet = isset($_GET['s']) ? (string)$_GET['s'] : '';
            if (empty($this->secret) || $secretGet !== $this->secret) {
                throw new \Exception('Invalid access');
            }
        }

        return $this;
    }

    /**
     * Make sure the action is valid and set the member variable.
     *
     * @throws \Exception
     */
    public function validateAndSetAction()
    {
        // Only set the action if it has been passed, else use the default.
        isset($_GET['a']) && $this->action = (string)$_GET['a'];

        if (!$this->isAction(self::$valid_actions)) {
            throw new \Exception('Invalid action');
        }

        return $this;
    }

    /**
     * Make sure the webhook is valid and perform the requested webhook operation.
     *
     * @throws \Exception
     */
    public function validateAndSetWebhook()
    {
        if (empty($this->webhook) && $this->isAction(['set', 'reset'])) {
            throw new \Exception('Invalid webhook');
        }

        if ($this->isAction(['unset', 'reset'])) {
            $this->test_output = $this->telegram->unsetWebHook()->getDescription();
        }
        if ($this->isAction(['set', 'reset'])) {
            $this->test_output = $this->telegram->setWebHook(
                $this->webhook . '?a=handle&s=' . $this->secret,
                $this->selfcrt
            )->getDescription();
        }

        (@constant('PHPUNIT_TEST') !== true) && print($this->test_output . PHP_EOL);

        return $this;
    }

    /**
     * Check if the current action is one of the passed ones.
     *
     * @param $actions
     *
     * @return bool
     */
    public function isAction($actions)
    {
        // Make sure we're playing with an array without empty values.
        $actions = array_filter((array)$actions);

        return in_array($this->action, $actions, true);
    }

    /**
     * Get the param of how long (in seconds) the script should loop.
     *
     * @return int
     */
    public function getLoopTime()
    {
        $loop_time = 0;

        if (isset($_GET['l'])) {
            $loop_time = (int)$_GET['l'];
            if ($loop_time <= 0) {
                $loop_time = 604800; // 7 days.
            }
        }

        return $loop_time;
    }

    /**
     * Handle the request, which calls either the Webhook or getUpdates method respectively.
     *
     * @throws \Exception
     */
    public function handleRequest()
    {
        if (empty($this->webhook)) {
            if ($loop_time = $this->getLoopTime()) {
                $this->handleGetUpdatesLoop($loop_time);
            } else {
                $this->handleGetUpdates();
            }
        } else {
            $this->handleWebhook();
        }

        return $this;
    }

    /**
     * Loop the getUpdates method for the passed amount of seconds.
     *
     * @param $loop_time_in_seconds int
     *
     * @return $this
     */
    public function handleGetUpdatesLoop($loop_time_in_seconds)
    {
        // Remember the time we started this loop.
        $now = time();

        echo 'Looping getUpdates until ' . date('Y-m-d H:i:s', $now + $loop_time_in_seconds) . PHP_EOL;

        while ($now > time() - $loop_time_in_seconds) {
            $this->handleGetUpdates();

            // Chill a bit.
            sleep(2);
        }

        return $this;
    }

    /**
     * Handle the updates using the getUpdates method.
     */
    public function handleGetUpdates()
    {
        echo date('Y-m-d H:i:s', time()) . ' - ';

        $response = $this->telegram->handleGetUpdates();
        if ($response->isOk()) {
            $results = array_filter((array)$response->getResult());

            printf('Updates processed: %d' . PHP_EOL, count($results));

            /** @var Entities\Update $result */
            foreach ($results as $result) {
                $chat_id = 0;
                $text    = 'Nothing';

                $update_content = $result->getUpdateContent();
                if ($update_content instanceof Entities\Message) {
                    $chat_id = $update_content->getFrom()->getId();
                    $text    = $update_content->getText();
                } elseif ($update_content instanceof Entities\InlineQuery || $update_content instanceof Entities\ChosenInlineResult) {
                    $chat_id = $update_content->getFrom()->getId();
                    $text    = $update_content->getQuery();
                }

                printf(
                    '%d: %s' . PHP_EOL,
                    $chat_id,
                    preg_replace('/\s+/', ' ', trim($text))
                );
            }
        } else {
            printf('Failed to fetch updates: %s' . PHP_EOL, $response->printError());
        }

        return $this;
    }

    /**
     * Handle the updates using the Webhook method.
     *
     * @throws \Exception
     */
    public function handleWebhook()
    {
        $this->telegram->handle();

        return $this;
    }

    /**
     * Set any extra bot features that have been assigned on construction.
     */
    public function setBotExtras()
    {
        isset($this->admins)         && $this->telegram->enableAdmins((array)$this->admins);
        isset($this->mysql)          && $this->telegram->enableMySql($this->mysql);
        isset($this->botan_token)    && $this->telegram->enableBotan($this->botan_token);
        isset($this->commands_paths) && $this->telegram->addCommandsPaths((array)$this->commands_paths);
        isset($this->custom_input)   && $this->telegram->setCustomInput($this->custom_input);
        isset($this->download_path)  && $this->telegram->setDownloadPath($this->download_path);
        isset($this->upload_path)    && $this->telegram->setUploadPath($this->upload_path);

        if (isset($this->command_configs) && is_array($this->command_configs)) {
            foreach ($this->command_configs as $command => $config) {
                $this->telegram->setCommandConfig($command, $config);
            }
        }

        return $this;
    }
}
