<?php
/**
 * Colonnes de liens du pied de page.
 *
 * ADR-001 : la donnée n'est ni du contenu administrable (pas de fiche à
 * éditer, pas de médias, pas de statut) ni du relationnel saisonnier (rien à
 * versionner par saison) — c'est du paramétrage d'affichage. Une option
 * autochargée suffit, et évite un CPT ou une table pour neuf lignes.
 *
 * Un lien porte SOIT un `page_id`, SOIT une `url`, jamais les deux. C'est ce
 * qui permet à un lien interne de survivre au renommage d'une page : le
 * permalien est résolu au rendu, pas figé à la saisie.
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FooterLinksRepository {

	public const OPTION = 'jcmv_footer_liens';

	/**
	 * Trois colonnes, et ce n'est pas un réglage : les `flex-basis` du pied de
	 * page (components.css) décident de la rupture en fonction de ce nombre.
	 * En faire un paramètre serait promettre une souplesse que le CSS n'a pas.
	 */
	public const COLUMNS = 3;

	/** Garde-fou de saisie : au-delà, ce n'est plus un pied de page. */
	public const MAX_LINKS = 10;

	/** Protocoles acceptés dans le champ « URL externe ». */
	private const PROTOCOLS = array( 'http', 'https', 'mailto', 'tel' );

	/**
	 * Colonnes prêtes à l'affichage : URL résolues, liens morts écartés.
	 *
	 * @return array<int, array{titre:string, liens:array<int, array{label:string, url:string, externe:bool}>}>
	 */
	public function all(): array {
		$columns = array();

		foreach ( $this->raw() as $column ) {
			$links = array();

			foreach ( $column['liens'] as $link ) {
				$url = $this->resolve( $link );

				if ( '' === $url ) {
					continue;
				}

				$links[] = array(
					'label'   => $link['label'],
					'url'     => $url,
					'externe' => $this->is_external( $url ),
				);
			}

			$columns[] = array(
				'titre' => $column['titre'],
				'liens' => $links,
			);
		}

		return $columns;
	}

	/**
	 * Forme stockée, normalisée : exactement trois colonnes, chaque lien avec
	 * ses trois clés. C'est ce que consomme le formulaire d'administration.
	 *
	 * @return array<int, array{titre:string, liens:array<int, array{label:string, page_id:int, url:string}>}>
	 */
	public function raw(): array {
		$stored = get_option( self::OPTION );

		/*
		 * Option absente : le plugin est déjà activé en production, le seed
		 * ne rejouera pas. Les valeurs d'origine vivent donc ici, en repli de
		 * lecture — le pied de page reste identique à ce qu'il était en dur
		 * tant que le bureau n'a rien enregistré, et la première sauvegarde
		 * matérialise l'option.
		 */
		if ( ! is_array( $stored ) || ! $stored ) {
			$stored = self::defaults();
		}

		return $this->normalize( $stored );
	}

	/**
	 * Assainit et enregistre. Une ligne sans libellé ou sans cible est
	 * supprimée : le formulaire rend toujours des lignes vides en réserve, il
	 * ne faut pas qu'elles se stockent.
	 *
	 * @param array $input Données brutes du formulaire, déjà passées à wp_unslash().
	 */
	public function save( array $input ): void {
		$clean = array();

		for ( $index = 0; $index < self::COLUMNS; $index++ ) {
			$column = ( isset( $input[ $index ] ) && is_array( $input[ $index ] ) ) ? $input[ $index ] : array();
			$source = ( isset( $column['liens'] ) && is_array( $column['liens'] ) ) ? $column['liens'] : array();
			$links  = array();

			foreach ( $source as $link ) {
				if ( ! is_array( $link ) ) {
					continue;
				}

				$label   = sanitize_text_field( (string) ( $link['label'] ?? '' ) );
				$page_id = absint( $link['page_id'] ?? 0 );
				$url     = esc_url_raw( trim( (string) ( $link['url'] ?? '' ) ), self::PROTOCOLS );

				// Les deux champs cohabitent dans le formulaire, jamais dans
				// la donnée : la page choisie l'emporte.
				if ( $page_id > 0 ) {
					$url = '';
				}

				if ( '' === $label || ( 0 === $page_id && '' === $url ) ) {
					continue;
				}

				$links[] = array(
					'label'   => $label,
					'page_id' => $page_id,
					'url'     => $url,
				);

				if ( count( $links ) >= self::MAX_LINKS ) {
					break;
				}
			}

			$clean[] = array(
				'titre' => sanitize_text_field( (string) ( $column['titre'] ?? '' ) ),
				'liens' => $links,
			);
		}

		// Autochargée : lue à chaque page du site, jamais dans une boucle.
		update_option( self::OPTION, $clean, true );
	}

	/**
	 * Le pied de page tel qu'il était écrit en dur dans le thème.
	 *
	 * Les cibles internes sont résolues en `page_id` quand la page existe :
	 * l'état par défaut bénéficie ainsi déjà de la résistance au changement de
	 * slug, sans que le bureau ait à ouvrir l'écran.
	 *
	 * @return array<int, array{titre:string, liens:array<int, array{label:string, page_id:int, url:string}>}>
	 */
	public static function defaults(): array {
		return array(
			array(
				'titre' => __( 'Le club', 'wp-jcmv' ),
				'liens' => array(
					self::internal( 'le-club', __( 'À propos', 'wp-jcmv' ) ),
					self::internal( 'lequipe', __( 'L\'équipe', 'wp-jcmv' ) ),
					self::internal( 'partenaires', __( 'Les partenaires', 'wp-jcmv' ) ),
				),
			),
			array(
				'titre' => __( 'Infos pratiques', 'wp-jcmv' ),
				'liens' => array(
					self::internal( 'horaires-tarifs', __( 'Horaires & tarifs', 'wp-jcmv' ) ),
					self::internal( 'contact', __( 'Contact', 'wp-jcmv' ) ),
					self::internal( 'mentions-legales', __( 'Mentions légales', 'wp-jcmv' ) ),
				),
			),
			array(
				'titre' => __( 'Réseau Judo', 'wp-jcmv' ),
				'liens' => array(
					self::external( 'https://www.ffjudo.com/', 'FFJDA' ),
					self::external( 'https://www.aurajudo.com/', __( 'Ligue AURA', 'wp-jcmv' ) ),
					self::external( 'https://puy-de-dome-judo.ffjudo.com/', __( 'Comité 63', 'wp-jcmv' ) ),
				),
			),
		);
	}

	/**
	 * URL d'affichage d'un lien, ou chaîne vide s'il n'est plus affichable.
	 *
	 * @param array{label:string, page_id:int, url:string} $link Lien stocké.
	 */
	private function resolve( array $link ): string {
		if ( $link['page_id'] > 0 ) {
			// Une page dépubliée ou supprimée ne laisse pas un lien mort dans
			// le pied de page : elle disparaît de la colonne. Un trou vaut
			// mieux qu'un 404 sur toutes les pages du site.
			if ( 'publish' !== get_post_status( $link['page_id'] ) ) {
				return '';
			}

			return (string) get_permalink( $link['page_id'] );
		}

		return $link['url'];
	}

	/**
	 * Un lien est externe s'il porte un hôte différent de celui du site.
	 *
	 * De cette réponse découlent `target="_blank"`, `rel="noopener"` et la
	 * mention « (nouvelle fenêtre) » à l'affichage : ce n'est pas une case à
	 * cocher, c'est une conséquence.
	 */
	private function is_external( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		// Chemin relatif, mailto:, tel: — on ne quitte pas la fenêtre.
		if ( ! $host ) {
			return false;
		}

		$site = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return strtolower( (string) $host ) !== strtolower( $site );
	}

	/**
	 * Lien interne par défaut : `page_id` si la page existe et est publiée,
	 * sinon le chemin, qui reste juste tant que la page n'a pas bougé.
	 *
	 * @return array{label:string, page_id:int, url:string}
	 */
	private static function internal( string $slug, string $label ): array {
		$page      = get_page_by_path( $slug );
		$published = $page instanceof \WP_Post && 'publish' === $page->post_status;

		return array(
			'label'   => $label,
			'page_id' => $published ? (int) $page->ID : 0,
			'url'     => $published ? '' : '/' . $slug,
		);
	}

	/**
	 * @return array{label:string, page_id:int, url:string}
	 */
	private static function external( string $url, string $label ): array {
		return array(
			'label'   => $label,
			'page_id' => 0,
			'url'     => $url,
		);
	}

	/**
	 * Ramène n'importe quelle donnée stockée à la forme attendue : trois
	 * colonnes, des liens complets. Défensif par construction — l'option peut
	 * avoir été écrite par une version antérieure ou à la main en base.
	 *
	 * @param array $columns Données brutes.
	 * @return array<int, array{titre:string, liens:array<int, array{label:string, page_id:int, url:string}>}>
	 */
	private function normalize( array $columns ): array {
		$out = array();

		for ( $index = 0; $index < self::COLUMNS; $index++ ) {
			$column = ( isset( $columns[ $index ] ) && is_array( $columns[ $index ] ) ) ? $columns[ $index ] : array();
			$source = ( isset( $column['liens'] ) && is_array( $column['liens'] ) ) ? $column['liens'] : array();
			$links  = array();

			foreach ( $source as $link ) {
				if ( ! is_array( $link ) ) {
					continue;
				}

				$links[] = array(
					'label'   => isset( $link['label'] ) ? (string) $link['label'] : '',
					'page_id' => isset( $link['page_id'] ) ? (int) $link['page_id'] : 0,
					'url'     => isset( $link['url'] ) ? (string) $link['url'] : '',
				);

				if ( count( $links ) >= self::MAX_LINKS ) {
					break;
				}
			}

			$out[] = array(
				'titre' => isset( $column['titre'] ) ? (string) $column['titre'] : '',
				'liens' => $links,
			);
		}

		return $out;
	}
}
