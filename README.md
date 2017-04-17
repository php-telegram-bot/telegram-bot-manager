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

There are 3 parameters available to get things rolling:

| Parameter | Description |
| --------- | ----------- |
| s         | **s**ecret: This is a special secret value defined in the main `manager.php` file. |
|           | This parameter is required to call the script via browser! |
| a         | **a**ction: The actual action to perform. (handle (default), set, unset, reset) |
|           | **handle** executes the `getUpdates` method; **set** / **unset** / **reset** the Webhook. |
| l         | **l**oop: Number of seconds to loop the script for (used for getUpdates method). |
|           | This would be used mainly via CLI, to continually get updates for a certain period. |
| i         | **i**nterval: Number of seconds to wait between getUpdates requests (used for getUpdates method, default: 2). |
|           | This would be used mainly via CLI, to continually get updates for a certain period, every **i** seconds. |

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

Let's start off with a simple example that uses the Webhook method:
```php
<?php

use NPM\TelegramBotManager\BotManager;

// Load composer.
require __DIR__ . '/vendor/autoload.php';

try {
    $bot = new BotManager([
        // Vitals!
        'api_key'      => '12345:my_api_key',
        'bot_username' => 'my_own_bot',
        'secret'       => 'super_secret',

        // Extras.
        'webhook'      => 'https://example.com/manager.php',
    ]);
    $bot->run();
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}
```

### Set vital bot parameters

The vital parameters are:
- Bot API key
- Bot username
- A secret

What secret you ask? Well, this is a user-defined key that is required to execute any of the library features.
Best make it long, random and very unique!

For 84 random characters:
- If you have `pwgen` installed, just execute `pwgen 84` and choose any one.
- Or just go [here](https://www.random.org/strings/?num=7&len=12&digits=on&upperalpha=on&loweralpha=on&unique=on&format=plain&rnd=new) and put all the output onto a single line.

(You get 2 guesses why 84 is a good number :wink:)

### Set extra bot parameters

Apart from the necessary vital parameters, the bot can be easily configured using extra parameters.

Enable admins? Add custom command paths? Set up logging?

**All no problem!**

Here is a list of available extra parameters:

| Parameter            | Description |
| ---------            | ----------- |
| validate_request     | Only allow webhook access from valid Telegram API IPs. |
| *bool*               | *default is `true`* |
| webhook              | URL to the manager PHP file used for setting up the Webhook. |
| *string*             | *e.g.* `'https://example.com/manager.php'` |
| certificate          | Path to a self-signed certificate (if necessary). |
| *string*             | *e.g.* `__DIR__ . '/server.crt'` |
| max_connections      | Maximum allowed simultaneous HTTPS connections to the webhook. |
| *int*                | *e.g.* `20` |
| allowed_updates      | List the types of updates you want your bot to receive. |
| *array*              | *e.g.* `['message', 'edited_channel_post', 'callback_query']` |
| logging              | Path(s) where to the log files should be put. This is an array that can contain all 3 log file paths (`error`, `debug` and `update`). |
| *array*              | *e.g.* `['error' => __DIR__ . '/php-telegram-bot-error.log']` |
| limiter              | Enable or disable the limiter functionality, also accepts options array. |
| *bool|array*         | *e.g.* `true` or `false` or `['interval' => 2]` |
| admins               | An array of user ids that have admin access to your bot. |
| *array*              | *e.g.* `[12345]` |
| mysql                | Mysql credentials to connect a database (necessary for [`getUpdates`](#using-getupdates-method) method!). |
| *array*              | *e.g.* `['host' => '127.0.0.1', 'user' => 'root', 'password' => 'root', 'database' => 'telegram_bot']` |
| download_path        | Custom download path. |
| *string*             | *e.g.* `__DIR__ . '/Download'` |
| upload_path          | Custom upload path. |
| *string*             | *e.g.* `__DIR__ . '/Upload'` |
| commands_paths       | A list of custom commands paths. |
| *array*              | *e.g.* `[__DIR__ . '/CustomCommands']` |
| command_configs      | A list of all custom command configs. |
| *array*              | *e.g.* `['sendtochannel' => ['your_channel' => '@my_channel']` |
| botan_token          | The Botan.io token to be used for analytics. |
| *string*             | *e.g.* `'botan_12345'` |
| custom_input         | Override the custom input of your bot (mostly for testing purposes!). |
| *string*             | *e.g.* `'{"some":"raw", "json":"update"}'` |

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
