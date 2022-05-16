<?php

namespace Struzik\EPPClient\RabbitMQConnection;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Struzik\EPPClient\Connection\ConnectionInterface;
use Struzik\EPPClient\Exception\ConnectionException;
use Symfony\Component\Uid\Uuid;

class RabbitMQConnection implements ConnectionInterface
{
    private AMQPStreamConnection $connection;
    private string $queueName;
    private int $timeout;
    private LoggerInterface $logger;
    private bool $isOpened;
    private AMQPChannel $channel;
    private string $callbackQueue;
    private ?string $correlationId;
    private ?string $response;

    public function __construct(AMQPStreamConnection $connection, string $queueName, int $timeout, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->queueName = $queueName;
        $this->timeout = $timeout;
        $this->logger = $logger;
        $this->isOpened = false;
    }

    public function open(): void
    {
        try {
            $this->connection->reconnect();
            $this->channel = $this->connection->channel();
            [$this->callbackQueue] = $this->channel->queue_declare(
                '',
                false,
                false,
                true,
                false
            );
            $this->channel->basic_consume(
                $this->callbackQueue,
                '',
                false,
                true,
                false,
                false,
                [$this, 'onResponse']
            );
            $this->isOpened = true;
        } catch (\Exception $e) {
            $this->logger->error($e);
            throw new ConnectionException('An error occurred while opening the connection. See previous exception.', 0, $e);
        }
    }

    public function isOpened(): bool
    {
        return $this->connection->isConnected() && $this->isOpened;
    }

    public function close(): void
    {
        try {
            $this->connection->close();
            $this->isOpened = false;
        } catch (\Exception $e) {
            $this->logger->error($e);
            throw new ConnectionException('An error occurred while closing the connection. See previous exception.', 0, $e);
        }
    }

    public function read(): string
    {
        try {
            while ($this->response === null) {
                $this->channel->wait(null, false, $this->timeout);
            }
        } catch (\Exception $e) {
            $this->logger->error($e);
            throw new ConnectionException('An error occurred while trying to read the response. See previous exception.', 0, $e);
        }

        $this->logger->info('The data read from the EPP connection', ['body' => $this->response]);

        $response = $this->response;
        $this->response = null;
        if (strpos($response, '<?xml') !== 0) {
            throw new ConnectionException($response);
        }

        return $response;
    }

    public function write(string $xml): void
    {
        $this->logger->info('The data written to the EPP connection', ['body' => $xml]);

        try {
            $this->response = null;
            $this->correlationId = (string) Uuid::v4();

            $message = new AMQPMessage($xml, ['correlation_id' => $this->correlationId, 'reply_to' => $this->callbackQueue, 'expiration' => $this->timeout * 1000]);
            $this->channel->basic_publish($message, '', $this->queueName);
        } catch (\Exception $e) {
            $this->logger->error($e);
            throw new ConnectionException('An error occurred while trying to write the request. See previous exception.', 0, $e);
        }
    }

    public function onResponse(AMQPMessage $message): void
    {
        if ($message->get('correlation_id') === $this->correlationId) {
            $this->response = $message->body;
        }
    }
}
