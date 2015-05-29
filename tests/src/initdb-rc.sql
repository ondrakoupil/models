-- Adminer 3.3.3 MySQL dump

SET NAMES utf8;
SET foreign_key_checks = 0;
SET time_zone = 'SYSTEM';
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `relationcontroller_test`;
CREATE TABLE `relationcontroller_test` (
  `idprodukt` int(11) NOT NULL,
  `idkategorie` int(11) NOT NULL,
  PRIMARY KEY (`idprodukt`,`idkategorie`),
  KEY `idkategorie` (`idkategorie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;



INSERT INTO `relationcontroller_test` (`idprodukt`, `idkategorie`) VALUES
(1,	1),
(1,	3),
(5,	3),
(2,	4),
(4,	4),
(1,	7),
(5,	7),
(7,	7),
(1,	8),
(2,	8),
(5,	8),
(7,	8);

-- 2013-12-21 17:11:10
