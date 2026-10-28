
--
-- Structure de la table `llx_product_stats`
--

CREATE TABLE `llx_product_stats` (
  `fk_product` int(11) NOT NULL,
  `date` date NOT NULL,
  `propal_nb` int(10) UNSIGNED DEFAULT NULL,
  `propal_qty` decimal(10,2) UNSIGNED DEFAULT NULL,
  `order_nb` int(10) UNSIGNED DEFAULT NULL,
  `order_qty` decimal(10,2) UNSIGNED DEFAULT NULL,
  `invoice_nb` int(10) UNSIGNED DEFAULT NULL,
  `invoice_qty` decimal(10,2) UNSIGNED DEFAULT NULL,
  `expe_nb` int(10) UNSIGNED DEFAULT NULL,
  `expe_qty` decimal(10,2) UNSIGNED DEFAULT NULL,
  `recep_nb` int(10) UNSIGNED DEFAULT NULL,
  `recep_qty` decimal(10,2) UNSIGNED DEFAULT NULL,
  `mvts_nb` int(10) UNSIGNED DEFAULT NULL,
  `mvts_qty` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `llx_product_stats`
--
ALTER TABLE `llx_product_stats`
  ADD PRIMARY KEY (`fk_product`,`date`) USING BTREE;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `llx_product_stats`
--
ALTER TABLE `llx_product_stats`
  ADD CONSTRAINT `llx_product_stats_ibfk_1` FOREIGN KEY (`fk_product`) REFERENCES `llx_product` (`rowid`);
COMMIT;
