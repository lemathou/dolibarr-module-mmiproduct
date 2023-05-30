SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `llxsq_product_competitor` (
  `rowid` int(11) NOT NULL,
  `datec` timestamp NOT NULL DEFAULT current_timestamp(),
  `tms` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fk_product` int(11) NOT NULL,
  `fk_soc` int(11) NOT NULL,
  `url` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `llxsq_product_competitor`
  ADD PRIMARY KEY (`rowid`),
  ADD UNIQUE KEY `fk_product` (`fk_product`,`fk_soc`) USING BTREE,
  ADD KEY `fk_soc` (`fk_soc`) USING BTREE;
ALTER TABLE `llxsq_product_competitor`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;

CREATE TABLE `llxsq_product_competitor_price` (
  `rowid` int(11) NOT NULL,
  `datec` timestamp NOT NULL DEFAULT current_timestamp(),
  `date` date NOT NULL,
  `fk_product` int(11) NOT NULL,
  `fk_soc` int(11) NOT NULL,
  `price` decimal(11,2) UNSIGNED NOT NULL,
  `qte` decimal(11,2) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `llxsq_product_competitor_price`
  ADD PRIMARY KEY (`rowid`),
  ADD INDEX `fk_product` (`fk_product`,`fk_soc`) USING BTREE,
  ADD KEY `fk_soc` (`fk_soc`);
ALTER TABLE `llxsq_product_competitor_price`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;
