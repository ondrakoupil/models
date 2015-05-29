-- Adminer 3.3.3 MySQL dump

SET NAMES utf8;
SET foreign_key_checks = 0;
SET time_zone = 'SYSTEM';
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `datamanager_test_with_name`;
CREATE TABLE `datamanager_test_with_name` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(20) COLLATE utf8_czech_ci NOT NULL DEFAULT 'Unnamed' COMMENT 'Some name',
  `desc` varchar(30) COLLATE utf8_czech_ci NOT NULL,
  `number` int(11) NOT NULL DEFAULT '100' COMMENT 'Some number',
  `long_something` text COLLATE utf8_czech_ci NOT NULL COMMENT 'Is not cached',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

INSERT INTO `datamanager_test_with_name` (`id`, `name`, `desc`, `number`, `long_something`) VALUES
(1,	'One',	'This is the first number',	1,	'Lorem ipsum dolor sit amen'),
(2,	'Two',	'Second number',	2,	'Dolor sit amen'),
(3,	'Three',	'The number after two',	3,	'Don\'t know how it continues... ');

