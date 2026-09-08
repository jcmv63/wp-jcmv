<?php
/**
 * Liens sortants de la fiche événement : document et itinéraire.
 *
 * Deux liens de la fiche sortaient tels que The Events Calendar les fabrique,
 * et aucun des deux ne disait ce qu'il faisait.
 *
 * LE DOCUMENT. Le champ « Site de l'événement » (`_EventURL`) sert au club à
 * publier la convocation d'une compétition. Faute de libellé,
 * `tribe_get_event_website_link()` retombe sur l'URL elle-même — la ligne
 * `$label = empty( $label ) ? $url : $label` de src/functions/template-tags/link.php —
 * et la fiche affichait quatre-vingt-dix caractères d'adresse cassés sur six
 * lignes dans une colonne étroite. Illisible à l'œil, et pire au lecteur
 * d'écran, qui épelle l'URL segment par segment.
 *
 * Le champ reste générique pour autant : rien n'oblige à y mettre un PDF, et
 * écrire « Convocation » en dur mentirait au premier événement qui y met le
 * site du club organisateur. Le libellé se déduit donc de l'extension, seule
 * information dont on dispose réellement : un `.pdf` est un document, tout le
 * reste est un site. On ne cherche pas à deviner qu'un PDF est une convocation
 * plutôt qu'un règlement ou un plan d'accès — on n'en sait rien, et une
 * étiquette fausse coûte plus cher qu'une étiquette vague.
 *
 * L'intitulé de ligne de TEC (« Site : ») est supprimé plutôt que traduit :
 * il ferait doublon avec un lien qui se nomme déjà. Le gabarit ne rend
 * l'intitulé que s'il n'est pas vide, renvoyer '' suffit donc à l'effacer.
 * Pour le rétablir un jour, renvoyer le libellé voulu depuis website_title().
 *
 * Une réserve à connaître : ce filtre n'existe que dans le gabarit classique
 * (`views/modules/meta/details.php`), celui que produisent les événements
 * importés en CSV, donc toutes les compétitions. Le gabarit de l'éditeur de
 * blocs (`views/blocks/parts/details.php`) écrit « Website: » en dur, sans
 * filtre : un événement composé à la main dans l'éditeur affichera donc
 * « Site : Document (PDF) ». Redondant, pas faux. Si le cas devenait courant,
 * un filtre `gettext` sur cette chaîne le réglerait.
 *
 * L'ITINÉRAIRE existait déjà : `tribe_get_map_link_html()` produit un simple
 * lien sortant, sans iframe ni script tiers — c'est le réglage « Afficher le
 * lien Google Maps », indépendant de « Intégrer une carte », que le club ne
 * veut pas. Restaient deux défauts : le libellé « + Google Map », daté et
 * muet sur ce qu'il fait, et une URL de RECHERCHE (`maps.google.com/maps?q=`)
 * là où le visiteur veut un itinéraire. Les deux se corrigent par filtre,
 * sans copier le gabarit du lieu.
 *
 * Aucun gabarit copié, aucune classe de TEC visée : quatre filtres publics et
 * notre propre balisage pour le lien d'itinéraire. Vérifié sur TEC 6.17.1.
 *
 * @package wp-jcmv
 */

namespace JCMV\Front;

use WP_HTML_Tag_Processor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EventLinks {

	/** Racine d'une URL d'itinéraire Google Maps (API « URLs », sans clé). */
	private const DIRECTIONS_ENDPOINT = 'https://www.google.com/maps/dir/?api=1&destination=';

	public static function register(): void {
		add_filter( 'tribe_events_get_event_website_title', array( self::class, 'website_title' ), 10, 2 );
		add_filter( 'tribe_get_event_website_link_label', array( self::class, 'website_label' ), 10, 2 );
		add_filter( 'tribe_events_google_map_link', array( self::class, 'directions_url' ), 10, 2 );
		add_filter( 'tribe_get_map_link_html', array( self::class, 'directions_html' ) );
	}

	/**
	 * Supprime l'intitulé « Site : » de la ligne : le lien se nomme lui-même.
	 *
	 * @param string   $title   Intitulé proposé par TEC.
	 * @param int|null $post_id Événement (inutilisé, conservé pour la signature).
	 * @return string
	 */
	public static function website_title( $title, $post_id = null ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return '';
	}

	/**
	 * Texte du lien, déduit de l'extension de l'URL saisie.
	 *
	 * @param string|null $label   Libellé proposé (vide : TEC y mettra l'URL).
	 * @param int|null    $post_id Événement.
	 * @return string
	 */
	public static function website_label( $label, $post_id = null ): string {
		$url   = (string) tribe_get_event_website_url( $post_id );
		$label = (string) $label;

		// Attention à l'ordre des opérations dans TEC : la substitution
		// `$label = empty( $label ) ? $url : $label` a lieu AVANT ce filtre
		// (src/functions/template-tags/link.php). Le libellé reçu n'est donc
		// jamais vide dès qu'il y a une URL — tester `empty()` ici revenait à
		// ne rien corriger du tout, et la fiche affichait toujours l'adresse
		// en toutes lettres. Le repère fiable est l'égalité avec l'URL : c'est
		// la signature du repli de TEC, et un libellé volontaire ne vaut jamais
		// exactement l'adresse qu'il désigne.
		if ( '' !== $label && $label !== $url ) {
			return $label;
		}

		return self::is_pdf( $url )
			? __( 'Document (PDF)', 'wp-jcmv' )
			: __( 'Site de l\'événement', 'wp-jcmv' );
	}

	/**
	 * Remplace l'URL de recherche de TEC par une URL d'itinéraire.
	 *
	 * L'adresse est reconstruite à partir des champs du lieu, comme le fait
	 * TEC, à une différence près : la région est écartée. Pour une adresse
	 * française elle n'aide pas le géocodage et ajoute du bruit dans la requête.
	 *
	 * Si le lieu n'a pas d'adresse exploitable, on rend l'URL de TEC telle
	 * quelle — qui vaut '' dans ce cas, et le gabarit n'affiche alors rien.
	 *
	 * @param string   $url     URL construite par TEC.
	 * @param int|null $post_id Événement (les helpers d'adresse résolvent son lieu).
	 * @return string
	 */
	public static function directions_url( $url, $post_id = null ): string {
		$destination = self::destination( $post_id );

		if ( '' === $destination ) {
			return (string) $url;
		}

		return self::DIRECTIONS_ENDPOINT . rawurlencode( $destination );
	}

	/**
	 * Réécrit le lien « + Google Map » avec notre libellé et notre classe.
	 *
	 * L'adresse de destination est relue dans le HTML que TEC vient de produire
	 * plutôt que recalculée : ce filtre ne reçoit pas l'identifiant de
	 * l'événement, et repasser par le `$post` global supposerait qu'on est bien
	 * dans la boucle de la fiche — ce que rien ne garantit.
	 *
	 * @param string $html Lien complet produit par TEC ('' si pas d'adresse).
	 * @return string
	 */
	public static function directions_html( $html ): string {
		$html = (string) $html;

		if ( '' === $html ) {
			return $html;
		}

		$tags = new WP_HTML_Tag_Processor( $html );

		if ( ! $tags->next_tag( 'A' ) ) {
			return $html;
		}

		$href = (string) $tags->get_attribute( 'href' );

		if ( '' === $href ) {
			return $html;
		}

		// `title` plutôt que rien : le lien s'ouvre dans un nouvel onglet — pour
		// ne pas faire perdre la fiche à qui consulte l'itinéraire — et RGAA 13.2
		// demande que l'ouverture soit annoncée. TEC posait déjà un `title` sur
		// ce lien, on garde le procédé en changeant ce qu'il dit.
		return sprintf(
			'<a class="jcmv-event-directions" href="%s" target="_blank" rel="noopener noreferrer" title="%s">%s</a>',
			esc_url( $href ),
			esc_attr__( 'Ouvrir l\'itinéraire dans une nouvelle fenêtre', 'wp-jcmv' ),
			esc_html__( 'Ouvrir l\'itinéraire', 'wp-jcmv' )
		);
	}

	/**
	 * L'URL pointe-t-elle vers un PDF ?
	 *
	 * L'extension est lue dans le CHEMIN et non dans l'URL entière : une chaîne
	 * de requête (`?v=2`, un jeton de téléchargement) ne doit pas la masquer.
	 *
	 * @param string $url URL saisie dans le champ « Site de l'événement ».
	 */
	private static function is_pdf( string $url ): bool {
		if ( '' === $url ) {
			return false;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		return 'pdf' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	}

	/**
	 * Adresse du lieu de l'événement, en une ligne, prête à être encodée.
	 *
	 * Les champs de TEC sortent DÉJÀ échappés pour le HTML : `tribe_get_address()`
	 * et ses voisines renvoient `esc_html( … )`. Une rue « d'Alsace » arrive donc
	 * ici en « d&#039;Alsace », et l'encoder tel quel enverrait le visiteur
	 * chercher cette chaîne littérale sur la carte. On décode avant d'encoder :
	 * `rawurlencode()` attend du texte, pas du HTML. Même remarque pour une
	 * esperluette dans un nom de salle, qui arriverait en « &amp; ».
	 *
	 * Le lieu peut être donné par l'identifiant de l'événement ou par celui du
	 * lieu lui-même : les helpers de TEC passent par `tribe_get_venue_id()`, qui
	 * accepte les deux. Le gabarit classique leur donne l'événement, celui de
	 * l'éditeur de blocs le lieu — les deux marchent sans distinction ici.
	 *
	 * @param int|null $post_id Événement ou lieu.
	 */
	private static function destination( $post_id ): string {
		$parts = array(
			tribe_get_address( $post_id ),
			tribe_get_city( $post_id ),
			tribe_get_zip( $post_id ),
			tribe_get_country( $post_id ),
		);

		$parts = array_map(
			static fn ( $part ): string => trim( wp_specialchars_decode( (string) $part, ENT_QUOTES ) ),
			$parts
		);

		return implode( ', ', array_filter( $parts ) );
	}
}
