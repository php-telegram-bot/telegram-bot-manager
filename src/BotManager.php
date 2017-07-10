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

use Longman\IPTools\Ip;
use Longman\TelegramBot\Entities;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\TelegramLog;
use TelegramBot\TelegramBotManager\Exception\InvalidAccessException;
use TelegramBot\TelegramBotManager\Exception\InvalidWebhookException;

class BotManager
{
    /**
     * @var string Telegram post servers IP range
     */
    const TELEGRAM_IP_RANGE = '149.154.167.197-149.154.167.233';

    /**
     * @var string The output for testing, instead of echoing
     */
    private $output = '';

    /**
     * @var \Longman\TelegramBot\Telegram
     */
    private $telegram;

    /**
     * @var \TelegramBot\TelegramBotManager\Params Object that manages the parameters.
     */
    private $params;

    /**
     * @var \TelegramBot\TelegramBotManager\Action Object that contains the current action.
     */
    private $action;

    /**
     * @var callable
     */
    private $custom_get_updates_callback;

    /**
     * BotManager constructor.
     *
     * @param array $params
     *
     * @throws \TelegramBot\TelegramBotManager\Exception\InvalidParamsException
     * @throws \TelegramBot\TelegramBotManager\Exception\InvalidActionException
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function __construct(array $params)
    {
        // Initialise logging before anything else, to allow errors to be logged.
        $this->initLogging($params['logging'] ?? []);

        $this->params = new Params($params);
        $this->action = new Action($this->params->getScriptParam('a'));

        // Set up a new Telegram instance.
        $this->telegram = new Telegram(
            $this->params->getBotParam('api_key'),
            $this->params->getBotParam('bot_username')
        );
    }

    /**
     * Check if we're busy running the PHPUnit tests.
     *
     * @return bool
     */
    public static function inTest(): bool
    {
        return defined('PHPUNIT_TEST') && PHPUNIT_TEST === true;
    }

    /**
     * Return the Telegram object.
     *
     * @return \Longman\TelegramBot\Telegram
     */
    public function getTelegram(): Telegram
    {
        return $this->telegram;
    }

    /**
     * Get the Params object.
     *
     * @return \TelegramBot\TelegramBotManager\Params
     */
    public function getParams(): Params
    {
        return $this->params;
    }

    /**
     * Get the Action object.
     *
     * @return \TelegramBot\TelegramBotManager\Action
     */
    public function getAction(): Action
    {
        return $this->action;
    }

    /**
     * Run this thing in all its glory!
     *
     * @return \TelegramBot\TelegramBotManager\BotManager
     * @throws \Longman\TelegramBot\Exception\TelegramException
     * @throws \TelegramBot\TelegramBotManager\Exception\InvalidAccessException
     * @throws \TelegramBot\TelegramBotManager\Exception\InvalidWebhookException
     * @throws \Exception
     */
    public function run(): self
    {
        // Make sure this is a valid call.
        $this->validateSecret();
        $this->validateRequest();

        if ($this->action->isAction('webhookinfo')) {
            $webhookinfo = Request::getWebhookInfo();
            print_r($webhookinfo->getResult() ?: $webhookinfo->printError());
            return $this;
        }
        if ($this->action->isAction(['set', 'unset', 'reset'])) {
            return $this->validateAndSetWebhook();
        }

        $this->setBotExtras();

        if ($this->action->isAction('handle')) {
            $this->handleRequest();
        } elseif ($this->action->isAction('cron')) {
            $this->handleCron();
        }

        return $this;
    }

    /**
     * Initialise all loggers.
     *
     * @param array $log_paths
     *
     * @return \TelegramBot\TelegramBotManager\BotManager
     * @throws \Exception
     */
    public function initLogging(array $log_paths): self
    {
        foreach ($log_paths as $logger => $logfile) {
            ('debug' === $logger) && TelegramLog::initDebugLog($logfile);
            ('error' === $logger) && TelegramLog::initErrorLog($logfile);
            ('update' === $logger) && TelegramLog::initUpdateLog($logfile);
        }

        return $this;
    }

    /**
     * Make sure the passed secret is valid.
     *
     * @param bool $force Force validation, even on CLI.
     *
     * @return \TelegramBot\TelegramBotManager\BotManager
     * @throws \TelegramBot\TelegramBotManager\Exception\InvalidAccessException
     */
    public function validateSecret(bool $force = false): self
    {
        // If we're running from CLI, secret isn't necessary.
        if ($force || 'cli' !== PHP_SAPI) {
            $secret     = $this->params->getBotParam('secret');
            $secret_get = $this->params->getScriptParam('s');
            if (!isset($secret, $secret_get) || $secret !== $secret_get) {
                throw new InvalidAccessException('Invalid access');
            }
        }

        return $this;
    }

    /**
     * Make sure the webhook is valid and perform the requested webhook operation.
     *
     * @return \TelegramBot\TelegramBotManager\BotManager
     * @throws \Longman\TelegramBot\Exception\TelegramException
     * @throws \TelegramBot\TelegramBotManager\Exception\InvalidWebhookException
     */
    public function validateAndSetWebhook(): self
    {
        $webhook = $this->params->getBotParam('webhook');
        if (empty($webhook['url'] ?? null) && $this->action->isAction(['set', 'reset'])) {
            throw new InvalidWebhookException('Invalid webhook');
        }

        if ($this->action->isAction(['unset', 'reset'])) {
            $this->handleOutput($this->telegram->deleteWebhook()->getDescription() . PHP_EOL);
            // When resetting the webhook, sleep for a bit to prevent too many requests.
            $this->action->isAction('reset') && sleep(1);
        }

        if ($this->action->isAction(['set', 'reset'])) {
            $webhook_params = array_filter([
                'certificate'     => $webhook['certificate'] ?? null,
                'max_connections' => $webhook['max_connections'] ?? null,
                'allowed_updates' => $webhook['allowed_updates'] ?? null,
            ], function ($v, $k) {
                if ($k === 'allowed_updates') {
                    // Special case for allowed_updates, which can be an empty array.
                    return is_array($v);
                }
                return !empty($v);
            }, ARRAY_FILTER_USE_BOTH);

            $this->handleOutput(
                $this->telegram->setWebhook(
                    $webhook['url'] . '?a=handle&s=' . $this->params->getBotParam('secret'),
                    $webhook_params
                )->getDescription() . PHP_EOL
            );
        }

        return $this;
    }

    /**
     * Save the test output and echo it if we're not in a test.
     *
     * @param string $output
     *
     * @return \TelegramBot\TelegramBotManager\BotManager
     */
    private function handleOutput(string $output): self
    {
        $this->output .= $output;

        if (!self::inTest()) {
            echo $output;
        }

        return $this;
    }

    /**
     * Set any extra bot features that have been assigned on construction.
     *
     * @return \TelegramBot\TelegramBotManager\BotManager
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function setBotExtras(): self
    {
        $this->setBotExtrasTelegram();
        $this->setBotExtrasRequest();

        return $this;
    }

    /**
     * Set extra bot parameters for Telegram object.
     *
     * @return \TelegramBot\TelegramBotManager\BotManager
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    protected function setBotExtrasTelegram(): self
    {
        $simple_extras = [
            'admins'         => 'enableAdmins',
            'mysql'          => 'enableMySql',
            'commands.paths' => 'addCommandsPaths',
            'custom_input'   => 'setCustomInput',
            'paths.download' => 'setDownloadPath',
            'paths.upload'   => 'setUploadPath',
        ];
        // For simple telegram extras, just pass the single param value to the Telegram method.
        foreach ($simple_extras as $param_key => $method) {
            $param = $this->params->getBotParam($param_key);
            if (null !== $param) {
                $this->telegram->$method($param);
            }
        }

        // Custom command configs.
        $command_configs = $this->params->getBotParam('commands.configs', []);
        foreach ($command_configs as $command => $config) {
            $this->telegram->setCommandConfig($command, $config);
        }

        // Botan with options.
        if ($botan_token = $this->params->getBotParam('botan.token')) {
            $botan_options = $this->params->getBotParam('botan.options', []);
            $this->telegram->enableBotan($botan_token, $botan_options);
        }

        return $this;
    }

    /**
     * Set extra bot parameters for Request class.
     *
     * @return \TelegramBot\TelegramBotManager\BotManager
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    protected function setBotExtrasRequest(): self
    {
        $request_extras = [
            // None at the moment...
        ];
        // For request extras, just pass the single param value to the Request method.
        foreach ($request_extras as $param_key => $method) {
            $param = $this->params->getBotParam($param_key);
            if (null !== $param) {
                Request::$method($param);
            }
        }

        // Special cases.
        $limiter_enabled = $this->params->getBotParam('limiter.enabled');
        if ($limiter_enabled !== null) {
            $limiter_options = $this->params->getBotParam('limiter.options', []);
            Request::setLimiter($limiter_enabled, $limiter_options);
        }

        return $this;
    }

    /**
     * Handle the request, which calls either the Webhook or getUpdates method respectively.
     *
     * @return \TelegramBot\TelegramBotManager\BotManager
     * @throws \TelegramBot\TelegramBotManager\Exception\InvalidAccessException
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function handleRequest(): self
    {
        if ($this->params->getBotParam('webhook.url')) {
            return $this->handleWebhook();
        }

        if ($loop_time = $this->getLoopTime()) {
            return $this->handleGetUpdatesLoop($loop_time, $this->getLoopInterval());
        }

        return $this->handleGetUpdates();
    }

    /**
     * Handle cron.
     *
     * @return \TelegramBot\TelegramBotManager\BotManager
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function handleCron(): self
    {
        $groups = explode(',', $this->params->getScriptParam('g', 'default'));

        $commands = [];
        foreach ($groups as $group) {
            $commands[] = $this->params->getBotParam('cron.groups.' . $group, []);
        }
        $this->telegram->runCommands(array_merge(...$commands));

        return $this;
    }

    /**
     * Get the number of seconds the script should loop.
     *
     * @return int
     */
    public function getLoopTime(): int
    {
        $loop_time = $this->params->getScriptParam('l');

        if (null === $loop_time) {
            return 0;
        }

        if (is_string($loop_time) && '' === trim($loop_time)) {
            return 604800; // Default to 7 days.
        }

        return max(0, (int) $loop_time);
    }

    /**
     * Get the number of seconds the script should wait after each getUpdates request.
     *
     * @return int
     */
    public function getLoopInterval(): int
    {
        $interval_time = $this->params->getScriptParam('i');

        if (null === $interval_time || (is_string($interval_time) && '' === trim($interval_time))) {
            return 2;
        }

        // Minimum interval is 1 second.
        return max(1, (int) $interval_time);
    }

    /**
     * Loop the getUpdates method for the passed amount of seconds.
     *
     * @param int $loop_time_in_seconds
     * @param int $loop_interval_in_seconds
     *
     * @return \TelegramBot\TelegramBotManager\BotManager
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function handleGetUpdatesLoop(int $loop_time_in_seconds, int $loop_interval_in_seconds = 2): self
    {
        // Remember the time we started this loop.
        $now = time();

        $this->handleOutput('Looping getUpdates until ' . date('Y-m-d H:i:s', $now + $loop_time_in_seconds) . PHP_EOL);

        while ($now > time() - $loop_time_in_seconds) {
            $this->handleGetUpdates();

            // Chill a bit.
            sleep($loop_interval_in_seconds);
        }

        return $this;
    }

    /**
     * Set a custom callback for handling the output of the getUpdates results.
     *
     * @param callable $callback
     *
     * @return \TelegramBot\TelegramBotManager\BotManager
     */
    public function setCustomGetUpdatesCallback(callable $callback): BotManager
    {
        $this->custom_get_updates_callback = $callback;
        return $this;
    }

    /**
     * Handle the updates using the getUpdates method.
     *
     * @return \TelegramBot\TelegramBotManager\BotManager
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function handleGetUpdates(): self
    {
        $get_updates_response = $this->telegram->handleGetUpdates();

        // Check if the user has set a custom callback for handling the response.
        if ($this->custom_get_updates_callback !== null) {
            $this->handleOutput(call_user_func($this->custom_get_updates_callback, $get_updates_response));
        } else {
            $this->handleOutput($this->defaultGetUpdatesCallback($get_updates_response));
        }

        return $this;
    }

    /**
     * Return the default output for getUpdates handling.
     *
     * @param Entities\ServerResponse $get_updates_response
     *
     * @return string
     */
    protected function defaultGetUpdatesCallback($get_updates_response): string
    {
        /** @var Entities\Update[] $results */
        $results = array_filter((array) $get_updates_response->getResult());

        $output = sprintf(
            '%s - Updates processed: %d' . PHP_EOL,
            date('Y-m-d H:i:s'),
            count($results)
        );

        foreach ($results as $result) {
            $chat_id = 0;
            $text    = '<n/a>';

            $update_content = $result->getUpdateContent();
            if ($update_content instanceof Entities\Message) {
                $chat_id = $update_content->getFrom()->getId();
                $text    = sprintf('<%s>', $update_content->getType());
            } elseif ($update_content instanceof Entities\InlineQuery ||
                      $update_content instanceof Entities\ChosenInlineResult
            ) {
                $chat_id = $update_content->getFrom()->getId();
                $text    = sprintf('<query> %s', $update_content->getQuery());
            }

            $output .= sprintf(
                '%d: %s' . PHP_EOL,
                $chat_id,
                preg_replace('/\s+/', ' ', trim($text))
            );
        }

        return $output;
    }

    /**
     * Handle the updates using the Webhook method.
     *
     * @return \TelegramBot\TelegramBotManager\BotManager
     * @throws \Longman\TelegramBot\Exception\TelegramException
     * @throws \TelegramBot\TelegramBotManager\Exception\InvalidAccessException
     */
    public function handleWebhook(): self
    {
        $this->telegram->handle();

        return $this;
    }

    /**
     * Return the current test output and clear it.
     *
     * @return string
     */
    public function getOutput(): string
    {
        $output       = $this->output;
        $this->output = '';

        return $output;
    }

    /**
     * Check if this is a valid request coming from a Telegram API IP address.
     *
     * @link https://core.telegram.org/bots/webhooks#the-short-version
     *
     * @return bool
     */
    public function isValidRequest(): bool
    {
        // If we're running from CLI, requests are always valid, unless we're running the tests.
        if ((!self::inTest() && 'cli' === PHP_SAPI) || false === $this->params->getBotParam('validate_request')) {
            return true;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR'] as $key) {
            if (filter_var($_SERVER[$key] ?? null, FILTER_VALIDATE_IP)) {
                $ip = $_SERVER[$key];
                break;
            }
        }

        return Ip::match($ip, array_merge(
            [self::TELEGRAM_IP_RANGE],
            (array) $this->params->getBotParam('valid_ips', [])
        ));
    }

    /**
     * Make sure this is a valid request.
     *
     * @throws \TelegramBot\TelegramBotManager\Exception\InvalidAccessException
     */
    private function validateRequest()
    {
        if (!$this->isValidRequest()) {
            throw new InvalidAccessException('Invalid access');
        }
    }
}
