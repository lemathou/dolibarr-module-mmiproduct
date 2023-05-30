<?php

// Load Dolibarr environment
require_once 'env.inc.php';
require_once 'main_load.inc.php';

$sql = 'UPDATE `'.MAIN_DB_PREFIX.'societe`
	SET `url`=NULL
	WHERE`url`="www.dema.be"';
$db->query($sql);

$s_list = [];
$s_list_url = [];
$sql = 'SELECT s.`rowid`, s.`nom`, s.`url`, s2.`competitor`
	FROM `'.MAIN_DB_PREFIX.'societe` s
	LEFT JOIN `'.MAIN_DB_PREFIX.'societe_extrafields` s2
		ON s2.fk_object=s.rowid
	WHERE s.`fournisseur` = 1';
//echo '<pre>'.$sql.'</pre>';
$q = $db->query($sql);
while($r=$q->fetch_assoc()) {
	if (!empty($r['url'])) {
		$r['site'] = parse_url($r['url']);
		$s_list_url[$r['site']['host']] = $r['rowid'];
	}
	$se = preg_split('/[\.\/\- ]+/', $r['nom']);
	foreach($se as $j=>$k) {
		if (in_array($k, ['www', 'en', 'et', 'services', 'materiaux', 'sarl', 'pour', 'de']))
			unset($se[$j]);
	}
	$r['namee'] = $se;
	$s_list[$r['rowid']] = $r;
}
var_dump($s_list_url);
//var_dump($s_list);

$domains = [];
$l = [];

$n = 0;
$handle = fopen('../../../documents/competitor/competitor.csv','r');
while ( ($data = fgetcsv($handle) ) !== FALSE ) {
	//process
	//var_dump($data);
	if (empty($cols)) {
		$cols = $data;
		continue;
	}
	$n++;

	$id = $data[0];
	$ref = $data[1];

	$d1 = [
		$data[2], // date
		$data[3], // URL
		$data[4], // Prix
	];
	$d2 = [
		$data[5],
		$data[6],
		$data[4],
	];
	$d3 = [
		$data[8],
		$data[9],
		$data[10],
	];

	$l[$n] = [
		'id' => $id,
		'd' => [],
	];

	for ($i=1; $i<=3; $i++) {
		$r = ${'d'.$i};
		if (empty($r[2])) {
			continue;
		}
		$r[0] = !empty($r[0]) ?implode('-', array_reverse(explode('/', $r[0]))) :date('Y-m-d');
		$r[3] = parse_url($r[1]);
		$r[2] = preg_replace('/[^0-9\.]/', '', str_replace(',', '.', $r[2]));
		if (!empty($r[3]['host'])) {
			$domain = $r[3]['host'];
			$s_id = NULL;
			if(!in_array($domain, $domains)) {
				var_dump($domain);
				if (isset($s_list_url[$domain])) {
					var_dump($s_list_url[$domain]);
					$s_id = $s_list_url[$domain];
				}
				else {
					$de = preg_split('/[\.\-]/', $domain);
					unset($de[count($de)-1]);
					foreach($de as $j=>$k) {
						if (in_array($k, ['www', 'en', 'et', 'services', 'materiaux', 'sarl', 'pour', 'de']))
							unset($de[$j]);
					}
					var_dump($de);
					$s = NULL;
					foreach($s_list as $s) {
						$ok = true;
						foreach($de as $p) {
							//$q = explode('-');
							if (!is_numeric(strpos(strtolower($s['nom']), strtolower($p)))) {
								$ok = false;
								continue;
							}
						}
						if ($ok) {
							break;
						}
					}
					if (!$ok) foreach($s_list as $s) {
						$ok = true;
						foreach($s['namee'] as $p) {
							//$q = explode('-');
							if (!is_numeric(strpos(strtolower($domain), strtolower($p)))) {
								$ok = false;
								continue;
							}
						}
						if ($ok) {
							break;
						}
						//var_dump($se);
					}
					// Trouvé
					if ($ok) {
						var_dump($s, $r[3]);
						$s_id = $s['rowid'];
						$sql = 'UPDATE `'.MAIN_DB_PREFIX.'societe`
							SET `url`="'.$r[3]['scheme'].'://'.$domain.'"
							WHERE rowid='.$s['rowid'];
						echo '<pre>'.$sql.'</pre>';
						$db->query($sql);
					}
					// A créer
					else {
						$sql = 'INSERT INTO `'.MAIN_DB_PREFIX.'societe`
							(`nom`, `url`, `fournisseur`)
							VALUES ("'.$domain.'", "'.$r[3]['scheme'].'://'.$domain.'", 1)';
						echo '<pre>'.$sql.'</pre>';
						$db->query($sql);
						$s_id = $db->last_insert_id(MAIN_DB_PREFIX.'societe');
						//var_dump($s_id); die();
					}
					//var_dump($de);
				}
				$domains[$s_id] = $domain;
			}
			else {
				$s_id = array_search($domain, $domains);
			}
			
			$sql = 'SELECT 1
				FROM `'.MAIN_DB_PREFIX.'product_competitor`
				WHERE url="'.$r[1].'"';
			echo '<pre>'.$sql.'</pre>';
			$q = $db->query($sql);
			if (empty($q->fetch_row())) {
				$sql = 'INSERT INTO `'.MAIN_DB_PREFIX.'product_competitor`
					(`fk_product`, `fk_soc`, `url`)
					VALUES ('.$id.', '.$s_id.', "'.$r[1].'")';
				echo '<pre>'.$sql.'</pre>';
				$db->query($sql);
				$url_id = $db->last_insert_id(MAIN_DB_PREFIX.'product_competitor');
				var_dump($url_id);
			}
			$sql = 'SELECT 1
				FROM `'.MAIN_DB_PREFIX.'product_competitor_price`
				WHERE `fk_product` = '.$id.'
					AND `fk_soc` = '.$s_id.'
					AND `date` = "'.$r[0].'"
					AND `price` = "'.$r[2].'"
					AND `qte` = 1';
			echo '<pre>'.$sql.'</pre>';
			$q = $db->query($sql);
			if (empty($q->fetch_row())) {
				$sql = 'INSERT INTO `'.MAIN_DB_PREFIX.'product_competitor_price`
					(`fk_product`, `fk_soc`, `price`, `date`, `qte`)
					VALUES ('.$id.', '.$s_id.', "'.$r[2].'", "'.$r[0].'", 1)';
				echo '<pre>'.$sql.'</pre>';
				$db->query($sql);
			}
		}
		//var_dump($r); die();
		$l[$n]['d'][$i] = $r;
	}
}
sort($domains);
var_dump($n, count($domains));
//var_dump($domains);

foreach($domains as $domain) {
	if (!isset($s_list_url[$domain])) {
		// création
	}
}