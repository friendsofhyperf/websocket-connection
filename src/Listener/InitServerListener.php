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

namespace FriendsOfHyperf\WebsocketClusterAddon\Listener;

use FriendsOfHyperf\WebsocketClusterAddon\Client\ClientInterface;
use FriendsOfHyperf\WebsocketClusterAddon\Client\TableClient;
use FriendsOfHyperf\WebsocketClusterAddon\Node\NodeInterface;
use FriendsOfHyperf\WebsocketClusterAddon\Node\TableNode;
use FriendsOfHyperf\WebsocketClusterAddon\Server;
use FriendsOfHyperf\WebsocketClusterAddon\Status\TableStatus;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\AfterWorkerStart;
use Hyperf\Framework\Event\BeforeMainServerStart;
use Hyperf\Framework\Event\MainWorkerStart;
use Hyperf\Stringable\Str;

use function Hyperf\Collection\value;

class InitServerListener implements ListenerInterface
{
    public function __construct(
        protected ConfigInterface $config,
        protected StdoutLoggerInterface $logger,
        protected ClientInterface $client,
        protected NodeInterface $node,
        protected Server $server
    ) {}

    /**
     * @return string[] returns the events that you want to listen
     */
    public function listen(): array
    {
        return [
            BeforeMainServerStart::class,
            AfterWorkerStart::class,
            MainWorkerStart::class,
        ];
    }

    /**
     * @param AfterWorkerStart|BeforeMainServerStart|MainWorkerStart|object $event
     */
    public function process(object $event): void
    {
        match (true) {
            $event instanceof BeforeMainServerStart => value(function () {
                $this->setServerId();
                $this->initClient();
                $this->initNode();
            }),
            $event instanceof MainWorkerStart => $this->start(),
            $event instanceof AfterWorkerStart => $this->setWorkerId($event->workerId),
            default => null
        };
    }

    public function initClient(): void
    {
        if ($this->client instanceof TableClient) {
            $size = (int) $this->config->get('websocket_cluster.client.table.size', 10240);
            $this->client->initTable($size);
            $this->logger->info(sprintf('[WebsocketClusterAddon] table initialized by %s', self::class));
        }

        $status = $this->client->getStatusInstance();

        if ($status instanceof TableStatus) {
            $size = (int) $this->config->get('websocket_cluster.client.table.size', 10240);
            $status->initTable($size);
            $this->logger->info(sprintf('[WebsocketClusterAddon] table initialized by %s', self::class));
        }
    }

    public function initNode(): void
    {
        if ($this->node instanceof TableNode) {
            $size = (int) $this->config->get('websocket_cluster.node.table.size', 10240);
            $this->node->initTable($size);
            $this->logger->info(sprintf('[WebsocketClusterAddon] table initialized by %s', self::class));
        }
    }

    public function setServerId(): void
    {
        $serverId = Str::slug(gethostname() ?: uniqid());
        $this->server->setServerId($serverId);
        $this->logger->info(sprintf('[WebsocketClusterAddon] @%s #%s serverId initialized by %s', -1, $serverId, self::class));
    }

    public function setWorkerId(int $workerId = 0): void
    {
        $this->server->setWorkerId($workerId);
        $this->logger->info(sprintf('[WebsocketClusterAddon] @%s #%s initialized by %s', $this->server->getServerId(), $workerId, self::class));
    }

    public function start(): void
    {
        $this->server->start();
        $this->logger->info(sprintf('[WebsocketClusterAddon] @%s #%s started by %s', $this->server->getServerId(), 0, self::class));
    }
}
