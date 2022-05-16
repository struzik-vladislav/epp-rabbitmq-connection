# EPP RabbitMQ Connection
[![Latest Stable Version](https://img.shields.io/github/v/release/struzik-vladislav/epp-rabbitmq-connection?sort=semver&style=flat-square)](https://packagist.org/packages/struzik-vladislav/epp-rabbitmq-connection)
[![Total Downloads](https://img.shields.io/packagist/dt/struzik-vladislav/epp-rabbitmq-connection?style=flat-square)](https://packagist.org/packages/struzik-vladislav/epp-rabbitmq-connection/stats)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![StandWithUkraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

Connection for communicating with EPP(Extensible Provisioning Protocol) servers via RabbitMQ server.

Connection for [struzik-vladislav/epp-client](https://github.com/struzik-vladislav/epp-client) library.

## Usage

```php
<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\NullLogger;
use Struzik\EPPClient\EPPClient;
use Struzik\EPPClient\NamespaceCollection;
use Struzik\EPPClient\RabbitMQConnection\RabbitMQConnection;
use Struzik\EPPClient\Request\Domain\CheckDomainRequest;
use Struzik\EPPClient\Response\Domain\CheckDomainResponse;

require_once __DIR__.'/vendor/autoload.php';

$rabbitConnection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$eppConnection = new RabbitMQConnection($rabbitConnection, 'epp.registry', 30, new NullLogger());
$eppClient = new EPPClient($eppConnection, new NullLogger());
$eppClient->getNamespaceCollection()->offsetSet(NamespaceCollection::NS_NAME_ROOT, 'urn:ietf:params:xml:ns:epp-1.0');
$eppClient->getNamespaceCollection()->offsetSet(NamespaceCollection::NS_NAME_CONTACT, 'urn:ietf:params:xml:ns:contact-1.0');
$eppClient->getNamespaceCollection()->offsetSet(NamespaceCollection::NS_NAME_HOST, 'urn:ietf:params:xml:ns:host-1.0');
$eppClient->getNamespaceCollection()->offsetSet(NamespaceCollection::NS_NAME_DOMAIN, 'urn:ietf:params:xml:ns:domain-1.0');

$eppClient->connect();

$request = new CheckDomainRequest($eppClient);
$request->addDomain('example.com');
/** @var CheckDomainResponse $response */
$response = $eppClient->send($request);

$eppClient->disconnect();
```
