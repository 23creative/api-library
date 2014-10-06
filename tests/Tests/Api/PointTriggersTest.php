<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     MIT http://opensource.org/licenses/MIT
 */

namespace Mautic\Tests\Api;

/**
 * PointTriggers Context
 *
 * @package Mautic\Api
 */
class PointTriggers extends CommonTest
{

    public function testGet()
    {
        $apiContext = $this->getContext('pointTriggers');
        $result     = $apiContext->get(1);

        $valid = empty($result['error']) || $result['error']['code'] == 404;
        $this->assertTrue($valid, $result['error']['message']);
    }

    public function testGetList()
    {
        $apiContext = $this->getContext('pointTriggers');
        $result     = $apiContext->getList();

        $this->assertTrue(empty($result['error']), $result['error']['message']);
    }
}