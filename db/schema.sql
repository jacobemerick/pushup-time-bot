# ------------------------------------------------------------
# Database schema for pushup time twitter bot
# For use w/ https://github.com/jacobemerick/pushuptime
# ------------------------------------------------------------


# Create database to hold schema
# ------------------------------------------------------------
DROP DATABASE IF EXISTS `pushuptime`;

CREATE DATABASE `pushuptime`;

SHOW WARNINGS;

USE `pushuptime`;


# Holder for Twitter followers (participants)
# ------------------------------------------------------------
DROP TABLE IF EXISTS `follower`;

CREATE TABLE `follower` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `twitter_id` bigint(20) unsigned NOT NULL,
  `screen_name` varchar(20) NOT NULL,
  `description` varchar(160) NOT NULL DEFAULT '',
  `profile_image` varchar(100) NOT NULL DEFAULT '',
  `location` varchar(100) NOT NULL DEFAULT '',
  `time_zone` varchar(100) NOT NULL DEFAULT '',
  `is_protected` tinyint(1) unsigned NOT NULL,
  `follower_count` bigint(20) unsigned NOT NULL,
  `friend_count` bigint(20) unsigned NOT NULL,
  `status_count` bigint(20) unsigned NOT NULL,
  `account_create_date` datetime DEFAULT NULL,
  `is_following` tinyint(1) unsigned NOT NULL,
  `create_date` datetime NOT NULL,
  `update_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `twitter_id_index` (`twitter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

SHOW WARNINGS;


# Preferences for each participating follower
# ------------------------------------------------------------
DROP TABLE IF EXISTS `reminder_preference`;

CREATE TABLE `reminder_preference` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `follower_id` int(11) unsigned NOT NULL,
  `weekday` varchar(20) NOT NULL DEFAULT '',
  `hour` varchar(40) NOT NULL DEFAULT '',
  `per_day` tinyint(1) unsigned NOT NULL,
  `create_date` datetime NOT NULL,
  `update_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `follower_id_index` (`follower_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

SHOW WARNINGS;


# List of sent reminders per follower
# ------------------------------------------------------------
DROP TABLE IF EXISTS `reminder`;

CREATE TABLE `reminder` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `follower_id` int(11) unsigned NOT NULL,
  `preference_id` int(11) unsigned NOT NULL,
  `message` tinyint(1) unsigned NOT NULL,
  `create_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `follower_id_index` (`follower_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

SHOW WARNINGS;

# Logging table for all bot mentions
# ------------------------------------------------------------
DROP TABLE IF EXISTS `mention`;

CREATE TABLE `mention` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tweet_id` bigint(20) unsigned NOT NULL,
  `text` VARCHAR(160) NOT NULL DEFAULT '',
  `follower_id` int(11) unsigned NOT NULL,
  `create_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `follower_id_index` (`follower_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

SHOW WARNINGS;

# Tracking table for follower performance
# ------------------------------------------------------------
DROP TABLE IF EXISTS `performance`;

CREATE TABLE `performance` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `follower_id` int(11) unsigned NOT NULL,
  `reminder_id` int(11) unsigned NOT NULL,
  `mention_id` int(11) unsigned NOT NULL,
  `amount` tinyint(1) unsigned NOT NULL,
  `create_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `follower_id_index` (`follower_id`),
  KEY `reminder_id_index` (`reminder_id`),
  KEY `mention_id_index` (`mention_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

SHOW WARNINGS;

