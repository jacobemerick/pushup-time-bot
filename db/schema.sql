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
  `is_protected` tinyint(1) unsigned NOT NULL,
  `follower_count` bigint(20) unsigned NOT NULL,
  `friend_count` bigint(20) unsigned NOT NULL,
  `status_count` bigint(20) unsigned NOT NULL,
  `account_create_date` datetime DEFAULT NULL,
  `is_following` tinyint(1) unsigned NOT NULL,
  `unfollow_count` tinyint(1) DEFAULT NULL,
  `create_date` datetime NOT NULL,
  `update_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `twitter_id_index` (`twitter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

SHOW WARNINGS;

