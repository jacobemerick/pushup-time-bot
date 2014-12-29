<?php

date_default_timezone_set('America/Chicago');

require_once 'vendor/autoload.php';

use Aura\Sql\ExtendedPdo;

$pdo = new ExtendedPdo(
    'connect-string',
    'username',
    'password'
);

use GuzzleHttp\Client,
    GuzzleHttp\Subscriber\Oauth\Oauth1;

$oauth = new Oauth1([
    'consumer_key'     => 'my_key',
    'consumer_secret'  => 'my_secret',
    'token'            => 'my_token',
    'token_secret'     => 'my_token_secret',
]);

$bot_name = 'pushuptime';

$rest_client = new Client([
    'base_url'  => 'https://api.twitter.com/1.1/',
    'defaults'  => ['auth' => 'oauth'],
]);
$rest_client->getEmitter()->attach($oauth);

$streaming_client = new Client([
    'base_url'  => 'https://userstream.twitter.com/1.1/',
    'defaults'  => ['auth' => 'oauth'],
]);
$streaming_client->getEmitter()->attach($oauth);

