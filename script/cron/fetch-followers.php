<?php

/**
 * cron that will fetch current followers of the bot and do some stuff
 * new followers will get inserted into the program
 * also, new followers will be followed by the bot
 * followers who left will be de-activated from the program
 * followers who have returned after a previous lapse will be re-activated
 */

require_once __DIR__ . '/../../bootstrap.php';

// fetch followers of the registered bot
// todo handle paginated responses (twitter only responds in groups of 5,000)
try {
    $result = $rest_client->get('followers/ids.json');
} catch (Exception $e) {
    exit("ABORT - fetch followers request failed with message: {$e->getMessage()}.");
}
if ($result->getStatusCode() != 200) {
    exit("ABORT - fetch followers request failed with code: {$result->getStatusCode()}.");
}
$twitter_followers = $result->json()['ids'];

// fetch local followers to determine who are new followers
$query = '
    SELECT
        twitter_id
    FROM
        follower';
try {
    $registered_followers = $pdo->fetchCol($query);
} catch (PDOException $e) {
    exit("ABORT - fetch registered followers failed with error: {$e->getMessage()}.");
}
$new_followers = array_diff($twitter_followers, $registered_followers);

// if there are new followers, than we need to loop through them and do some stuff
if (count($new_followers) > 0) {
    // first we want to fetch more information for each follower
    // todo handle pagination (twitter can only accept 100 ids per request)
    try {
        $result = $rest_client->post('users/lookup.json', [
            'body' => [
                'user_id' => implode(',', $new_followers),
            ],
        ]);
    } catch (Exception $e) {
        exit("ABORT - user lookup request failed with message: {$e->getMessage()}.");
    }
    if ($result->getStatusCode() != 200) {
        exit("ABORT - user lookup request failed with code: {$result->getStatusCode()}.");
    }

    // quick sanity check to make sure that we got back the proper number of follower objects
    $response = $result->json();
    if (count($response) != count($new_followers)) {
        exit("ABORT - the number of returned followers does not equal the number requested.");
    }

    // now we need to do individual actions on each follower
    foreach ($response as $follower) {
        // we want to try to follow them back
        try {
            $result = $rest_client->post('friendships/create.json', [
                'body' => [
                    'user_id'  => $follower['id'],
                    'follow'   => true,
                ],
            ]);
        } catch (Exception $e) {
            exit("ABORT - tried to follow a user and got failure message: {$e->getMessage()}.");
        }
        if ($result->getStatusCode() != 200) {
            exit("ABORT - tried to follow a user and got failure code: {$result->getStatusCode()}.");
        }

        // then we will insert them into the program
        $query = '
            INSERT INTO
                `follower`
                (`twitter_id`, `screen_name`, `description`, `profile_image`, `location`, `time_zone`, `is_protected`, `follower_count`, `friend_count`, `status_count`, `account_create_date`, `is_following`, `create_date`)
            VALUES
                (:twitter_id, :screen_name, :description, :profile_image, :location, :time_zone, :is_protected, :follower_count, :friend_count, :status_count, :account_create_date, :is_following, NOW())';
        $parameters = [
            'twitter_id'           => $follower['id'],
            'screen_name'          => $follower['screen_name'],
            'description'          => $follower['description'],
            'profile_image'        => $follower['profile_image_url'],
            'location'             => $follower['location'],
            'time_zone'            => $follower['time_zone'],
            'is_protected'         => $follower['protected'] ? 1 : 0,
            'follower_count'       => $follower['followers_count'],
            'friend_count'         => $follower['friends_count'],
            'status_count'         => $follower['statuses_count'],
            'account_create_date'  => date('Y-m-d H:i:s', strtotime($follower['created_at'])),
            'is_following'         => 1,
        ];
        try {
            $pdo->perform($query, $parameters);
            $follower_id = $pdo->lastInsertId();
        } catch (PDOException $e) {
            exit("ABORT - was unable to insert the new follower into the table, error: {$e->getMessage()}.");
        }

        // default settings for preferences
        // todo this should be editable by another endpoint
        $query = '
            INSERT INTO
                `reminder_preference`
                (`follower_id`, `weekday`, `hour`, `per_day`, `create_date`)
            VALUES
                (:follower_id, :weekday, :hour, :per_day, NOW())';
        $parameters = [
            'follower_id'  => $follower_id,
            'weekday'      => '0,1,2,3,4,5,6',
            'hour'         => '7,8,9,10,11,12,13,14,15,16,17,18,19,20',
            'per_day'      => 3,
        ];
        try {
            $pdo->perform($query, $parameters);
        } catch (PDOException $e) {
            exit("ABORT - was unable to insert default reminder preferences into table, error: {$e->getMessage()}.");
        }
    }
}

// fetch any followers who are de-activated in our system but are now followers
$query = '
    SELECT
        twitter_id
    FROM
        follower
    WHERE
        is_following = :is_not_following';
$parameters = [
    'is_not_following' => 0,
];
try {
    $deactivated_followers = $pdo->fetchCol($query, $parameters);
} catch (PDOException $e) {
    exit("ABORT - fetch deactivated followers failed with error: {$e->getMessage()}.");
}
$returning_followers = array_intersect($twitter_followers, $deactivated_followers);

// if there are followers who have returned, we want to re-activate their status
if (count($returning_followers) > 0) {
    $query = '
        UPDATE
            follower
        SET
            is_following = :is_following
        WHERE
            twitter_id IN (:returned_follower_ids)';
    $parameters = [
        'is_following'           => 1,
        'returned_follower_ids'  => $returning_followers,
    ];
    try {
        $pdo->perform($query, $parameters);
    } catch (PDOException $e) {
        exit("ABORT - attempt to re-activate followers failed with error: {$e->getMessage()}");
    }
}

// fetch any followers who are active in our system but are no longer followers
$query = '
    SELECT
        twitter_id
    FROM
        follower
    WHERE
        is_following = :is_following';
$parameters = [
    'is_following' => 1,
];
try {
    $active_followers = $pdo->fetchCol($query, $parameters);
} catch (PDOException $e) {
    exit("ABORT - fetch activate followers failed with error: {$e->getMessage()}.");
}
$deactivated_followers = array_diff($active_followers, $twitter_followers);

// if there are freshly deactivated followers, we want to disable them in our program
if (count($deactivated_followers) > 0) {
    $query = '
        UPDATE
            follower
        SET
            is_following = :is_not_following
        WHERE
            twitter_id IN (:deactivated_follower_ids)';
    $parameters = [
        'is_not_following'          => 0,
        'deactivated_follower_ids'  => $deactivated_followers,
    ];
    try {
        $pdo->perform($query, $parameters);
    } catch (PDOException $e) {
        exit("ABORT - attempt to deactivate followers failed with error: {$e->getMessage()}");
    }
}

