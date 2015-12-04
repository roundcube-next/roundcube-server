<?php

/**
 * Extended Monolog LineFormatter for human-readable stream logging
 *
 * @author Thomas Bruederli <thomas@roundcube.net>
 *
 * Copyright (C) 2015, Roundcube Dev Team
 *
 * Licensed under the GNU General Public License version 3 or
 * any later version as published by the Free Software Foundation.
 * For full license information see http://www.gnu.org/licenses/gpl-3.0
 */

namespace Roundcube\Logger;

use Monolog\Formatter\LineFormatter;

/**
 * Extended Monolog LineFormatter formatting objects as JSON pretty-printed
 * multi-line blocks.
 */
class MultilineFormatter extends LineFormatter
{
    protected function normalize($data)
    {
        if (is_object($data) && !($data instanceof \DateTime)) {
            $data = ["[object] (" . get_class($data) . ")" => get_object_vars($data)];
        }

        return parent::normalize($data);
    }

    protected function convertToString($data)
    {
        if (null === $data || is_scalar($data)) {
            return (string) $data;
        }

        return strtr(
            json_encode($this->normalize($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ['\n' => "\n"]);
    }
}
