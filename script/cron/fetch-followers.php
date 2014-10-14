<?php

require_once __DIR__ . '/../../bootstrap.php';

$result = $rest_client->get('followers/ids.json');
if ($result->getStatusCode() != 200) {
    exit("ABORT - fetch followers request failed with code: {$result->getStatusCode()}.");
}

$twitter_followers = $result->json();
if (count($twitter_followers['ids']) < 1) {
    exit("ABORT - no followers were found to process.");
}

// todo handle paginated responses

$query = '
    SELECT
        twitter_id
    FROM
        follower';
$registered_followers = $pdo->fetchCol($query);

// todo handle odd follower status

$new_followers = array_diff($twitter_followers, $registered_followers);

// todo fetch more information
// todo insert record into db

$removed_followers = array_diff($registered_followers, $twitter_followers);

// todo increment the unfollow, if critical than deactivate or drop the record

