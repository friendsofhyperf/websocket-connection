<?php

declare(strict_types=1);
/**
 * This file is part of websocket-connection.
 *
 * @link     https://github.com/friendofhyperf/websocket-connection
 * @document https://github.com/friendofhyperf/websocket-connection/blob/main/README.md
 * @contact  huangdijia@gmail.com
 * @license  https://github.com/friendofhyperf/websocket-connection/blob/main/LICENSE
 */
namespace FriendsOfHyperf\WebsocketConnection\Sid;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerInterface;

class RedisSid implements SidInterface
{
    protected $connection = 'default';

    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * @var string
     */
    protected $prefix = 'ws-sids';

    /**
     * @var string
     */
    protected $serverId;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->redis = $container->get(RedisFactory::class)->get($this->connection);
        $this->logger = $container->get(StdoutLoggerInterface::class);
    }

    public function setServerId(string $serverId): void
    {
        $this->serverId = $serverId;
    }

    public function add(int $fd, int $uid): void
    {
        $this->redis->sAdd($this->getSidKey($uid), $this->getSid($fd));
    }

    public function del(int $fd, int $uid): void
    {
        $this->redis->sRem($this->getSidKey($uid), $this->getSid($fd));
    }

    public function size(int $uid): int
    {
        return $this->redis->sCard($this->getSidKey($uid));
    }

    public function flush(): void
    {
        $keys = $this->redis->keys($this->getSidKey('*'));
        $disconnected = [];

        foreach ($keys as $key) {
            $sids = $this->redis->sMembers($key);

            foreach ((array) $sids as $sid) {
                if ($this->isLocal($sid)) {
                    $disconnected[] = [$key, $sid];
                }
            }
        }

        $this->redis->multi();

        foreach ($disconnected as $item) {
            $this->logger->debug(sprintf('%s deleted by %s.', $item[1], __CLASS__));
            $this->redis->sRem(...$item);
        }

        $this->redis->exec();
    }

    public function getSidKey($uid): string
    {
        return sprintf('%s:%s', $this->prefix, $uid);
    }

    public function getSid(int $fd): string
    {
        return sprintf('%s#%s', $this->serverId, $fd);
    }

    public function getFd(string $sid): int
    {
        return (int) explode('#', $sid)[1] ?? 0;
    }

    public function isLocal(string $sid): bool
    {
        return (explode('#', $sid)[0] ?? '') == $this->serverId;
    }
}
