<?php

use App\Services\Mailer;
use Psr\Container\ContainerInterface;

$containerBuilder->addDefinitions([
    Mailer::class => function (ContainerInterface $c) {
        return new Mailer();
    },
]);
