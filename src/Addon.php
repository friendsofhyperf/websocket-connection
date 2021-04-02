<?php

declare(strict_types=1);
/**
 * This file is part of websocket-cluster-addon.
 *
 * @link     https://github.com/friendofhyperf/websocket-cluster-addon
 * @document https://github.com/friendofhyperf/websocket-cluster-addon/blob/main/README.md
 * @contact  huangdijia@gmail.com
 * @license  https://github.com/friendofhyperf/websocket-cluster-addon/blob/main/LICENSE
 */
namespace FriendsOfHyperf\WebsocketClusterAddon;

use FriendsOfHyperf\WebsocketClusterAddon\Connection\ConnectionInterface;
use FriendsOfHyperf\WebsocketClusterAddon\Provider\ClientProviderInterface;
use FriendsOfHyperf\WebsocketClusterAddon\Subscriber\SubscriberInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\Coordinator\Constants;
use Hyperf\Utils\Coordinator\CoordinatorManager;
use Hyperf\Utils\Coroutine;
use Hyperf\WebSocketServer\Sender;
use Psr\Container\ContainerInterface;
use Throwable;

class Addon
{
    protected $prefix = 'wssa:servers';

    /**
     * @var int
     */
    protected $workerId;

    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * @var string
     */
    protected $redisPool = 'default';

    /**
     * @var string
     */
    protected $serverId;

    /**
     * @var bool
     */
    protected $isRunning;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var SubscriberInterface
     */
    protected $subscriber;

    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * @var Sender
     */
    protected $sender;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->connection = $container->get(ConnectionInterface::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->redis = $container->get(RedisFactory::class)->get($this->redisPool);
        $this->sender = $container->get(Sender::class);
        $this->subscriber = $container->get(SubscriberInterface::class);
    }

    public function setIsRunning(bool $isRunning): void
    {
        $this->isRunning = $isRunning;
    }

    public function setServerId(string $serverId): void
    {
        $this->serverId = $serverId;
    }

    public function getServerId(): string
    {
        return $this->serverId;
    }

    public function setWorkerId(int $workerId): void
    {
        $this->workerId = $workerId;
    }

    public function getWorkerId(): int
    {
        return $this->workerId;
    }

    public function start(): void
    {
        $this->isRunning = true;

        $this->subscribe();
        $this->keepalive();
        $this->clearUpExpired();
    }

    public function broadcast(string $payload): void
    {
        [$uid, $message, $isLocal] = unserialize($payload);

        if ($isLocal) {
            $this->doBroadcast($payload);

            return;
        }

        $this->publish($this->getChannelKey(), $payload);
    }

    public function subscribe(): void
    {
        Coroutine::create(function () {
            CoordinatorManager::until(Constants::WORKER_START)->yield();

            retry(PHP_INT_MAX, function () {
                try {
                    $this->subscriber->subscribe($this->getChannelKey(), function ($payload) {
                        $this->doBroadcast($payload);
                    });
                } catch (Throwable $e) {
                    $this->logger->error((string) $e);
                    throw $e;
                }
            }, 1000);
        });
    }

    public function keepalive(): void
    {
        Coroutine::create(function () {
            while (true) {
                if (! $this->isRunning) {
                    $this->logger->debug(sprintf('[WebsocketClusterAddon.%s] keepalive stopped by %s', $this->serverId, __CLASS__));
                    break;
                }

                $this->redis->zAdd($this->getServerListKey(), time(), $this->serverId);
                $this->logger->debug(sprintf('[WebsocketClusterAddon.%s] keepalive by %s', $this->serverId, __CLASS__));

                sleep(1);
            }
        });
    }

    public function clearUpExpired(): void
    {
        Coroutine::create(function () {
            while (true) {
                if (! $this->isRunning) {
                    $this->logger->debug(sprintf('[WebsocketClusterAddon.%s] clearUpExpired stopped by %s', $this->serverId, __CLASS__));
                    break;
                }

                $start = '-inf';
                $end = (string) strtotime('-10 seconds');
                $expiredServers = $this->redis->zRangeByScore($this->getServerListKey(), $start, $end);
                /** @var ConnectionInterface $connection */
                $connection = $this->container->get(ConnectionInterface::class);
                $client = $this->container->get(ClientProviderInterface::class);

                foreach ($expiredServers as $serverId) {
                    $connection->flush($serverId);
                    $client->flush($serverId);
                    $this->redis->zRem($this->getServerListKey(), $serverId);
                }

                $this->logger->info(sprintf('[WebsocketClusterAddon.%s] clear up by %s', $this->serverId, __CLASS__));

                sleep(3);
            }
        });
    }

    public function all(): array
    {
        return $this->redis->zRangeByScore($this->getServerListKey(), '-inf', '+inf');
    }

    protected function publish(string $channel, string $payload): void
    {
        $this->redis->publish($channel, $payload);
    }

    protected function doBroadcast(string $payload): void
    {
        [$uid, $message] = unserialize($payload);

        $fds = $this->connection->all((int) $uid);

        foreach ($fds as $fd) {
            $this->sender->push($fd, $message);
        }
    }

    protected function getChannelKey(): string
    {
        return join(':', [
            $this->prefix,
            'channel',
        ]);
    }

    protected function getServerListKey(): string
    {
        return join(':', [
            $this->prefix,
        ]);
    }
}