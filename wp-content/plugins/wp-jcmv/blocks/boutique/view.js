/**
 * Bloc « Boutique » — permutation des photos d'un produit.
 *
 * Toutes les photos sont déjà dans le DOM (rendu serveur) : le script ne fait
 * que déplacer la classe is-active. Aucun src ni srcset n'est réécrit, donc
 * aucun risque de servir une image de mauvaise taille, et le chargement
 * différé du navigateur reste maître du téléchargement.
 *
 * Les vignettes sont masquées par le CSS tant que ce script n'a pas posé
 * jcmv-shop--js : sans JavaScript, la carte se réduit à sa photo principale
 * plutôt qu'à une rangée de boutons inertes.
 */
( function () {
	'use strict';

	function activer( media, index ) {
		var photos = media.querySelectorAll( '.jcmv-shop__photo' );
		var thumbs = media.querySelectorAll( '.jcmv-shop__thumb' );

		if ( ! photos[ index ] ) {
			return;
		}

		Array.prototype.forEach.call( photos, function ( photo, i ) {
			photo.classList.toggle( 'is-active', i === index );
		} );

		Array.prototype.forEach.call( thumbs, function ( thumb, i ) {
			thumb.classList.toggle( 'is-active', i === index );
			thumb.setAttribute( 'aria-pressed', i === index ? 'true' : 'false' );
		} );
	}

	/**
	 * Amène une vignette dans la partie visible de la bande, en laissant
	 * dépasser la moitié de la suivante.
	 *
	 * Sans ça, cliquer la vignette coupée du bord ne déplaçait rien : un
	 * navigateur ne fait défiler vers l'élément qui reçoit le focus que pour la
	 * navigation séquentielle au clavier, jamais pour un focus provoqué par un
	 * clic — le pointeur est déjà sur l'élément, il n'a rien à révéler. La
	 * photo changeait donc bien, mais la bande restait immobile et la vignette
	 * qu'on venait de choisir restait à moitié cachée.
	 *
	 * La demi-vignette d'amorce n'est pas un ornement : un simple « défiler au
	 * minimum » collerait la vignette choisie contre le bord, et le signal qui
	 * dit qu'il y a une suite disparaîtrait au premier clic — précisément quand
	 * on vient de prouver qu'il y en a une. Elle est symétrique, donc le retour
	 * en arrière laisse aussi voir d'où l'on vient.
	 *
	 * On écrit scrollLeft plutôt que d'appeler scrollIntoView() : celui-ci
	 * remonte toute la chaîne des conteneurs de défilement et ferait sauter la
	 * page quand la carte est à cheval sur le bas de la fenêtre. Le navigateur
	 * borne scrollLeft de lui-même, il n'y a rien à plafonner ici, et
	 * `scroll-behavior: smooth` — neutralisé sous prefers-reduced-motion — se
	 * charge de l'animation.
	 */
	function amener( bande, thumb ) {
		if ( ! bande ) {
			return;
		}

		var style = window.getComputedStyle( bande );
		var marge = parseFloat( style.paddingLeft ) || 0;
		var margeFin = parseFloat( style.paddingRight ) || 0;
		var gouttiere = parseFloat( style.columnGap ) || 0;

		var zone = bande.getBoundingClientRect();
		var cible = thumb.getBoundingClientRect();
		// Moitié de vignette plus la gouttière : après le défilement, la
		// suivante montre exactement la même moitié qu'elle montre au repos.
		var amorce = cible.width / 2 + gouttiere;

		var manqueGauche = cible.left - amorce - ( zone.left + marge );
		var manqueDroite = cible.right + amorce - ( zone.right - margeFin );

		if ( manqueGauche < 0 ) {
			bande.scrollLeft += manqueGauche;
		} else if ( manqueDroite > 0 ) {
			bande.scrollLeft += manqueDroite;
		}
	}

	function init( media ) {
		var bande = media.querySelector( '.jcmv-shop__thumbs' );

		media.addEventListener( 'click', function ( event ) {
			var thumb = event.target.closest( '.jcmv-shop__thumb' );
			if ( ! thumb ) {
				return;
			}

			activer( media, parseInt( thumb.dataset.jcmvPhoto, 10 ) || 0 );
			amener( bande, thumb );
		} );
	}

	function demarrer() {
		var grilles = document.querySelectorAll( '.jcmv-shop' );

		Array.prototype.forEach.call( grilles, function ( grille ) {
			grille.classList.add( 'jcmv-shop--js' );
		} );

		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-jcmv-gallery]' ),
			init
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', demarrer );
	} else {
		demarrer();
	}
} )();
