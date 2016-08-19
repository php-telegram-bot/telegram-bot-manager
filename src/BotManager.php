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

use Longman\TelegramBot\Entities;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\TelegramLog;

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
     * @var Params Object that manages the parameters.
     */
    private $params;

    /**
     * @var Action Object that contains the current action.
     */
    private $action;

    /**
     * BotManager constructor.
     *
     * @param array $params
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $params)
    {
        $this->params = new Params($params);
        $this->action = new Action($this->params->getScriptParam('a'));
    }

    public function getParams()
    {
        return $this->params;
    }

    public function getAction()
    {
        return $this->action;
    }

    /**
     * Run this thing in all its glory!
     *
     * @throws \InvalidArgumentException
     */
    public function run()
    {
        // Initialise logging.
        $this->initLogging();

        // Make sure this is a valid call.
        $this->validateSecret();

        // Set up a new Telegram instance.
        $this->telegram = new Telegram(
            $this->params->getBotParam('api_key'),
            $this->params->getBotParam('botname')
        );

        if ($this->action->isAction(['set', 'unset', 'reset'])) {
            $this->validateAndSetWebhook();
        } elseif ($this->action->isAction('handle')) {
            // Set any extras.
            $this->setBotExtras();
            $this->handleRequest();
        }

        return $this;
    }

    /**
     * Initialise all loggers.
     */
    public function initLogging()
    {
        $logging = $this->params->getBotParam('logging');
        if (is_array($logging)) {
            /** @var array $logging */
            foreach ($logging as $logger => $logfile) {
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
     * @throws \InvalidArgumentException
     */
    public function validateSecret($force = false)
    {
        // If we're running from CLI, secret isn't necessary.
        if ($force || 'cli' !== PHP_SAPI) {
            $secret    = $this->params->getBotParam('secret');
            $secretGet = $this->params->getScriptParam('s');
            if ($secretGet !== $secret) {
                throw new \InvalidArgumentException('Invalid access');
            }
        }

        return $this;
    }

    /**
     * Make sure the webhook is valid and perform the requested webhook operation.
     *
     * @throws \InvalidArgumentException
     */
    public function validateAndSetWebhook()
    {
        $webhook = $this->params->getBotParam('webhook');
        $selfcrt = $this->params->getBotParam('selfcrt');
        if (empty($webhook) && $this->action->isAction(['set', 'reset'])) {
            throw new \InvalidArgumentException('Invalid webhook');
        }

        if ($this->action->isAction(['unset', 'reset'])) {
            $this->test_output = $this->telegram->unsetWebHook()->getDescription();
        }
        if ($this->action->isAction(['set', 'reset'])) {
            $this->test_output = $this->telegram->setWebHook(
                $webhook . '?a=handle&s=' . $this->params->getBotParam('secret'),
                $selfcrt
            )->getDescription();
        }

        (@constant('PHPUNIT_TEST') !== true) && print($this->test_output . PHP_EOL);

        return $this;
    }

    /**
     * Set any extra bot features that have been assigned on construction.
     *
     * @return $this
     */
    public function setBotExtras()
    {
        ($v = $this->params->getBotParam('admins')) && $this->telegram->enableAdmins($v);
        ($v = $this->params->getBotParam('mysql')) && $this->telegram->enableMySql($v);
        ($v = $this->params->getBotParam('botan_token')) && $this->telegram->enableBotan($v);
        ($v = $this->params->getBotParam('commands_paths')) && $this->telegram->addCommandsPaths($v);
        ($v = $this->params->getBotParam('custom_input')) && $this->telegram->setCustomInput($v);
        ($v = $this->params->getBotParam('download_path')) && $this->telegram->setDownloadPath($v);
        ($v = $this->params->getBotParam('upload_path')) && $this->telegram->setUploadPath($v);

        $command_configs = $this->params->getBotParam('command_configs');
        if (is_array($command_configs)) {
            /** @var array $command_configs */
            foreach ($command_configs as $command => $config) {
                $this->telegram->setCommandConfig($command, $config);
            }
        }

        return $this;
    }

    /**
     * Handle the request, which calls either the Webhook or getUpdates method respectively.
     *
     * @throws \InvalidArgumentException
     */
    public function handleRequest()
    {
        if (empty($this->params->getBotParam('webhook'))) {
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
     * Get the number of seconds the script should loop.
     *
     * @return int
     */
    public function getLoopTime()
    {
        $loop_time = $this->params->getScriptParam('l');

        if (null === $loop_time) {
            return 0;
        }

        if ('' === trim($loop_time)) {
            return 604800; // Default to 7 days.
        }

        return max(0, (int)$loop_time);
    }

    /**
     * Loop the getUpdates method for the passed amount of seconds.
     *
     * @param int $loop_time_in_seconds
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
     * @throws \InvalidArgumentException
     */
    public function handleWebhook()
    {
        $this->telegram->handle();

        return $this;
    }
}
