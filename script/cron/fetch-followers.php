<?php

require_once __DIR__ . '/../../bootstrap.php';

$result = $rest_client->get('followers/ids.json');
if ($result->getStatusCode() != 200) {
    exit("ABORT - fetch followers request failed with code: {$result->getStatusCode()}.");
}

$response = $result->json();
if (count($response['ids']) < 1) {
    exit("ABORT - no followers were found to process.");
}
$twitter_followers = $response['ids'];

// todo handle paginated responses (twitter only responds in groups of 5,000)

$query = '
    SELECT
        twitter_id
    FROM
        follower';
$registered_followers = $pdo->fetchCol($query);

$new_followers = array_diff($twitter_followers, $registered_followers);

if (count($new_followers) > 0) {

    // todo handle pagination (twitter can only accept 100 ids per request)
    $result = $rest_client->post('users/lookup.json', [
        'body' => [
            'user_id' => implode(',', $new_followers),
        ]
    ]);
    if ($result->getStatusCode() != 200) {
        exit("ABORT - user lookup request failed with code: {$result->getStatusCode()}.");
    }

    $response = $result->json();
    if (count($response) != count($new_followers)) {
        exit("ABORT - the number of returned followers does not equal the number requested.");
    }

    foreach ($response as $follower) {
        $result = $rest_client->post('friendships/create.json', [
            'body' => [
                'user_id'  => $follower['id'],
                'follow'   => true,
            ],
        ]);

        if ($result->getStatusCode() != 200) {
            exit("ABORT - tried to follow a user and got failure code: {$result->getStatusCode()}.");
        }

        $query = '
            INSERT INTO
                `follower` (`twitter_id`, `screen_name`, `description`, `is_protected`, `follower_count`, `friend_count`, `status_count`, `account_create_date`, `is_following`, `create_date`)
            VALUES
                (:twitter_id, :screen_name, :description, :protected_account, :follower_count, :friend_count, :status_count, :account_create_date, :follower, NOW())';

        $params = [
            'twitter_id'           => $follower['id'],
            'screen_name'          => $follower['screen_name'],
            'description'          => $follower['description'],
            'protected_account'    => $follower['protected'] ? 1 : 0,
            'follower_count'       => $follower['followers_count'],
            'friend_count'         => $follower['friends_count'],
            'status_count'         => $follower['statuses_count'],
            'account_create_date'  => date('Y-M-D H:i:s', strtotime($follower['created_at'])),
            'follower'             => 1,
        ];

        $statement = $pdo->prepare($query);
        try {
            $statement->execute($params);
        } catch (PDOException $e) {
            exit("ABORT - was unable to insert the new follower into the table, error: {$e->getMessage()}");
        }

    }

}
    
$removed_followers = array_diff($registered_followers, $twitter_followers);

if (count($removed_followers) > 0) {
    foreach ($removed_followers as $removed_follower) {
        $query = '
            UPDATE
                `follower`
            SET
                `is_following` = :not_following
            WHERE
                `twitter_id` = :twitter_id';

        $params = [
            'not_following'  => 0,
            'twitter_id'     => $removed_follower,
        ];

        $statement = $pdo->prepare($query);
        try {
            $statement->execute($params);
        } catch (PDOException $e) {
            exit("ABORT - was unable to update status of non follower, error: {$e->getMessage()}");
        }
    }
}

