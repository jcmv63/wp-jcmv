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

	function init( media ) {
		media.addEventListener( 'click', function ( event ) {
			var thumb = event.target.closest( '.jcmv-shop__thumb' );
			if ( ! thumb ) {
				return;
			}

			activer( media, parseInt( thumb.dataset.jcmvPhoto, 10 ) || 0 );
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
