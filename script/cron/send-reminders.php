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
    // test to make sure that the day makes sense
    $current_day = date('w');
    $allowed_days = explode(',', $follower->weekday);
    if (!in_array($current_day, $allowed_days)) {
        continue;
    }

    // test to make sure that the hour is valid
    $current_hour = date('H');
    $allowed_hours = explode(',', $follower->hour);
    if (!in_array($current_hour, $allowed_hours)) {
        continue;
    }

    // sanity check to make sure we haven't already exceeded number of reminders per day
    $query = '
        SELECT
            COUNT(1) AS count
        FROM
            reminder
        WHERE
            reminder.follower_id = :follower AND
            reminder.create_date BETWEEN(:start_date, :end_date)';
    $parameters = [
        'follower'    => $follower->id,
        'start_date'  => mktime(0, 0, 0),
        'end_date'    => mktime(23, 59, 59),
    ];
    try {
        $count = $pdo->fetchValue($query, $parameters);
    } catch (PDOException $e) {
        exit("ABORT - fetch count for follower {$follower->id} failed with message: {$e->getMessage()}.");
    }
    if ($count >= $follower->per_day) {
        continue;
    }
    
    // send reminder if applicable (twitter call + db insert)
}

