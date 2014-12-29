<?php

/**
 * daemon that listens for mentions and attempts to parse what to do with them
 * for example, a response to a reminder will tally up the partcipants performance
 * also, a request for information will send out some sort of short report
 * most mentions should trigger some sort of response, even if it an error
 */

require_once __DIR__ . '/../../bootstrap.php';

// watch the endpoint for new mentions
try {
    $result = $streaming_client->get('user.json', ['stream' => true]);
} catch (Exception $e) {
    exit("ABORT - mention listener request failed with message: {$e->getMessage()}.");
}

if ($result->getStatusCode() != 200) {
    exit("ABORT - mention listener request failed with code {$e->getStatusCode()}.");
}
$stream = $result->getBody();

$line = '';
while (!$stream->eof()) {
    $line .= $stream->read(1);
    while (strstr($line, "\r\n") !== false) {
        list($message, $line) = explode("\r\n", $line, 2);
        $message = json_decode($message, true);

        if (isset($message['in_reply_to_screen_name']) && $message['in_reply_to_screen_name'] == $bot_name) {
            $query = '
                SELECT
                    1
                FROM
                    `mention`
                WHERE
                    `tweet_id` = :tweet_id';
            $parameters = [
                'tweet_id'  => $message['id_str'],
            ];
            try {
                $mention_exists = $pdo->fetchCol($query, $parameters);
            } catch (PDOException $e) {
                exit("ABORT - fetch possible mentions failed with error: {$e->getMessage()}.");
            }

            if ($mention_exists != 1) {
                $query = '
                    SELECT
                        `id`
                    FROM
                        `follower`
                    WHERE
                        `twitter_id` = :twitter_id';
                $parameters = [
                    'twitter_id'  => $message['user']['id'],
                ];
                try {
                    $follower_id = $pdo->fetchCol($query, $parameters);
                } catch (PDOException $e) {
                    exit("ABORT - follower lookup failed with error: {$e->getMessage()}.");
                }

                if (empty($follower_id)) {
                    echo "SKIP - unknown follower tried to contact us with message {$message['text']}.";
                    continue;
                }

                $query = '
                    INSERT INTO
                        `mention`
                        (`tweet_id`, `text`, `follower_id`, `create_date`)
                    VALUES
                        (:tweet_id, :text, :follower_id, :create_date)';
                $parameters = [
                    'tweet_id'     => $message['id_str'],
                    'text'         => $message['text'],
                    'follower_id'  => $follower_id,
                    'create_date'  => date('Y-m-d H:i:s'),
                ];
                try {
                    $pdo->perform($query, $parameters);
                } catch (PDOException $e) {
                    exit("ABORT - was unable to insert mention into table, error {$e->getMessage()}.");
                }
            }
        }
    }
}

