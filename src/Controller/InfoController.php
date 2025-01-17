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

namespace FriendsOfHyperf\WebsocketClusterAddon\Controller;

use FriendsOfHyperf\WebsocketClusterAddon\Client\ClientInterface;
use FriendsOfHyperf\WebsocketClusterAddon\Node\NodeInterface;
use FriendsOfHyperf\WebsocketClusterAddon\Server;
use FriendsOfHyperf\WebsocketClusterAddon\Status\RedisBitmapStatus;
use FriendsOfHyperf\WebsocketClusterAddon\Status\StatusInterface;
use FriendsOfHyperf\WebsocketClusterAddon\Subscriber\SubscriberInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Container\ContainerInterface;

class InfoController
{
    public function __construct(
        protected ContainerInterface $container,
        protected RequestInterface $request,
        protected Server $server,
        protected ClientInterface $client
    ) {}

    public function info()
    {
        if ($uid = $this->request->input('uid')) {
            $uid = (int) $uid;

            return [
                'online' => $this->client->getOnlineStatus($uid),
                'clients' => $this->client->size($uid),
            ];
        }

        return [
            'instances' => [
                NodeInterface::class => $this->container->get(NodeInterface::class)::class,
                ClientInterface::class => $this->container->get(ClientInterface::class)::class,
                StatusInterface::class => RedisBitmapStatus::class,
                SubscriberInterface::class => $this->container->get(SubscriberInterface::class)::class,
            ],
            'online' => $this->client->size(),
            'nodes' => $this->server->getMonitors(),
        ];
    }
}
