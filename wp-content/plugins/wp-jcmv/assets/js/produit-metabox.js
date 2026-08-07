/**
 * Metabox produit (ADR-005) : lignes tarifaires répétables et sélection de
 * photos complémentaires.
 *
 * Vanilla, sans build, comme les scripts d'éditeur des blocs du plugin.
 * jQuery n'est en dépendance que pour wp.media, qui l'exige.
 *
 * Aucun index n'est réutilisé après suppression : les noms de champs sont
 * renumérotés à chaque mutation. PHP reçoit donc toujours une suite continue,
 * et l'ordre du DOM devient l'ordre d'affichage sur le site.
 */
( function () {
	'use strict';

	var config = window.jcmvProduit || {};

	document.addEventListener( 'DOMContentLoaded', function () {
		initTarifs();
		initGalerie();
	} );

	/* --- Grille tarifaire ------------------------------------------------ */

	function initTarifs() {
		var tbody = document.getElementById( 'jcmv-tarifs-lignes' );
		var ajouter = document.getElementById( 'jcmv-tarifs-ajouter' );
		var gabarit = document.getElementById( 'jcmv-tarifs-gabarit' );

		if ( ! tbody || ! ajouter || ! gabarit ) {
			return;
		}

		ajouter.addEventListener( 'click', function () {
			var ligne = gabarit.content.firstElementChild.cloneNode( true );
			tbody.appendChild( ligne );
			renumeroter( tbody );

			var champ = ligne.querySelector( 'input[type="text"]' );
			if ( champ ) {
				champ.focus();
			}
		} );

		// Délégation : les lignes ajoutées après coup sont couvertes sans
		// avoir à recâbler quoi que ce soit.
		tbody.addEventListener( 'click', function ( event ) {
			var bouton = event.target.closest( '.jcmv-tarifs__supprimer' );
			if ( ! bouton ) {
				return;
			}

			var ligne = bouton.closest( '.jcmv-tarifs__ligne' );
			if ( ! ligne ) {
				return;
			}

			// Le focus doit survivre à la suppression, sinon il retombe sur
			// <body> et la navigation au clavier repart du haut de la page.
			var suivant = ligne.nextElementSibling || ligne.previousElementSibling;

			ligne.remove();
			renumeroter( tbody );

			var cible = suivant
				? suivant.querySelector( '.jcmv-tarifs__supprimer' )
				: ajouter;
			if ( cible ) {
				cible.focus();
			}
		} );
	}

	function renumeroter( tbody ) {
		var lignes = tbody.querySelectorAll( '.jcmv-tarifs__ligne' );

		Array.prototype.forEach.call( lignes, function ( ligne, index ) {
			Array.prototype.forEach.call(
				ligne.querySelectorAll( 'input[name]' ),
				function ( input ) {
					input.name = input.name.replace(
						/jcmv_tarif\[[^\]]*\]/,
						'jcmv_tarif[' + index + ']'
					);
				}
			);
		} );
	}

	/* --- Galerie --------------------------------------------------------- */

	function initGalerie() {
		var zone = document.getElementById( 'jcmv-galerie' );
		var liste = document.getElementById( 'jcmv-galerie-liste' );
		var champ = document.getElementById( 'jcmv-galerie-ids' );
		var choisir = document.getElementById( 'jcmv-galerie-choisir' );

		if ( ! zone || ! liste || ! champ || ! choisir || ! window.wp || ! window.wp.media ) {
			return;
		}

		var max = parseInt( zone.dataset.max, 10 ) || 3;
		var cadre = null;

		choisir.addEventListener( 'click', function () {
			if ( ! cadre ) {
				cadre = window.wp.media( {
					title: config.mediaTitle || 'Photos du produit',
					button: { text: config.mediaButton || 'Utiliser ces photos' },
					library: { type: 'image' },
					multiple: 'add',
				} );

				cadre.on( 'select', function () {
					appliquer( cadre.state().get( 'selection' ).toJSON() );
				} );
			}

			// Présélectionner ce qui est déjà retenu évite au bureau de tout
			// resélectionner pour ajouter une seule photo.
			cadre.on( 'open', function () {
				var selection = cadre.state().get( 'selection' );
				selection.reset();
				ids().forEach( function ( id ) {
					var attachment = window.wp.media.attachment( id );
					attachment.fetch();
					selection.add( attachment );
				} );
			} );

			cadre.open();
		} );

		liste.addEventListener( 'click', function ( event ) {
			var bouton = event.target.closest( '.jcmv-galerie__retirer' );
			if ( ! bouton ) {
				return;
			}

			var item = bouton.closest( '.jcmv-galerie__item' );
			if ( item ) {
				item.remove();
				synchroniser();
				choisir.focus();
			}
		} );

		function ids() {
			return champ.value
				.split( ',' )
				.map( function ( id ) {
					return parseInt( id, 10 );
				} )
				.filter( function ( id ) {
					return id > 0;
				} );
		}

		function appliquer( attachments ) {
			liste.innerHTML = '';

			attachments.slice( 0, max ).forEach( function ( attachment ) {
				var url =
					( attachment.sizes &&
						attachment.sizes.thumbnail &&
						attachment.sizes.thumbnail.url ) ||
					attachment.url;

				var item = document.createElement( 'li' );
				item.className = 'jcmv-galerie__item';
				item.dataset.id = attachment.id;

				var img = document.createElement( 'img' );
				img.src = url;
				// Vignette d'administration purement décorative : le texte
				// alternatif du site vient du titre du produit, pas d'ici.
				img.alt = '';
				item.appendChild( img );

				var retirer = document.createElement( 'button' );
				retirer.type = 'button';
				retirer.className = 'button-link jcmv-galerie__retirer';
				retirer.textContent = 'Retirer';
				item.appendChild( retirer );

				liste.appendChild( item );
			} );

			synchroniser();
		}

		function synchroniser() {
			var items = liste.querySelectorAll( '.jcmv-galerie__item' );

			champ.value = Array.prototype.map
				.call( items, function ( item ) {
					return item.dataset.id;
				} )
				.join( ',' );

			choisir.disabled = false;
		}
	}
} )();
