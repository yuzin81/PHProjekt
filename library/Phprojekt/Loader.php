<?php
/**
 * Our own class loader.
 *
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License version 2.1 as published by the Free Software Foundation
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * @copyright  Copyright (c) 2008 Mayflower GmbH (http://www.mayflower.de)
 * @package    PHProjekt
 * @subpackage Core
 * @license    LGPL 2.1 (See LICENSE file)
 * @version    $Id$
 * @link       http://www.phprojekt.com
 * @author     David Soria Parra <soria_parra@mayflower.de>
 * @since      File available since Release 6.0
 */

/**
 * An own class loader that reads the class files from the
 * /application directory or from the Zend library directory depending
 * on the name of the class.
 *
 * @copyright  Copyright (c) 2008 Mayflower GmbH (http://www.mayflower.de)
 * @package    PHProjekt
 * @subpackage Core
 * @license    LGPL 2.1 (See LICENSE file)
 * @version    Release: @package_version@
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 * @author     David Soria Parra <soria_parra@mayflower.de>
 */
class Phprojekt_Loader extends Zend_Loader
{
    /**
     * Identifier for views
     * It's normaly only needed by the internals
     *
     * @see _getClass
     */
    const VIEW = 'Views';

    /**
     * Identifier for models
     * It's normaly only needed by the internals
     *
     * @see _getClass
     */
    const MODEL = 'Models';

    /**
     * Directories
     *
     * @var array
     */
    protected static $_directories = array(PHPR_CORE_PATH, PHPR_LIBRARY_PATH);

    /**
     * Define the set of allowed characters for classes.
     *
     */
    const CLASS_PATTERN = '[A-Za-z0-9_]+';

    /**
     * Load a class
     *
     * @param string       $class Name of the class
     * @param string|array $dirs  Directories to search
     *
     * @return void
     */
    public static function loadClass($class, $dirs = null)
    {
        if (preg_match("@Controller$@", $class)) {
            $names  = explode('_', $class);
            $front  = Zend_Controller_Front::getInstance();
            $module = (count($names) > 1) ? $names[0] : $front->getDefaultModule();

            $file = PHPR_CORE_PATH . DIRECTORY_SEPARATOR
                  . $module . DIRECTORY_SEPARATOR
                  . $front->getModuleControllerDirectoryName()
                  . DIRECTORY_SEPARATOR
                  . array_pop($names) . '.php';

            if (self::isReadable($file)) {
                self::_includeFile($file, true);
            }
        }

        if (!class_exists($class, false)) {
            if (null === $dirs) {
                $dirs = self::$_directories;
            }
            parent::loadClass($class, $dirs);
        }
    }

    /**
     * The autoload method used to load classes on demand
     * Returns either the name of the class or false, if
     * loading failed.
     *
     * @param string $class The name of the class
     *
     * @return string|false Class name on success; false on failure
     */
    public static function autoload($class)
    {
        try {
            self::loadClass($class, self::$_directories);
            return $class;
        } catch (Exception $error) {
            $error->getMessage();
            return false;
        }

        return false;
    }

    /**
     * Instantiate a given class name. We asume that it's allready loaded.
     *
     * @param string $name Name of the class
     * @param array  $args Argument list
     *
     * @return object
     */
    protected static function _newInstance($name, $args)
    {
        /*
         * We have to use the reflection here, as expanding arguments
         * to an array is not possible without reflection.
         */
        $class = new ReflectionClass($name);
        if (null !== $class->getConstructor()) {
            return $class->newInstanceArgs($args);
        } else {
            return $class->newInstance();
        }
    }

    /**
     * Finds a class. If a customized class is available in the Customized/
     * directory, it's loaded and the name is returned, instead of the
     * normal class.
     *
     * @param string $module Name of the module
     * @param string $item   Name of the class to be loaded
     * @param string $ident  Ident, might be 'Models', 'Controllers' or 'Views'
     *
     * @throws Zend_Exception If class not found
     *
     * @return string
     */
    protected static function _getClass($module, $item, $ident)
    {
        $nIdentifier = sprintf("%s_%s_%s", $module, $ident, $item);
        $cIdentifier = sprintf("%s_%s_Customized_%s", $module, $ident, $item);

        if (class_exists($cIdentifier, false)) {
            return $cIdentifier;
        } else {
            return $nIdentifier;
        }
    }

    /**
     * Load the class of a model and return the name of the class.
     * Always use the returned name to instantiate a class, a customized
     * class name might be loaded and returned by this method
     *
     * @param string $module Name of the module
     * @param string $model  Name of the class to be loaded
     *
     * @see _getClass
     *
     * @throws Zend_Exception If class not found
     *
     * @return string
     */
    public static function getModelClassname($module, $model)
    {
        return self::_getClass($module, $model, self::MODEL);
    }

    /**
     * Load the class of a model and return an instance of the class.
     * Always use the returned name to instantiate a class, a customized
     * class name might be loaded and returned by this method
     *
     * @param string $module Name of the module
     * @param string $view   Name of the class to be loaded
     *
     * @see _getClass
     *
     * @throws Zend_Exception If class not found
     *
     * @return string
     */
    public static function getViewClassname($module, $view)
    {
        return self::_getClass($module, $view, self::VIEW);
    }

    /**
     * Load the class of a model and return an new instance of the class.
     * Always use the returned name to instantiate a class, a customized
     * class name might be loaded and returned by this method.
     * This method can take more than the two arguments. Every other argument
     * is passed to the constructor.
     *
     * The class is temporally cached in the Registry for the next calls
     * Only is cached if don't have any arguments
     *
     * Be sure that the class have the correct "__clone" function defined
     * if it have some internal variables with other classes for prevent
     * the same point to the object.
     *
     * @param string $module Name of the module
     * @param string $model  Name of the model
     *
     * @return Object
     */
    public static function getModel($module, $model)
    {
        $name = self::getModelClassname($module, $model);
        $args = array_slice(func_get_args(), 2);

        if (empty($args) && Phprojekt::getInstance()->getConfig()->useCacheForClasses) {
            $registryName = 'getModel_'.$module.'_'.$model;
            if (!Zend_Registry::isRegistered($registryName)) {
                $object = self::_newInstance($name, $args);
                Zend_Registry::set($registryName, $object);
            } else {
                $object = clone(Zend_Registry::get($registryName));
            }
        } else {
            $object = self::_newInstance($name, $args);
        }

        return $object;
    }

    /**
     * Load a class from the library and return an new instance of the class.
     * This method can take more than the two arguments. Every other argument
     * is passed to the constructor.
     *
     * The class is temporally cached in the Registry for the next calls
     * Only is cached if don't have any arguments
     *
     * Be sure that the class have the correct "__clone" function defined
     * if it have some internal variables with other classes for prevent
     * the same point to the object.
     *
     * @param string $name Name of the class
     *
     * @return Object
     */
    public static function getLibraryClass($name)
    {
        $args = array_slice(func_get_args(), 2);

        if (empty($args) && Phprojekt::getInstance()->getConfig()->useCacheForClasses) {
            $registryName = 'getLibraryClass_'.$name;
            if (!Zend_Registry::isRegistered($registryName)) {
                $object = self::_newInstance($name, $args);
                Zend_Registry::set($registryName, $object);
            } else {
                $object = clone(Zend_Registry::get($registryName));
            }
        } else {
            $object = self::_newInstance($name, $args);
        }

        return $object;
    }

    /**
     * Returns the name of the model for a given object
     *
     * @param Phprojekt_Model_Interface $object An active record
     *
     * @return string|boolean
     */
    public static function getModelFromObject(Phprojekt_Model_Interface $object)
    {
        $match = null;
        $pattern = str_replace('_', '', self::CLASS_PATTERN);
        if (preg_match("@_(" . $pattern . ")$@", get_class($object), $match)) {
            return $match[1];
        }

        return false;
    }

    /**
     * Returns the name of the modul for a given object
     *
     * @param Phprojekt_ActiveRecord_Abstract $object An active record
     *
     * @return string|boolean
     */
    public static function getModuleFromObject(Phprojekt_Model_Interface $object)
    {
        $match = null;
        $pattern = str_replace('_', '', self::CLASS_PATTERN);
        if (preg_match("@^(" . $pattern . ")_@", get_class($object), $match)) {
            return $match[1];
        }

        return false;
    }

    /**
     * Load the class of a view and return an new instance of the class.
     * Always use the returned name to instantiate a class, a customized
     * class name might be loaded and returned by this method
     *
     * @param string $module Name of the module
     * @param string $view   Name of the view
     *
     * @return Object
     */
    public static function getView($module, $view)
    {
        $name = self::getViewClassname($module, $view);
        $args = array_slice(func_get_args(), 2);

        return self::_newInstance($name, $args);
    }

    /**
     * Try to include a file by the class name
     *
     * @param strung $class The name of the class
     *
     * @return boolean
     */
    public function tryToLoadClass($class)
    {
        $names  = explode('_', $class);
        $file   = PHPR_CORE_PATH;
        foreach ($names as $name) {
            $file .= DIRECTORY_SEPARATOR . $name;
        }
        $file .= '.php';
        if (file_exists($file)) {
            self::_includeFile($file, true);
            return true;
        } else {
            return false;
        }
    }
}