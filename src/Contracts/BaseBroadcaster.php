<?php
namespace BPA\InfrastructureLib\Contracts;


abstract class BaseBroadcaster
{
    abstract public function broadcast($event, $payload);
    abstract public function subscribe($callback);
}
