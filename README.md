# PHP Telegram Bot Manager

[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/php-telegram-bot/telegram-bot-manager.svg)](https://scrutinizer-ci.com/g/php-telegram-bot/telegram-bot-manager/?branch=master)
[![Codecov](https://img.shields.io/codecov/c/github/php-telegram-bot/telegram-bot-manager.svg)](https://codecov.io/gh/php-telegram-bot/telegram-bot-manager)
[![Build Status](https://img.shields.io/travis/php-telegram-bot/telegram-bot-manager.svg)](https://travis-ci.org/php-telegram-bot/telegram-bot-manager)

[![Latest Stable Version](https://img.shields.io/packagist/v/noplanman/telegram-bot-manager.svg)](https://packagist.org/packages/noplanman/telegram-bot-manager)
[![Total Downloads](https://img.shields.io/packagist/dt/noplanman/telegram-bot-manager.svg)](https://packagist.org/packages/noplanman/telegram-bot-manager)
[![License](https://img.shields.io/packagist/l/noplanman/telegram-bot-manager.svg)](https://github.com/php-telegram-bot/telegram-bot-manager/LICENSE.md)

This project builds on top of [PHP Telegram Bot](https://github.com/php-telegram-bot/core) and as such, depends on it!

The main purpose of this mini-library is to make the interaction between your webserver and Telegram easier.
I strongly suggest your read the PHP Telegram Bot [instructions](https://github.com/php-telegram-bot/core#instructions) first, to understand what this library does exactly.

Installation and usage is pretty straight forward:

### Require this package with [Composer](https://getcomposer.org/)

Either run this command in your command line:

```bash
composer require noplanman/telegram-bot-manager:^0.43
```

**or**

For existing Composer projects, edit your project's `composer.json` file to require `noplanman/telegram-bot-manager`:

```yaml
"require": {
    "noplanman/telegram-bot-manager": "^0.43"
}
```
and then run `composer update`

**NOTE:** This will automatically also install PHP Telegram Bot into your project (if it isn't already).

### Performing actions

What use would this library be if you couldn't perform any actions?!

There are a few parameters available to get things rolling:

| Parameter | Description |
| --------- | ----------- |
| s         | **s**ecret: This is a special secret value defined in the main `manager.php` file. |
|           | This parameter is required to call the script via browser! |
| a         | **a**ction: The actual action to perform. (handle (default), cron, set, unset, reset) |
|           | **handle** executes the `getUpdates` method; **cron** executes cron commands; **set** / **unset** / **reset** the webhook. |
| l         | **l**oop: Number of seconds to loop the script for (used for getUpdates method). |
|           | This would be used mainly via CLI, to continually get updates for a certain period. |
| i         | **i**nterval: Number of seconds to wait between getUpdates requests (used for getUpdates method, default is 2). |
|           | This would be used mainly via CLI, to continually get updates for a certain period, every **i** seconds. |
| g         | **g**roup: Commands group for cron (only used together with `cron` action, default group is `default`). |
|           | Define which group of commands to execute via cron. |

#### via browser

Simply point your browser to the `manager.php` file with the necessary **GET** parameters:
- `http://example.com/manager.php?s=<secret>&a=<action>&l=<loop>&i=<interval>`

**Webhook**

Set, unset and reset the webhook:
- `http://example.com/manager.php?s=super_secret&a=set`
- `http://example.com/manager.php?s=super_secret&a=unset`
- `http://example.com/manager.php?s=super_secret&a=reset` (unset & set combined)

**getUpdates**

Handle updates once:
- `http://example.com/manager.php?s=super_secret&a=handle` or simply
- `http://example.com/manager.php?s=super_secret` (`handle` action is the default)

Handle updates for 30 seconds, fetching every 5 seconds:
- `http://example.com/manager.php?s=super_secret&l=30&i=5`

#### via CLI

When using CLI, the secret is not necessary (since it could just be read from the file itself).

Call the `manager.php` file directly using `php` and pass the parameters:
- `$ php manager.php a=<action> l=<loop> i=<interval>`

**Webhook**

Set, unset and reset the webhook:
- `$ php manager.php a=set`
- `$ php manager.php a=unset`
- `$ php manager.php a=reset` (unset & set combined)

**getUpdates**

Handle updates once:
- `$ php manager.php a=handle` or simply
- `$ php manager.php` (`handle` action is the default)

Handle updates for 30 seconds, fetching every 5 seconds:
- `$ php manager.php l=30 i=5`

### Create the manager PHP file

You can name this file whatever you like, it just has to be somewhere inside your PHP project (preferably in the root folder to make things easier).
(Let's assume our file is called `manager.php`)

Let's start off with a simple example that uses the webhook method:
```php
<?php

use NPM\TelegramBotManager\BotManager;

// Load composer.
require_once __DIR__ . '/vendor/autoload.php';

try {
    $bot = new BotManager([
        // Vitals!
        'api_key'      => '12345:my_api_key',
        'bot_username' => 'my_own_bot',
        'secret'       => 'super_secret',

        // Extras.
        'webhook'      => [
            'url' => 'https://example.com/manager.php',
        ]
    ]);
    $bot->run();
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}
```

### Set vital bot parameters

The vital parameters are:

```php
$bot = new BotManager([
    // (string) Bot API key provided by @BotFather.
    'api_key'      => '12345:my_api_key',
    // (string) Bot username that was defined when creating the bot.
    'bot_username' => 'my_own_bot',
    // (string) A secret password required to authorise access to the webhook.
    'secret'       => 'super_secret',

    ...
]);
```

The `secret` is a user-defined key that is required to execute any of the library's features.
Best make it long, random and very unique!

For 84 random characters:
- If you have `pwgen` installed, just execute `pwgen 84` and choose any one.
- Or just go [here](https://www.random.org/strings/?num=7&len=12&digits=on&upperalpha=on&loweralpha=on&unique=on&format=plain&rnd=new) and put all the output onto a single line.

(You get 2 guesses why 84 is a good number :wink:)

### Set extra bot parameters

Apart from the necessary vital parameters, the bot can be easily configured using extra parameters.

Enable admins? Add custom command paths? Set up logging?

**All no problem!**

Here is a complete list of all available extra parameters:

```php
$bot = new BotManager([
    ...

    // (array) All options that have to do with the webhook.
    'webhook'          => [
        // (string) URL to the manager PHP file used for setting up the webhook.
        'url'             => 'https://example.com/manager.php',
        // (string) Path to a self-signed certificate (if necessary).
        'certificate'     => __DIR__ . '/server.crt',
        // (int) Maximum allowed simultaneous HTTPS connections to the webhook.
        'max_connections' => 20,
        // (array) List the types of updates you want your bot to receive.
        'allowed_updates' => ['message', 'edited_channel_post', 'callback_query'],
    ],

    // (bool) Only allow webhook access from valid Telegram API IPs.
    'validate_request' => true,
    // (array) When using `validate_request`, also allow these IPs.
    'valid_ips'        => [
        '1.2.3.4',         // single
        '192.168.1.0/24',  // CIDR
        '10/8',            // CIDR (short)
        '5.6.*',           // wildcard
        '1.1.1.1-2.2.2.2', // range
    ],

    // (array) Paths where the log files should be put.
    'logging'          => [
        // (string) Log file for all incoming update requests.
        'update' => __DIR__ . '/php-telegram-bot-update.log',
        // (string) Log file for debug purposes.
        'debug'  => __DIR__ . '/php-telegram-bot-debug.log',
        // (string) Log file for all errors.
        'error'  => __DIR__ . '/php-telegram-bot-error.log',
    ],

    // (array) All options that have to do with the limiter.
    'limiter'          => [
        // (bool) Enable or disable the limiter functionality.
        'enabled' => true,
        // (array) Any extra options to pass to the limiter.
        'options' => [
            // (float) Interval between request handles.
            'interval' => 0.5,
        ],
    ],

    // (array) An array of user ids that have admin access to your bot (must be integers).
    'admins'           => [12345],

    // (array) Mysql credentials to connect a database (necessary for [`getUpdates`](#using-getupdates-method) method!).
    'mysql'            => [
        'host'     => '127.0.0.1',
        'user'     => 'root',
        'password' => 'root',
        'database' => 'telegram_bot',
    ],

    // (array) List of configurable paths.
    'paths'            => [
        // (string) Custom download path.
        'download' => __DIR__ . '/Download',
        // (string) Custom upload path.
        'upload'   => __DIR__ . '/Upload',
    ],

    // (array) All options that have to do with commands.
    'commands'         => [
        // (array) A list of custom commands paths.
        'paths'   => [
            __DIR__ . '/CustomCommands',
        ],
        // (array) A list of all custom command configs.
        'configs' => [
            'sendtochannel' => ['your_channel' => '@my_channel'],
            'weather'       => ['owm_api_key' => 'owm_api_key_12345'],
        ],
    ],

    // (array) All options that have to do with botan.
    'botan'            => [
        // (string) The Botan.io token to be used for analytics.
        'token'   => 'botan_12345',
        // (array) Any extra options to pass to botan.
        'options' => [
            // (float) Custom timeout for requests.
            'timeout' => 3,
        ],
    ],

    // (array) All options that have to do with cron.
    'cron'             => [
        // (array) List of groups that contain the commands to execute.
        'groups' => [
            // Each group has a name and array of commands.
            // When no group is defined, the default group gets executed.
            'default'     => [
                '/default_cron_command',
            ],
            'maintenance' => [
                '/db_cleanup',
                '/db_repair',
                '/log_rotate',
                '/message_admins Maintenance completed',
            ],
        ],
    ],

    // (string) Override the custom input of your bot (mostly for testing purposes!).
    'custom_input'     => '{"some":"raw", "json":"update"}',
]);
```

### Using getUpdates method

Using the `getUpdates` method requires a MySQL database connection:
```php
$bot = new BotManager([
    ...
    // Extras.
    'mysql'   => [
        'host'     => '127.0.0.1',
        'user'     => 'root',
        'password' => 'root',
        'database' => 'telegram_bot',
    ],
]);
```

Now, the updates can be done either through the [browser](#via-browser) or [via CLI](#via-cli).

## Development

When running live bot tests on a fork, you must enter the following environment variables to your [repository settings](https://docs.travis-ci.com/user/environment-variables#Defining-Variables-in-Repository-Settings) on travis-ci.org:
```
API_KEY="12345:your_api_key"
BOT_USERNAME="username_of_your_bot"
```
It probably makes sense for you to create a new dummy bot for this.
