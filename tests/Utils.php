<?php

/**
 * Test class for utility functions
 *
 * This file is part of the Roundcube server test suite
 *
 * @author Thomas Bruederli <thomas@roundcube.net>
 *
 * Copyright (C) 2017, Roundcube Dev Team
 *
 * Licensed under the GNU General Public License version 3 or
 * any later version as published by the Free Software Foundation.
 * For full license information see http://www.gnu.org/licenses/gpl-3.0
 */

namespace Roundcube;

class UtilsTest extends \PHPUnit_Framework_TestCase
{
    public function testRandomBytes()
    {
        $rand1 = Utils::randomBytes(24);
        $rand2 = Utils::randomBytes(24);
        $rand3 = Utils::randomBytes(40);

        $this->assertTrue($rand1 !== $rand2);
        $this->assertEquals(40, strlen($rand3));
    }
}

