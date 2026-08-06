/**
 * Bloc « Abonnement au calendrier » — composition du lien (ADR-004).
 *
 * Amélioration progressive : le balisage rendu par render.php pointe déjà sur
 * le flux global et fonctionne sans ce script. On se contente ici de recalculer
 * le lien au cochage, et de révéler les commandes qui n'ont de sens qu'avec du
 * JavaScript (tout cocher, tout décocher, copier) — d'où leur attribut `hidden`
 * dans le HTML servi.
 */
( function () {
	'use strict';

	function init( racine ) {
		var base = racine.dataset.base;
		var tous = racine.dataset.tous;
		var cases = Array.prototype.slice.call(
			racine.querySelectorAll( 'input[name="jcmv-categorie"]' )
		);
		var bouton = racine.querySelector( '[data-jcmv-bouton]' );
		var champ = racine.querySelector( '[data-jcmv-url]' );
		var vide = racine.querySelector( '[data-jcmv-vide]' );
		var copier = racine.querySelector( '[data-jcmv-copier]' );
		var statut = racine.querySelector( '[data-jcmv-copie-statut]' );
		var toutBtn = racine.querySelector( '[data-jcmv-tout]' );
		var rienBtn = racine.querySelector( '[data-jcmv-rien]' );

		if ( ! cases.length || ! bouton || ! champ ) {
			return;
		}

		function coches() {
			return cases
				.filter( function ( c ) {
					return c.checked;
				} )
				.map( function ( c ) {
					return c.value;
				} );
		}

		function rafraichir() {
			var choix = coches();
			// Tout coché équivaut au flux global : on sert alors l'URL courte,
			// plus lisible et plus facile à dicter qu'une liste de neuf slugs.
			var nom =
				0 === choix.length || choix.length === cases.length
					? tous
					: choix.join( '+' );
			var url = base + nom + '.ics';
			var rien = 0 === choix.length;

			champ.value = url;
			bouton.href = url.replace( /^https?:\/\//, 'webcal://' );

			// Aucune case cochée : on neutralise le bouton plutôt que de le
			// masquer, pour que la mise en page ne saute pas.
			bouton.setAttribute( 'aria-disabled', rien ? 'true' : 'false' );
			bouton.classList.toggle( 'is-disabled', rien );
			if ( vide ) {
				vide.hidden = ! rien;
			}
		}

		cases.forEach( function ( c ) {
			c.addEventListener( 'change', rafraichir );
		} );

		bouton.addEventListener( 'click', function ( evt ) {
			if ( 'true' === bouton.getAttribute( 'aria-disabled' ) ) {
				evt.preventDefault();
			}
		} );

		if ( toutBtn ) {
			toutBtn.hidden = false;
			toutBtn.addEventListener( 'click', function () {
				cases.forEach( function ( c ) {
					c.checked = true;
				} );
				rafraichir();
			} );
		}

		if ( rienBtn ) {
			rienBtn.hidden = false;
			rienBtn.addEventListener( 'click', function () {
				cases.forEach( function ( c ) {
					c.checked = false;
				} );
				rafraichir();
			} );
		}

		// Le presse-papier n'est disponible qu'en contexte sécurisé : on ne
		// révèle le bouton que s'il peut tenir sa promesse.
		if ( copier && navigator.clipboard ) {
			copier.hidden = false;
			copier.addEventListener( 'click', function () {
				navigator.clipboard.writeText( champ.value ).then(
					function () {
						champ.select();
						if ( statut ) {
							statut.textContent = 'Adresse copiée.';
						}
					},
					function () {
						if ( statut ) {
							statut.textContent =
								'La copie a échoué, sélectionnez l’adresse à la main.';
						}
					}
				);
			} );
		}

		rafraichir();
	}

	function demarrer() {
		document.querySelectorAll( '[data-jcmv-abo]' ).forEach( init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', demarrer );
	} else {
		demarrer();
	}
} )();
