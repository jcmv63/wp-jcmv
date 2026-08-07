/**
 * Metabox produit (ADR-005) : cases de tailles et photos complémentaires.
 *
 * Vanilla, sans build, comme les scripts d'éditeur des blocs du plugin.
 * jQuery n'est en dépendance que pour wp.media, qui l'exige.
 *
 * Le serveur rend déjà les cases du système courant : ce script ne sert qu'au
 * changement de système en cours d'édition. Sans lui, la page reste utilisable,
 * la liste des tailles se met simplement à jour à l'enregistrement suivant.
 */
( function () {
	'use strict';

	var config = window.jcmvProduit || {};
	var i18n = config.i18n || {};

	document.addEventListener( 'DOMContentLoaded', function () {
		initTailles();
		initGalerie();
	} );

	/* --- Tailles ---------------------------------------------------------- */

	function initTailles() {
		var zone = document.getElementById( 'jcmv-tailles' );
		var systemes = document.querySelectorAll( '.jcmv-systeme-choix input[type="radio"]' );

		if ( ! zone || ! systemes.length ) {
			return;
		}

		Array.prototype.forEach.call( systemes, function ( radio ) {
			radio.addEventListener( 'change', function () {
				if ( radio.checked ) {
					reconstruire( zone, radio.value );
				}
			} );
		} );
	}

	/**
	 * Reconstruit la liste pour un système, en conservant les tailles déjà
	 * cochées qui n'en font pas partie.
	 *
	 * Sans cette conservation, changer de système effacerait silencieusement
	 * des tailles saisies — le genre de perte qu'on ne remarque qu'après
	 * enregistrement.
	 */
	function reconstruire( zone, termId ) {
		var systemes = config.systemes || {};
		var proposees = systemes[ termId ] || [];
		var cochees = lireCochees( zone );

		var index = proposees.map( minuscule );
		var horsSysteme = cochees.filter( function ( taille ) {
			return -1 === index.indexOf( minuscule( taille ) );
		} );

		zone.innerHTML = '';

		if ( ! proposees.length ) {
			zone.appendChild( aide( termId && '0' !== termId ? i18n.sansTailles : i18n.sansSysteme ) );
		}

		if ( ! proposees.length && ! horsSysteme.length ) {
			return;
		}

		var liste = document.createElement( 'ul' );
		liste.className = 'jcmv-tailles__liste';

		// La pastille « hors système » n'a de sens que s'il y a un système dont
		// être sorti : sur « Aucune », toutes les tailles y tomberaient et
		// seraient signalées pour rien. Même règle que côté serveur.
		var badge = Boolean( termId ) && '0' !== termId;

		proposees.forEach( function ( taille ) {
			liste.appendChild(
				item( taille, -1 !== cochees.map( minuscule ).indexOf( minuscule( taille ) ), false )
			);
		} );

		horsSysteme.forEach( function ( taille ) {
			liste.appendChild( item( taille, true, badge ) );
		} );

		zone.appendChild( liste );
	}

	function lireCochees( zone ) {
		return Array.prototype.map.call(
			zone.querySelectorAll( 'input[type="checkbox"]:checked' ),
			function ( input ) {
				return input.value;
			}
		);
	}

	function item( taille, checked, horsSysteme ) {
		var li = document.createElement( 'li' );
		li.className = 'jcmv-tailles__item' + ( horsSysteme ? ' is-hors-systeme' : '' );

		var label = document.createElement( 'label' );

		var input = document.createElement( 'input' );
		input.type = 'checkbox';
		input.name = 'jcmv_produit_tailles[]';
		input.value = taille;
		input.checked = checked;
		label.appendChild( input );

		var texte = document.createElement( 'span' );
		texte.textContent = taille;
		label.appendChild( texte );

		if ( horsSysteme ) {
			var marque = document.createElement( 'em' );
			marque.className = 'jcmv-tailles__hors';
			marque.textContent = i18n.horsSysteme || 'hors système';
			label.appendChild( marque );
		}

		li.appendChild( label );
		return li;
	}

	function aide( texte ) {
		var p = document.createElement( 'p' );
		p.className = 'description';
		p.textContent = texte || '';
		return p;
	}

	function minuscule( valeur ) {
		return String( valeur ).toLowerCase();
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
					title: i18n.mediaTitle || 'Photos du produit',
					button: { text: i18n.mediaButton || 'Utiliser ces photos' },
					library: { type: 'image' },
					multiple: 'add',
				} );

				cadre.on( 'select', function () {
					appliquer( cadre.state().get( 'selection' ).toJSON() );
				} );

				// Présélectionner ce qui est déjà retenu évite au bureau de
				// tout resélectionner pour ajouter une seule photo.
				cadre.on( 'open', function () {
					var selection = cadre.state().get( 'selection' );
					selection.reset();
					ids().forEach( function ( id ) {
						var attachment = window.wp.media.attachment( id );
						attachment.fetch();
						selection.add( attachment );
					} );
				} );
			}

			cadre.open();
		} );

		liste.addEventListener( 'click', function ( event ) {
			var bouton = event.target.closest( '.jcmv-galerie__retirer' );
			if ( ! bouton ) {
				return;
			}

			var element = bouton.closest( '.jcmv-galerie__item' );
			if ( element ) {
				element.remove();
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

				var element = document.createElement( 'li' );
				element.className = 'jcmv-galerie__item';
				element.dataset.id = attachment.id;

				var img = document.createElement( 'img' );
				img.src = url;
				// Vignette d'administration purement décorative : le texte
				// alternatif du site vient du titre du produit, pas d'ici.
				img.alt = '';
				element.appendChild( img );

				var retirer = document.createElement( 'button' );
				retirer.type = 'button';
				retirer.className = 'button-link jcmv-galerie__retirer';
				retirer.textContent = i18n.retirer || 'Retirer';
				element.appendChild( retirer );

				liste.appendChild( element );
			} );

			synchroniser();
		}

		function synchroniser() {
			champ.value = Array.prototype.map
				.call( liste.querySelectorAll( '.jcmv-galerie__item' ), function ( element ) {
					return element.dataset.id;
				} )
				.join( ',' );
		}
	}
} )();
