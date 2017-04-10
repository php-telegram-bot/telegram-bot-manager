<?php declare(strict_types=1);
/**
 * This file is part of the TelegramBotManager package.
 *
 * (c) Armando Lüscher <armando@noplanman.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Set error reporting to the max level.
error_reporting(-1);

// Set UTC timezone.
date_default_timezone_set('UTC');

// Include the Composer autoloader.
require_once __DIR__ . '/../vendor/autoload.php';
