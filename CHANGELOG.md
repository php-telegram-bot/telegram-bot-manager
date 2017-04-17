# Changelog
The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

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
