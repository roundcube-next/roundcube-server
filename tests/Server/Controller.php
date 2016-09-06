<?php

/**
 * Test class for basic Roundcube Server controller methods
 *
 * This file is part of the Roundcube server test suite
 *
 * @author Thomas Bruederli <thomas@roundcube.net>
 *
 * Copyright (C) 2015, Roundcube Dev Team
 *
 * Licensed under the GNU General Public License version 3 or
 * any later version as published by the Free Software Foundation.
 * For full license information see http://www.gnu.org/licenses/gpl-3.0
 */

namespace Roundcube\Server;

use Sabre\HTTP;

/**
 * Extend Controller class to make protected methods public
 */
class ServerController extends Controller
{
    public function getRouteMatch($path)
    {
        return parent::getRouteMatch($path);
    }
}

class ControllerTest extends \PHPUnit_Framework_TestCase
{
    public function testGetRouteMatch()
    {
        $nop = function() {};
        $controller = new ServerController();
        $controller->routes['jmap'] = $nop;
        $controller->routes['auth'] = $nop;
        $controller->routes['.well-known/jmap'] = $nop;
        $controller->routes['download/{blobId}/{name}'] = $nop;

        $this->assertEquals('auth', $controller->getRouteMatch('auth'));
        $this->assertEquals('.well-known/jmap', $controller->getRouteMatch('.well-known/jmap'));
        $this->assertFalse($controller->getRouteMatch('foo/bar'));

        $this->assertEquals('download/{blobId}/{name}', $controller->getRouteMatch('download/one/222'));
        $this->assertFalse($controller->getRouteMatch('download/one'));
    }
}

