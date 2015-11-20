<?php

/**
 * Roundcube Server Utilities collection
 *
 * @author Thomas Bruederli <thomas@roundcube.net>
 *
 * Copyright (C) 2015, Roundcube Dev Team
 *
 * Licensed under the GNU General Public License version 3 or
 * any later version as published by the Free Software Foundation.
 * For full license information see http://www.gnu.org/licenses/gpl-3.0
 */

namespace Roundcube;

/**
 * Static calss providing utility functions for the Free/Busy service
 */
class Utils
{
    /**
     * Resolve the given directory to a real path ending with $append
     *
     * @param string Arbitrary directory directory path
     * @param string Make path end with this string/character
     * @return string Absolute file system path
     */
    public static function abspath($dirname, $append = '')
    {
        if ($dirname[0] != '/')
            $dirname = realpath(ROUNDCUBE_INSTALL_ROOT . '/' . $dirname);

        return rtrim($dirname, '/') . $append;
    }

    /**
     * Resolve relative URL into a fully qualified URI
     *
     * @param string $url Relative URL
     *
     * @return string Fully qualified URL
     */
    public static function fullyQualifiedUrl($url)
    {
        // prepend protocol://hostname:port
        if (!preg_match('|^https?://|', $url)) {
            $schema       = 'http';
            $default_port = 80;

            if (self::isHTTPS()) {
                $schema       = 'https';
                $default_port = 443;
            }

            $prefix = $schema . '://' . preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
            if ($_SERVER['SERVER_PORT'] != $default_port) {
                $prefix .= ':' . $_SERVER['SERVER_PORT'];
            }

            $url = $prefix . ($url[0] == '/' ? '' : '/') . $url;
        }

        return $url;
    }

    /**
     * Check if working in SSL mode
     *
     * @param integer $port      HTTPS port number
     * @param boolean $use_https Enables 'use_https' option checking
     *
     * @return boolean
     */
    public static function isHTTPS($port=null, $use_https=true)
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https'
         /* && in_array($_SERVER['REMOTE_ADDR'], $config->get('proxy_whitelist', array())) */
        ) {
            return true;
        }
        if ($port && $_SERVER['SERVER_PORT'] == $port) {
            return true;
        }

        return false;
    }
}