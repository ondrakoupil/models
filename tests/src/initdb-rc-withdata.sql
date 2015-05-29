-- Adminer 3.3.3 MySQL dump

SET NAMES utf8;
SET foreign_key_checks = 0;
SET time_zone = 'SYSTEM';
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `relationcontroller_with_data_test`;
CREATE TABLE `relationcontroller_with_data_test` (
  `idprodukt` int(11) NOT NULL,
  `idkategorie` int(11) NOT NULL,
  `valid` tinyint(4) NOT NULL DEFAULT '1',
  `priorita` tinyint(4) NOT NULL DEFAULT '0',
  `nazev` varchar(20) COLLATE utf8_czech_ci,
  PRIMARY KEY (`idprodukt`,`idkategorie`),
  KEY `idkategorie` (`idkategorie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

INSERT INTO `relationcontroller_with_data_test` (`idprodukt`, `idkategorie`, `valid`, `priorita`, `nazev`) VALUES
(1,	1,	1,	2,	''),
(2,	1,	1,	-1,	''),
(2,	3,	1,	0,	''),
(5,	1,	0,	5,	'alt'),
(8,	1,	1,	0,	''),
(8,	3,	0,	8,	'admin'),
(8,	10,	0,	-5,	'hi\'tech');

-- 2014-01-05 12:32:56
