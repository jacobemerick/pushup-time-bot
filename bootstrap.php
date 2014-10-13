<?php

require_once 'vendor/autoload.php';

use GuzzleHttp\Client,
    GuzzleHttp\Subscriber\Oauth\Oauth1;

$oauth = new Oauth1([
    'consumer_key'     => 'my_key',
    'consumer_secret'  => 'my_secret',
    'token'            => 'my_token',
    'token_secret'     => 'my_token_secret',
]);

$rest_client = new Client([
    'base_url'  => 'https://api.twitter.com/1.1/',
    'defaults'  => ['auth' => 'oauth'],
]);

$rest_client->getEmitter()->attach($oauth);

