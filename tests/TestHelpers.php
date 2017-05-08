<?php declare(strict_types=1);
/**
 * This file is part of the TelegramBotManager package.
 *
 * (c) Armando Lüscher <armando@noplanman.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TelegramBot\TelegramBotManager\Tests;

class TestHelpers
{
    /**
     * Set the value of a private/protected property of an object.
     *
     * @param object $object   Object that contains the property.
     * @param string $property Name of the property who's value we want to set.
     * @param mixed  $value    The value to set to the property.
     */
    public static function setObjectProperty($object, $property, $value)
    {
        $ref_object   = new \ReflectionObject($object);
        $ref_property = $ref_object->getProperty($property);
        $ref_property->setAccessible(true);
        $ref_property->setValue($object, $value);
    }

    /**
     * Set the value of a private/protected static property of a class.
     *
     * @param string $class    Class that contains the static property.
     * @param string $property Name of the property who's value we want to set.
     * @param mixed  $value    The value to set to the property.
     */
    public static function setStaticProperty($class, $property, $value)
    {
        $ref_property = new \ReflectionProperty($class, $property);
        $ref_property->setAccessible(true);
        $ref_property->setValue(null, $value);
    }

    /**
     * Call a private/protected method with certain arguments.
     *
     * @param object $object Object to call method on.
     * @param string $method Name of the method to call.
     * @param array  $args   Parameters to pass to method.
     */
    public static function callObjectMethod($object, $method, array $args = [])
    {
        $ref_object = new \ReflectionObject($object);
        $ref_method = $ref_object->getMethod($method);
        $ref_method->setAccessible(true);
        $ref_method->invokeArgs($object, $args);
    }
}
