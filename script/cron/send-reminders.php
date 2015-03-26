<?php

/**
 * cron that will send out reminders for followers
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
    $user_timezone = $converter($follower->time_zone);
    $system_time = new DateTime('now');
    $user_time = new DateTime('now', $user_timezone);

    // test to make sure that the day makes sense
    $current_day = $user_time->format('w');
    $allowed_days = explode(',', $follower->weekday);
    if (!in_array($current_day, $allowed_days)) {
        continue;
    }

    // test to make sure that the hour is valid
    $current_hour = $user_time->format('H');
    $allowed_hours = explode(',', $follower->hour);
    if (!in_array($current_hour, $allowed_hours)) {
        continue;
    }

    // sanity check to make sure we haven't already exceeded number of reminders per day
    $user_offset = ($system_time->getOffset() - $user_time->getOffset());
    $user_offset = DateInterval::createFromDateString("{$user_offset} seconds");
    $query = '
        SELECT
            COUNT(1) AS count
        FROM
            reminder
        WHERE
            reminder.follower_id = :follower AND
            reminder.create_date BETWEEN :start_date AND :end_date';
    $parameters = [
        'follower'    => $follower->id,
        'start_date'  => $system_time->setTime(0, 0)->add($user_offset)->format('c'),
        'end_date'    => $system_time->setTime(23, 59, 59)->add($user_offset)->format('c'),
    ];
    try {
        $count = $pdo->fetchValue($query, $parameters);
    } catch (PDOException $e) {
        exit("ABORT - fetch count for follower {$follower->id} failed with message: {$e->getMessage()}.");
    }
    if ($count >= $follower->per_day) {
        continue;
    }

    // test to see if a notification has been sent during this 'chunk'
    $start_time = $user_time->setTime(reset($allowed_hours), 0)->getTimestamp();
    $end_time = $user_time->setTime(end($allowed_hours), 59, 59)->getTimestamp();
    $chunked_timespan = ($end_time - $start_time) / $follower->per_day;
    $weight = (time() - $start_time) / $chunked_timespan;
    $expected_notifications = ceil($weight);
    if ($count >= $expected_notifications) {
        continue;
    }

    // determine if a notification should be sent out
    $random_number = rand(0, 1000);
    $random_comparison = ($weight - $count) * 1000;
    $random_comparison = round($random_comparison);
    if ($random_number > $random_comparison) {
        continue;
    }

    // send reminder
    $tweet = sprintf($messages['reminder'], $follower->screen_name, $user_time->format('g:i a'));
    try {
        $result = $rest_client->post('statuses/update.json', [
            'body' => [
                'status' => $tweet,
            ],
        ]);
    } catch (Exception $e) {
        exit("ABORT - tried to remind {$follower->screen_name} and got failure: {$e->getMessage()}.");
    }
    if ($result->getStatusCode() != 200) {
        exit("ABORT - tried to remind {$follower->screen_name} and got failure code: {$result->getStatusCode()}.");
    }

    // then we insert into the record for our records
    $query = '
        INSERT INTO
            `reminder`
            (`follower_id`, `preference_id`, `tweet_id`, `create_date`)
        VALUES
            (:follower, :preference, :tweet, NOW())';
    $parameters = [
        'follower'    => $follower->id,
        'preference'  => $follower->preference_id,
        'tweet'       => $result->json()['id_str'],
    ];
    try {
        $pdo->perform($query, $parameters);
    } catch (PDOException $e) {
        exit("ABORT - was unable to insert the reminder record into table, error: {$e->getMessage()}.");
    }
}

