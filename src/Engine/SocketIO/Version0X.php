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

use InvalidArgumentException;

use ElephantIO\Payload\Encoder;
use ElephantIO\Engine\AbstractSocketIO;
use ElephantIO\Engine\Socket;

use ElephantIO\Exception\SocketException;
use ElephantIO\Exception\UnsupportedTransportException;
use ElephantIO\Exception\ServerConnectionFailureException;

/**
 * Implements the dialog with Socket.IO version 0.x
 *
 * Based on the work of Baptiste Clavié (@Taluu)
 *
 * @auto ByeoungWook Kim <quddnr145@gmail.com>
 * @link https://tools.ietf.org/html/rfc6455#section-5.2 Websocket's RFC
 */
class Version0X extends AbstractSocketIO
{
    const PROTO_CLOSE         = 0;
    const PROTO_OPEN          = 1;
    const PROTO_HEARTBEAT     = 2;
    const PROTO_MESSAGE       = 3;
    const PROTO_JOIN_MESSAGE  = 4;
    const PROTO_EVENT         = 5;
    const PROTO_ACK           = 6;
    const PROTO_ERROR         = 7;
    const PROTO_NOOP          = 8;

    const TRANSPORT_POLLING   = 'xhr-polling';
    const TRANSPORT_WEBSOCKET = 'websocket';

    /** {@inheritDoc} */
    public function connect()
    {
        if ($this->isConnected()) {
            return;
        }

        $this->handshake();
        $this->upgradeTransport();
    }

    /** {@inheritDoc} */
    public function close()
    {
        if (!$this->isConnected()) {
            return;
        }

        $this->write(static::PROTO_CLOSE);

        $this->socket->close();
        $this->socket = null;
        $this->session = null;
        $this->cookies = [];
    }

    /** {@inheritDoc} */
    public function emit($event, array $args)
    {
        $this->write(static::PACKET_EVENT, json_encode(['name' => $event, 'args' => $args]));
    }

    /** {@inheritDoc} */
    public function wait($event)
    {
    }

    /** {@inheritDoc} */
    public function write($code, $message = null)
    {
        if (!$this->isConnected()) {
            return;
        }

        $payload = $this->getPayload($code, $message);
        $bytes = $this->socket->send((string) $payload);

        // wait a little bit of time after this message was sent
        \usleep($this->options['wait']);

        return $bytes;
    }

    /** {@inheritDoc} */
    public function of($namespace)
    {
        parent::of($namespace);

        $this->write(static::PROTO_OPEN);
    }

    /** {@inheritDoc} */
    public function getName()
    {
        return 'SocketIO Version 0.X';
    }

    /** {@inheritDoc} */
    protected function getDefaultOptions()
    {
        $defaults = parent::getDefaultOptions();

        $defaults['protocol']  = 1;
        $defaults['transport'] = static::TRANSPORT_WEBSOCKET;

        return $defaults;
    }

    /**
     * Create socket.
     *
     * @throws SocketException
     */
    protected function createSocket()
    {
        if ($this->socket) {
            $this->logger->debug('Closing socket connection');
            $this->socket->close();
            $this->socket = null;
        }
        $this->socket = new Socket($this->url, $this->context, array_merge($this->options, ['logger' => $this->logger]));
        if ($errors = $this->socket->getErrors()) {
            throw new SocketException($errors[0], $errors[1]);
        }
    }

    /**
     * Create payload.
     *
     * @param int $code
     * @param string $message
     * @throws \InvalidArgumentException
     * @return \ElephantIO\Payload\Encoder
     */
    protected function getPayload($code, $message)
    {
        if (!is_int($code) || 0 > $code || 6 < $code) {
            throw new \InvalidArgumentException('Wrong message type when trying to write on the socket');
        }

        return new Encoder($code . '::' . $this->namespace . ':' . $message, Encoder::OPCODE_TEXT, true);
    }

    /** Does the handshake with the Socket.io server and populates the `session` value object */
    protected function handshake()
    {
        if (null !== $this->session) {
            return;
        }

        $this->logger->debug('Starting handshake');

        // set timeout to default
        $this->options['timeout'] = $this->getDefaultOptions()['timeout'];

        $this->createSocket();

        $url = $this->socket->getParsedUrl();
        $uri = sprintf('/%s/%d/%s', trim($url['path'], '/'), $this->options['version'],
            $this->options['transport']);
        if (isset($url['query'])) {
            $uri .= '/?' . http_build_query($url['query']);
        }

        $this->socket->request($uri, ['Connection: close']);
        if ($this->socket->getStatusCode() != 200) {
            throw new ServerConnectionFailureException('unable to perform handshake');
        }

        $sess = explode(':', $this->socket->getBody());
        $handshake = [
            'sid' => $sess[0],
            'pingInterval' => $sess[1],
            'pingTimeout' => $sess[2],
            'upgrades' => array_flip(explode(',', $sess[3])),
        ];

        if (!in_array('websocket', $handshake['upgrades'])) {
            throw new UnsupportedTransportException('websocket');
        }

        $cookies = [];
        foreach ($this->socket->getHeaders() as $header) {
            $matches = null;
            if (preg_match('/^Set-Cookie:\s*([^;]*)/i', $header, $matches)) {
                $cookies[] = $matches[1];
            }
        }
        $this->cookies = $cookies;
        $this->session = new Session($handshake['sid'], $handshake['pingInterval'], $handshake['pingTimeout'], $handshake['upgrades']);

        $this->logger->debug(sprintf('Handshake finished with %s', var_export($this->session, true)));
    }

    /** Upgrades the transport to WebSocket */
    protected function upgradeTransport()
    {
        $this->logger->debug('Starting websocket upgrade');

        // set timeout based on handshake response
        $this->options['timeout'] = $this->session->getTimeout();

        $this->createSocket();

        $url = $this->socket->getParsedUrl();

        $uri = sprintf('/%s/%d/%s/%s', trim($url['path'], '/'), $this->options['protocol'], $this->options['transport'], $this->session->id);
        if (isset($url['query'])) {
            $uri .= '/?' . http_build_query($url['query']);
        }

        $key = \base64_encode(\sha1(\uniqid(\mt_rand(), true), true));

        $origin = '*';
        $headers = isset($this->context['headers']) ? (array) $this->context['headers'] : [] ;

        foreach ($headers as $header) {
            $matches = [];
            if (\preg_match('`^Origin:\s*(.+?)$`', $header, $matches)) {
                $origin = $matches[1];
                break;
            }
        }

        $headers = [
            'Upgrade: WebSocket',
            'Connection: Upgrade',
            sprintf('Sec-WebSocket-Key: %s', $key),
            'Sec-WebSocket-Version: 13',
            sprintf('Origin: %s', $origin),
        ];

        if (!empty($this->cookies)) {
            $headers[] = sprintf('Cookie: %s', implode('; ', $this->cookies));
        }
        $this->socket->request($uri, $headers, ['skip_body' => true]);
        if ($this->socket->getStatusCode() != 101) {
            throw new ServerConnectionFailureException('unable to upgrade to WebSocket');
        }

        $this->logger->debug('Websocket upgrade completed');
    }
}
