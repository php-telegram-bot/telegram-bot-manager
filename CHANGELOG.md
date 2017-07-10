# Changelog
The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

Exclamation symbols (:exclamation:) note something of importance e.g. breaking changes. Click them to learn more.

## [Unreleased]
### Added
### Changed
### Deprecated
### Removed
### Fixed
### Security

## [1.2.0] - 2017-07-10
### Added
- Custom output callback can be defined for getUpdates method.
### Changed
- Default output of getUpdates method now shows the message type or query text, not the text message content.
### Deprecated
### Fixed
- GetUpdates method would crash if a non-text message was sent.

## [1.1.0] - 2017-05-23
### Added
- `webhookinfo` action to get result from `getWebhookInfo`.
### Changed
- Clean up and refactor some methods.
### Fixed
- Passing an empty array to `webhook.allowed_updates` parameter now correctly resets to defaults.

## [1.0.1] - 2017-05-09
### Changed
- Use more stable `longman/ip-tools` for IP matching.

## [1.0.0] - 2017-05-08
### Changed
- [:exclamation:][1.0.0-bc-move] Move to `php-telegram-bot/telegram-bot-manager` on packagist.
- [:exclamation:][1.0.0-bc-move] Move to `TelegramBot\TelegramBotManager` namespace.

## [0.44.0] - 2017-05-05
### Added
- Ability to define custom valid IPs to access webhook.
- Execute commands via cron, using `cron` action and `g` parameter.
### Changed
- [:exclamation:][0.44.0-bc-parameter-structure] Remodelled the parameter array to a more flexible structure.
- `bot_username` and `secret` are no longer vital parameters.
### Fixed
- Initialise loggers before anything else, to allow logging of all errors.
### Security
- Enforce non-empty secret when using webhook.

## [0.43.0] - 2017-04-17
### Added
- PHP CodeSniffer introduced and cleaned code to pass tests.
- Custom exceptions for better error handling.
- Request limiter options.

## [0.42.0.1] - 2017-04-11
### Added
- Changelog.
### Changed
- (!) Rename vital parameter `botname` to `bot_username` everywhere.
### Fixed
- Some code style issues.

## [0.42.0] - 2017-04-10
### Changed
- Move to PHP Telegram Bot organisation.
- Mirror version with core library.
- Update repository links.
### Fixed
- Readme formatting.

## [0.4.0] - 2017-02-26
### Added
- Latest Telegram Bot limiter functionality.
### Fixed
- Travis tests, using MariaDB instead of MySQL.

## [0.3.1] - 2017-01-04
### Fixed
- Make CLI usable again after setting up Telegram API IP address limitations.

## [0.3.0] - 2016-12-25
### Added
- Latest changes from PHP Telegram API bot.
### Security
- Request validation to secure the script to allow only Telegram API IPs of executing the webhook handle.

## [0.2.1] - 2016-10-16
### Added
- Interval between updates can be set via parameter.

## [0.2] - 2016-09-16
### Changed
- Force PHP7.

## [0.1.1] - 2016-08-20
### Fixed
- Tiny conditional fix to correct the output.

## [0.1] - 2016-08-20
### Added
- First minor version that contains the basic functionality.

[1.0.0-bc-move]: https://github.com/php-telegram-bot/telegram-bot-manager/wiki/Breaking-backwards-compatibility#namespace-and-package-name-changed "Namespace and package name changed"
[0.44.0-bc-parameter-structure]: https://github.com/php-telegram-bot/telegram-bot-manager/wiki/Breaking-backwards-compatibility#parameter-structure-changed "Parameter structure changed"
