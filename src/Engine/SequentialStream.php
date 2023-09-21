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

namespace ElephantIO\Engine;

class SequentialStream
{
    /**
     * @var string
     */
    protected $data = null;

    /**
     * Constructor.
     *
     * @param string $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Read a fixed size data.
     *
     * @param int $len
     * @return string
     */
    public function read($len = 1)
    {
        if (!$this->isEof()) {
            $result = substr($this->data, 0, $len);
            $this->data = substr($this->data, $len);

            return $result;
        }
    }

    /**
     * Read data up to delimeter.
     *
     * @param string $delimeter
     * @param array $noskips
     * @return string
     */
    public function readUntil($delimeter = ',', $noskips = [])
    {
        if (!$this->isEof()) {
            list($p, $d) = $this->getPos($this->data, $delimeter);
            if (false !== $p) {
                $result = substr($this->data, 0, $p);
                // skip delimeter
                if (!in_array($d, $noskips)) {
                    $p++;
                }
                $this->data = substr($this->data, $p);

                return $result;
            }
        }
    }

    /**
     * Get first position of delimeters.
     *
     * @param string $data
     * @param string $delimeter
     * @return boolean|number
     */
    protected function getPos($data, $delimeter)
    {
        $pos = false;
        $delim = null;
        for ($i = 0; $i < strlen($delimeter); $i++) {
            $d = substr($delimeter, $i, 1);
            if (false !== ($p = strpos($data, $d))) {
                if (false === $pos || $p < $pos) {
                    $pos = $p;
                    $delim = $d;
                }
            }
        }

        return [$pos, $delim];
    }

    /**
     * Get unprocessed data.
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Is EOF.
     *
     * @return boolean
     */
    public function isEof()
    {
        return 0 === strlen($this->data) ? true : false;
    }
}
