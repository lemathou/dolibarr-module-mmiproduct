CREATE TABLE IF NOT EXISTS `llx_c_cat_universe` (
  `rowid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `date_c` timestamp NOT NULL DEFAULT current_timestamp(),
  `tms` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `code` varchar(16) NOT NULL,
  `label` varchar(128) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `pos` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`rowid`),
  UNIQUE KEY `code` (`code`),
  KEY `pos` (`pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
