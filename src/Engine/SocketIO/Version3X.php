<?php
/**
 * This file is part of the Elephant.io package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Wisembly
 * @license   http://www.opensource.org/licenses/MIT-License MIT License
 */

namespace ElephantIO\Engine\SocketIO;

/**
 * Implements the dialog with Socket.IO version 3.x
 *
 * Based on the work of Mathieu Lallemand (@lalmat)
 *
 * @author Baptiste Clavié <baptiste@wisembly.com>
 * @link https://tools.ietf.org/html/rfc6455#section-5.2 Websocket's RFC
 */
class Version3X extends Version1X
{

    /** {@inheritDoc} */
    public function getName()
    {
        return 'SocketIO Version 3.X';
    }

    /** {@inheritDoc} */
    protected function getDefaultOptions()
    {
        $defaults = parent::getDefaultOptions();

        $defaults['version'] = 4;

        return $defaults;
    }
}
