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

namespace ElephantIO;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;

/**
 * Represents an engine used within ElephantIO to send / receive messages from
 * a websocket real time server
 *
 * Loosely based on the work of the following :
 *   - Ludovic Barreca (@ludovicbarreca)
 *   - Mathieu Lallemand (@lalmat)
 *
 * @author Baptiste Clavié <baptiste@wisembly.com>
 */
interface EngineInterface extends LoggerAwareInterface
{
    /**
     * Connect to the targeted server
     */
    public function connect();

    /** Closes the connection to the websocket */
    public function close();

    /**
     * Read data from the socket
     *
     * @return string Data read from the socket
     */
    public function read();

    /**
     * Emits a message through the websocket
     *
     * @param string $event Event to emit
     * @param array  $args  Arguments to send
     */
    public function emit($event, $args);

    /**
     * Wait for event to arrive.
     *
     * @param string $event
     * @return \stdClass
     */
    public function wait($event);

    /**
     * Keeps alive the connection
     */
    public function keepAlive();

    /**
     * Gets the name of the engine
     *
     * @return string
     */
    public function getName();

    /**
     * Sets the namespace for the next messages
     *
     * @param string $namespace the namespace
     */
    public function of($namespace);
}
