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
namespace FriendsOfHyperf\WebsocketConnection\Listener;

use FriendsOfHyperf\WebsocketConnection\Server;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeMainServerStart;
use Hyperf\Framework\Event\MainWorkerStart;
use Psr\Container\ContainerInterface;

/**
 * @Listener
 */
class InitServerIdListener implements ListenerInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return string[] returns the events that you want to listen
     */
    public function listen(): array
    {
        return [
            // BeforeMainServerStart::class,
            MainWorkerStart::class,
        ];
    }

    public function process(object $event)
    {
        /** @var Server $server */
        $server = $this->container->get(Server::class);
        $server->setServerId(uniqid());
        $server->setIsRunning(true);

        $server->start();
    }
}
