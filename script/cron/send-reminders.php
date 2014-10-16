<?php

/**
 * cron that will send out reminders for followers to do pushups
 * logic will respect the settings for each follower
 * only so many reminders per follower will be sent out
 * and those reminders will happen on defined days and between hours
 */

require_once __DIR__ . '/../../bootstrap.php';

// fetch active followers and their preferences
$query = '
    SELECT
        follower.id,
        follower.screen_name,
        follower.time_zone,
        reminder_preference.id AS preference_id,
        reminder_preference.weekday,
        reminder_preference.hour,
        reminder_preference.per_day
    FROM
        follower
            INNER JOIN reminder_preference ON
                reminder_preference.follower_id = follower.id
    WHERE
        follower.is_following = :is_following';
$parameters = [
    'is_following' => 1,
];
try {
    $statement = $pdo->prepare($query);
    $statement->execute($parameters);
} catch (PDOException $e) {
    exit("ABORT - fetch active followers query failed with message: {$e->getMessage()}.");
}

// loop through result set and check to see if they are applicable for a reminder
while (($follower = $statement->fetch(PDO::FETCH_OBJ)) != false) {
    // test to see if the day is valid
    // test to see if the hour is valid
    // test to see how many reminders have been sent today (db call)
    // do random logic to determine if they should be sent a reminder (weighted accordingly)
    // send reminder if applicable (twitter call + db insert)
}

