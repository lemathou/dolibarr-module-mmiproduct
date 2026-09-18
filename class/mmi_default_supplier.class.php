<?php
/**
 * Copyright (C) 2026       MMI Mathieu Moulin      <contact@iprospective.fr>
 */

/**
 * Fournisseur par défaut d'un produit (extrafields fk_soc_fournisseur, supplier_ref,
 * supplier_packaging) — RM3233.
 *
 * Règle : le fournisseur par défaut ne change pas, sauf s'il n'a plus aucun prix
 * d'achat (actif) pour le produit, ou s'il n'est pas renseigné.
 * - création/modification d'une ligne : si le défaut est vide ou sans prix, la ligne
 *   devient le défaut ; si la ligne appartient au défaut, on rafraîchit réf et
 *   conditionnement ; sinon rien.
 * - suppression : si le défaut n'a plus de prix, le remplaçant est le prix le plus
 *   récent (tms, puis rowid) ; s'il n'en reste aucun, le défaut est vidé. Ces deux
 *   cas sont notifiés (événement agenda + email).
 *
 * La même règle est reproduite côté dpsync (RM3232) : la garder identique.
 */
class mmi_default_supplier
{
	const REASON_KEEP = 'keep';
	const REASON_REFRESH = 'refresh';
	const REASON_INIT = 'init';
	const REASON_REPLACE = 'replace';
	const REASON_REPLACE_RECENT = 'replace_recent';
	const REASON_EMPTY = 'empty';

	/**
	 * Calcule le nouveau fournisseur par défaut. Fonction pure (aucune I/O).
	 *
	 * @param array      $state  état actuel du produit : ['fk_soc'=>?int, 'ref'=>?string, 'packaging'=>?mixed]
	 * @param array      $lines  prix d'achat du produit APRÈS l'événement (ligne supprimée exclue) :
	 *                           liste de ['id'=>int, 'fk_soc'=>int, 'ref'=>?string, 'packaging'=>?mixed, 'tms'=>?string, 'status'=>?int]
	 * @param array|null $line   ligne créée ou modifiée (même format), null pour une suppression
	 * @return array ['state'=>nouvel état, 'changed'=>bool, 'reason'=>string, 'notify'=>bool]
	 */
	public static function resolve(array $state, array $lines, $line = null)
	{
		$state = array_merge(['fk_soc'=>null, 'ref'=>null, 'packaging'=>null], $state);
		$current = (int) $state['fk_soc'];

		$lines = array_values(array_filter($lines, function ($l) {
			return !isset($l['status']) || $l['status'] === null || (int) $l['status'] !== 0;
		}));
		if ($line !== null && isset($line['status']) && $line['status'] !== null && (int) $line['status'] === 0)
			$line = null;

		$current_lines = array_values(array_filter($lines, function ($l) use ($current) {
			return $current > 0 && (int) $l['fk_soc'] === $current;
		}));

		// Le défaut a encore au moins un prix : il reste le défaut
		if (!empty($current_lines)) {
			$source = null;
			if ($line !== null && (int) $line['fk_soc'] === $current)
				$source = $line;
			// La réf du produit ne correspond plus à aucune ligne du défaut (ligne supprimée ou réf changée ailleurs)
			elseif (!in_array((string) $state['ref'], array_map(function ($l) { return (string) $l['ref']; }, $current_lines), true))
				$source = static::most_recent($current_lines);

			if ($source === null)
				return static::_result($state, $state, self::REASON_KEEP, false);

			$new = ['fk_soc'=>$current, 'ref'=>$source['ref'], 'packaging'=>$source['packaging']];
			return static::_result($state, $new, self::REASON_REFRESH, false);
		}

		// Le défaut est vide ou n'a plus de prix
		if ($line !== null) {
			$new = ['fk_soc'=>(int) $line['fk_soc'], 'ref'=>$line['ref'], 'packaging'=>$line['packaging']];
			return static::_result($state, $new, $current > 0 ? self::REASON_REPLACE : self::REASON_INIT, false);
		}

		$recent = static::most_recent($lines);
		if ($recent !== null) {
			$new = ['fk_soc'=>(int) $recent['fk_soc'], 'ref'=>$recent['ref'], 'packaging'=>$recent['packaging']];
			return static::_result($state, $new, $current > 0 ? self::REASON_REPLACE_RECENT : self::REASON_INIT, $current > 0);
		}

		$new = ['fk_soc'=>null, 'ref'=>null, 'packaging'=>null];
		return static::_result($state, $new, self::REASON_EMPTY, $current > 0);
	}

	/**
	 * Prix le plus récent : tms le plus grand, puis rowid le plus grand.
	 *
	 * @param array $lines
	 * @return array|null
	 */
	public static function most_recent(array $lines)
	{
		$best = null;
		foreach ($lines as $l) {
			if ($best === null
				|| strcmp((string) $l['tms'], (string) $best['tms']) > 0
				|| ((string) $l['tms'] === (string) $best['tms'] && (int) $l['id'] > (int) $best['id']))
				$best = $l;
		}
		return $best;
	}

	protected static function _result(array $old, array $new, $reason, $notify)
	{
		$changed = (int) $old['fk_soc'] !== (int) $new['fk_soc']
			|| (string) $old['ref'] !== (string) $new['ref']
			|| (string) $old['packaging'] !== (string) $new['packaging'];
		return ['state'=>$new, 'changed'=>$changed, 'reason'=>$reason, 'notify'=>$notify && $changed];
	}

	// -- I/O --

	/**
	 * Prix d'achat d'un produit, au format attendu par resolve().
	 *
	 * @param DoliDB $db
	 * @param int    $fk_product
	 * @param int    $exclude_id  ligne à exclure (suppression en cours : le trigger DELETE passe avant le DELETE SQL)
	 * @return array
	 */
	public static function fetch_lines($db, $fk_product, $exclude_id = 0)
	{
		$sql = 'SELECT rowid, fk_soc, ref_fourn, packaging, tms, status
			FROM '.MAIN_DB_PREFIX.'product_fournisseur_price
			WHERE fk_product='.((int) $fk_product)
			.($exclude_id > 0 ? ' AND rowid<>'.((int) $exclude_id) : '');
		$lines = [];
		$resql = $db->query($sql);
		if (!$resql)
			return $lines;
		while ($obj = $db->fetch_object($resql))
			$lines[] = static::_line($obj);
		return $lines;
	}

	/**
	 * @param DoliDB $db
	 * @param int    $id  rowid product_fournisseur_price
	 * @return array|null ligne + 'fk_product'
	 */
	public static function fetch_line($db, $id)
	{
		$sql = 'SELECT rowid, fk_product, fk_soc, ref_fourn, packaging, tms, status
			FROM '.MAIN_DB_PREFIX.'product_fournisseur_price
			WHERE rowid='.((int) $id);
		$resql = $db->query($sql);
		if (!$resql || !($obj = $db->fetch_object($resql)))
			return null;
		return static::_line($obj) + ['fk_product'=>(int) $obj->fk_product];
	}

	protected static function _line($obj)
	{
		return [
			'id'=>(int) $obj->rowid,
			'fk_soc'=>(int) $obj->fk_soc,
			'ref'=>$obj->ref_fourn,
			'packaging'=>$obj->packaging,
			'tms'=>$obj->tms,
			'status'=>$obj->status,
		];
	}

	/**
	 * Applique la règle à un produit après un événement sur un prix d'achat.
	 *
	 * @param DoliDB  $db
	 * @param User    $user
	 * @param int     $pfp_id     ligne concernée
	 * @param bool    $is_delete
	 * @return int <0 erreur, 0 rien à faire, >0 produit mis à jour
	 */
	public static function apply($db, $user, $pfp_id, $is_delete)
	{
		$row = static::fetch_line($db, $pfp_id);
		if (empty($row) || empty($row['fk_product']))
			return 0;

		$product = new Product($db);
		if ($product->fetch($row['fk_product']) <= 0)
			return -1;

		$state = [
			'fk_soc'=>$product->array_options['options_fk_soc_fournisseur'] ?? null,
			'ref'=>$product->array_options['options_supplier_ref'] ?? null,
			'packaging'=>$product->array_options['options_supplier_packaging'] ?? null,
		];
		$lines = static::fetch_lines($db, $product->id, $is_delete ? $pfp_id : 0);
		$res = static::resolve($state, $lines, $is_delete ? null : $row);
		if (!$res['changed'])
			return 0;

		$product->array_options['options_fk_soc_fournisseur'] = $res['state']['fk_soc'];
		$product->array_options['options_supplier_ref'] = $res['state']['ref'];
		$product->array_options['options_supplier_packaging'] = $res['state']['packaging'];
		// update() et non insertExtraFields() : PRODUCT_MODIFY notifie la synchro Presta
		if ($product->update($product->id, $user) < 0)
			return -1;

		if ($res['notify'])
			static::notify($db, $user, $product, $state, $res);

		return 1;
	}

	/**
	 * Notification d'un changement subi (suppression) : événement agenda + email.
	 */
	public static function notify($db, $user, $product, array $old, array $res)
	{
		global $conf, $langs;

		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

		$names = [];
		foreach ([$old['fk_soc'], $res['state']['fk_soc']] as $id) {
			if ($id > 0 && !isset($names[$id])) {
				$soc = new Societe($db);
				$names[$id] = $soc->fetch($id) > 0 ? $soc->name : '#'.$id;
			}
		}
		$old_name = $old['fk_soc'] > 0 ? $names[$old['fk_soc']] : '-';
		$new_name = $res['state']['fk_soc'] > 0 ? $names[$res['state']['fk_soc']] : '';

		$label = $new_name !== ''
			? 'Fournisseur par défaut remplacé : '.$old_name.' → '.$new_name
			: 'Fournisseur par défaut retiré : '.$old_name.' (plus aucun prix d\'achat)';
		$text = 'Produit '.$product->ref.' - '.$product->label."\n"
			.'Le dernier prix d\'achat du fournisseur par défaut ('.$old_name.') a été supprimé.'."\n"
			.($new_name !== ''
				? 'Nouveau fournisseur par défaut (prix le plus récent) : '.$new_name.' (réf. '.$res['state']['ref'].').'
				: 'Il ne reste aucun prix d\'achat : le produit n\'a plus de fournisseur par défaut.')."\n"
			.'Utilisateur : '.$user->login;

		$ac = new ActionComm($db);
		$ac->type_code = 'AC_OTH_AUTO';
		$ac->code = 'AC_MMI_DEFAULT_SUPPLIER';
		$ac->label = $label;
		$ac->note_private = $text;
		$ac->datep = dol_now();
		$ac->percentage = -1;
		$ac->userownerid = $user->id;
		$ac->elementtype = 'product';
		$ac->fk_element = $product->id;
		$ac->create($user);

		$to = getDolGlobalString('MMIPRODUCT_DEFAULT_SUPPLIER_ALERT_EMAIL_TO', getDolGlobalString('MMIPRODUCT_ALERT_EMAIL_TO'));
		if (empty($to))
			return;
		$from = getDolGlobalString('MMIPRODUCT_ALERT_EMAIL_FROM', getDolGlobalString('MAIN_MAIL_EMAIL_FROM'));
		$url = DOL_MAIN_URL_ROOT.'/product/card.php?id='.$product->id;
		$mail = new CMailFile('[Dolibarr] '.$label.' — '.$product->ref, $to, $from, $text."\n".$url);
		$mail->sendfile();
	}
}
