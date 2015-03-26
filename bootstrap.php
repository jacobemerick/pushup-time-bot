<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$handle = @fopen('config.json', 'r');
if ($handle == false) {
    exit('No configuration found.');
}
$config = fread($handle, filesize('config.json'));
fclose($handle);
$config = @json_decode($config, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    exit('Could not read configuration.');
}

$handle = @fopen('messages.json', 'r');
if ($handle == false) {
    exit('No messages file found.');
}
$messages = fread($handle, filesize('messages.json'));
fclose($handle);
$messages = @json_decode($messages, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    exit('Could not read messages.');
}

date_default_timezone_set($config['timezone']);
$bot_name = $config['bot_name'];

require_once 'vendor/autoload.php';

use Aura\Sql\ExtendedPdo;

$pdo = new ExtendedPdo(
    $config['database']['connection'],
    $config['database']['username'],
    $config['database']['password']
);

use GuzzleHttp\Client,
    GuzzleHttp\Subscriber\Oauth\Oauth1;

$oauth = new Oauth1([
    'consumer_key'     => $config['twitter']['consumer_key'],
    'consumer_secret'  => $config['twitter']['consumer_secret'],
    'token'            => $config['twitter']['token'],
    'token_secret'     => $config['twitter']['token_secret'],
]);

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

use Jacobemerick\TimezoneConverter\Converter;
$converter = new Converter(Converter::RAILS_FORMAT);

