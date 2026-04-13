
--
-- Structure de la table `llx_product_stock_stats`
--

CREATE TABLE `llx_product_stock_stats` (
  `fk_product` int(11) NOT NULL,
  `date` date NOT NULL,
  `stock_phy` decimal(10,2) DEFAULT NULL,
  `stock_theo` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `llx_product_stock_stats`
--
ALTER TABLE `llx_product_stock_stats`
  ADD PRIMARY KEY (`fk_product`,`date`) USING BTREE,
  ADD KEY `date` (`date`);
COMMIT;
