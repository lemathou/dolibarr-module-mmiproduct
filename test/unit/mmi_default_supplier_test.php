<?php
/**
 * Tests unitaires de mmi_default_supplier::resolve() — RM3233.
 * Sans dépendance (ni Dolibarr ni phpunit) : php7.4 test/unit/mmi_default_supplier_test.php
 * Code de sortie 0 si tout passe.
 */

require_once __DIR__.'/../../class/mmi_default_supplier.class.php';

$fail = 0;
$n = 0;
function check($label, $cond)
{
	global $fail, $n;
	$n++;
	if ($cond) {
		echo "ok   $label\n";
	} else {
		$fail++;
		echo "FAIL $label\n";
	}
}
function l($id, $soc, $ref, $tms = '2026-01-01 00:00:00', $packaging = null, $status = 1)
{
	return ['id'=>$id, 'fk_soc'=>$soc, 'ref'=>$ref, 'packaging'=>$packaging, 'tms'=>$tms, 'status'=>$status];
}
$R = 'mmi_default_supplier';

// Défaut = 14 (réf A1), 2 fournisseurs
$state = ['fk_soc'=>14, 'ref'=>'A1', 'packaging'=>6];
$lines = [l(1, 14, 'A1', '2026-01-01 00:00:00', 6), l(2, 20, 'B1', '2026-06-01 00:00:00', 12)];

// 1. Modification du prix d'un fournisseur secondaire : rien ne change
$r = $R::resolve($state, $lines, $lines[1]);
check('secondaire modifié → keep', $r['reason'] === 'keep' && !$r['changed'] && !$r['notify']);

// 2. Création d'une ligne pour un nouveau fournisseur : rien ne change
$new = l(3, 30, 'C1', '2026-09-01 00:00:00');
$r = $R::resolve($state, array_merge($lines, [$new]), $new);
check('nouveau fournisseur créé → keep', $r['reason'] === 'keep' && !$r['changed']);

// 3. Réf du défaut modifiée : refresh réf + conditionnement
$mod = l(1, 14, 'A2', '2026-09-01 00:00:00', 8);
$r = $R::resolve($state, [$mod, $lines[1]], $mod);
check('réf du défaut modifiée → refresh', $r['reason'] === 'refresh' && $r['changed'] && $r['state']['ref'] === 'A2' && $r['state']['packaging'] === 8 && $r['state']['fk_soc'] === 14 && !$r['notify']);

// 4. Ligne du défaut modifiée sans changement de valeurs : pas de mise à jour
$r = $R::resolve($state, $lines, $lines[0]);
check('défaut modifié à l\'identique → pas de changement', !$r['changed']);

// 5. Produit sans défaut : la ligne créée devient le défaut, sans notification
$r = $R::resolve(['fk_soc'=>null, 'ref'=>null, 'packaging'=>null], [l(5, 30, 'C1')], l(5, 30, 'C1'));
check('sans défaut + création → init', $r['reason'] === 'init' && $r['state']['fk_soc'] === 30 && $r['state']['ref'] === 'C1' && !$r['notify']);

// 6. Bascule/fusion : la ligne du défaut passe à Morato (19776), le défaut n'a plus de prix → Morato
$moved = l(1, 19776, 'A1', '2026-09-18 10:00:00', 6);
$r = $R::resolve($state, [$moved, $lines[1]], $moved);
check('ligne du défaut déplacée → replace par la cible', $r['reason'] === 'replace' && $r['state']['fk_soc'] === 19776 && $r['state']['ref'] === 'A1' && !$r['notify']);

// 7. Défaut pointant vers un tiers sans prix (ex. tiers fusionné) + événement sur un autre → replace
$r = $R::resolve(['fk_soc'=>999, 'ref'=>'X', 'packaging'=>null], $lines, $lines[1]);
check('défaut orphelin + modif autre ligne → replace', $r['reason'] === 'replace' && $r['state']['fk_soc'] === 20);

// 8. Déplacement d'une ligne du défaut qui en a une autre : le défaut reste, réf rafraîchie
$state2 = ['fk_soc'=>14, 'ref'=>'A1', 'packaging'=>6];
$lines2 = [l(1, 19776, 'A1', '2026-09-18 10:00:00'), l(4, 14, 'A1-12', '2026-03-01 00:00:00', 12)];
$r = $R::resolve($state2, $lines2, $lines2[0]);
check('défaut garde une autre ligne → refresh depuis celle-ci', $r['reason'] === 'refresh' && $r['state']['fk_soc'] === 14 && $r['state']['ref'] === 'A1-12' && !$r['notify']);

// 9. Suppression du dernier prix du défaut : le plus récent le remplace, avec notification
$lines3 = [l(2, 20, 'B1', '2026-06-01 00:00:00', 12), l(3, 30, 'C1', '2026-08-01 00:00:00', 24)];
$r = $R::resolve($state, $lines3, null);
check('suppression du dernier prix du défaut → plus récent + notify', $r['reason'] === 'replace_recent' && $r['state']['fk_soc'] === 30 && $r['state']['ref'] === 'C1' && $r['state']['packaging'] === 24 && $r['notify']);

// 10. Égalité de tms : le rowid le plus grand gagne
$r = $R::resolve($state, [l(7, 20, 'B1', '2026-06-01 00:00:00'), l(9, 30, 'C1', '2026-06-01 00:00:00'), l(8, 40, 'D1', '2026-06-01 00:00:00')], null);
check('égalité de tms → rowid le plus grand', $r['state']['fk_soc'] === 30);

// 11. Suppression du tout dernier prix : défaut vidé, avec notification
$r = $R::resolve($state, [], null);
check('plus aucun prix → empty + notify', $r['reason'] === 'empty' && $r['state']['fk_soc'] === null && $r['state']['ref'] === null && $r['notify']);

// 12. Suppression d'un prix non-défaut : rien ne change
$r = $R::resolve($state, [$lines[0]], null);
check('suppression d\'un secondaire → keep', $r['reason'] === 'keep' && !$r['changed'] && !$r['notify']);

// 13. Suppression d'une des 2 lignes du défaut (celle de la réf produit) : refresh, pas de notification
$r = $R::resolve($state, [l(4, 14, 'A1-12', '2026-03-01 00:00:00', 12), $lines[1]], null);
check('suppression d\'une des lignes du défaut → refresh sans notify', $r['reason'] === 'refresh' && $r['state']['ref'] === 'A1-12' && !$r['notify']);

// 14. Pas de défaut + suppression avec prix restants : init sans notification
$r = $R::resolve(['fk_soc'=>null, 'ref'=>null, 'packaging'=>null], $lines3, null);
check('sans défaut + suppression → init sans notify', $r['reason'] === 'init' && $r['state']['fk_soc'] === 30 && !$r['notify']);

// 15. Pas de défaut, plus aucun prix : rien à faire
$r = $R::resolve(['fk_soc'=>null, 'ref'=>null, 'packaging'=>null], [], null);
check('sans défaut ni prix → aucun changement', !$r['changed'] && !$r['notify']);

// 16. Les prix désactivés (status=0) ne comptent pas
$r = $R::resolve($state, [l(1, 14, 'A1', '2026-01-01 00:00:00', 6, 0), $lines[1]], $lines[1]);
check('seul prix du défaut désactivé → replace', $r['reason'] === 'replace' && $r['state']['fk_soc'] === 20);
$r = $R::resolve(['fk_soc'=>null, 'ref'=>null, 'packaging'=>null], [l(6, 50, 'E1', '2026-01-01', null, 0)], l(6, 50, 'E1', '2026-01-01', null, 0));
check('ligne désactivée ne devient pas le défaut', !$r['changed']);

// 17. État partiel (clés manquantes) toléré
$r = $R::resolve([], [l(5, 30, 'C1')], l(5, 30, 'C1'));
check('état vide toléré → init', $r['reason'] === 'init' && $r['state']['fk_soc'] === 30);

echo "\n".($n - $fail)."/$n OK\n";
exit($fail ? 1 : 0);
