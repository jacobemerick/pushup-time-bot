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
                        `id`,
                        `screen_name`
                    FROM
                        `follower`
                    WHERE
                        `twitter_id` = :twitter_id';
                $parameters = [
                    'twitter_id'  => $message['user']['id'],
                ];
                try {
                    $follower = $pdo->fetchOne($query, $parameters);
                } catch (PDOException $e) {
                    exit("ABORT - follower lookup failed with error: {$e->getMessage()}.");
                }

                if (empty($follower)) {
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
                    'follower_id'  => $follower['id'],
                    'create_date'  => date('Y-m-d H:i:s'),
                ];
                try {
                    $pdo->perform($query, $parameters);
                    $mention = $pdo->lastInsertId();
                } catch (PDOException $e) {
                    exit("ABORT - was unable to insert mention into table, error {$e->getMessage()}.");
                }

                if (preg_match('/^@' . $bot_name . '\s+(\d+)\s*[\.!]?$/i', $message['text'], $match) === 1) {
                    if (!empty($message['in_reply_to_status_id'])) {
                        $query = '
                            SELECT
                                `id`
                            FROM
                                `reminder`
                            WHERE
                                `tweet_id` = :tweet_id
                            LIMIT 1';
                        $parameters = [
                            'tweet_id' => $message['in_reply_to_status_id'],
                        ];
                        try {
                            $reminder = $pdo->fetchValue($query, $parameters);
                        } catch (PDOException $e) {
                            exit("Abort - fetch reminder for reply {$message['in_reply_to_status_id']} failed with message {$e->getMessage()}.");
                        }
                    }
                    if (empty($reminder)) {
                        $reminder = 0;
                    }

                    $query = '
                        INSERT INTO
                            `performance`
                            (`follower_id`, `reminder_id`, `mention_id`, `amount`, `create_date`)
                        VALUES
                            (:follower_id, :reminder_id, :mention_id, :amount, :create_date)';
                    $parameters = [
                        'follower_id'  => $follower['id'],
                        'reminder_id'  => $reminder,
                        'mention_id'   => $mention,
                        'amount'       => $match[1],
                        'create_date'  => date('Y-m-d H:i:s'),
                    ];
                    try {
                        $pdo->perform($query, $parameters);
                    } catch (PDOException $e) {
                        exit("ABORT - was unable to insert performance into table, error {$e->getMessage()}.");
                    }

                    $tweet = "@{$follower['screen_name']} thanks - we recorded {$match[1]} pushups for you.";
                    try {
                        $result = $rest_client->post('statuses/update.json', [
                            'body' => [
                                'status' => $tweet,
                            ],
                        ]);
                    } catch (Exception $e) {
                        exit("ABORT - tried to tell {$follower['screen_name']} that we recorded some pushups and got failure {$e->getMessage()}.");
                    }
                    if ($result->getStatusCode() != 200) {
                        exit("ABORT - tried to tell {$follower['screen_name']} that we recorded some pushups and got failure code {$result->getStatusCode()}.");
                    }
                } else {
                    $tweet = "@{$follower['screen_name']} sorry, I couldn't understand what you said.";
                    try {
                        $result = $rest_client->post('statuses/update.json', [
                            'body' => [
                                'status' => $tweet,
                            ],
                        ]);
                    } catch (Exception $e) {
                        exit("ABORT - tried to tell {$follower['screen_name']} that we didn't understand their message and got failure {$e->getMessage()}.");
                    }
                    if ($result->getStatusCode() != 200) {
                        exit("ABORT - tried to tell {$follower['screen_name']} that we didn't understand their message and got failure code {$result->getStatusCode()}.");
                    }
                }
            }
        }
    }
}

