<?php
/**
 * [...description of the type ...]
 *
 * PHP version 5
 *
 * Copyright (C) project swissbib, University Library Basel, Switzerland
 * http://www.swissbib.org  / http://www.swissbib.ch / http://www.ub.unibas.ch
 *
 * Date: 07/22/14
 * Time: 4:49 PM
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category swissbib_VuFind2
 * @package  [...package name...]
 * @author   Guenter Hipler  <guenter.hipler@unibas.ch>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.swissbib.org
 */
namespace Swissbib\VuFind\Auth;
use Zend\ServiceManager\ServiceManager;

/**
 * Class Factory
 * factory for authentication (swissbib specific) authentication services
 *
 * @package Swissbib\VuFind\Auth
 */
class Factory
{

    /**
     * Construct Shibboleth mock object - hand in environments
     * without specific shib service provider installation (e.g. Snowflake)
     *
     * @param ServiceManager $sm
     * @return ShibbolethMock
     */
    public static function getShibMock(ServiceManager $sm)
    {
        return new ShibbolethMock($sm->getServiceLocator()->get('VuFind\ILSConnection'));
    }
}