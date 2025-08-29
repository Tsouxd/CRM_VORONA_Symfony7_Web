<?php
// src/EventSubscriber/TimezoneSubscriber.php

namespace App\EventSubscriber;

use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Events;

class TimezoneSubscriber implements EventSubscriber
{
    private string $timezone;

    public function __construct(string $timezone = 'Indian/Antananarivo')
    {
        $this->timezone = $timezone;
    }

    public function postConnect(ConnectionEventArgs $args)
    {
        $args->getConnection()->executeStatement(sprintf("SET time_zone = '%s'", $this->timezone));
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postConnect,
        ];
    }
}