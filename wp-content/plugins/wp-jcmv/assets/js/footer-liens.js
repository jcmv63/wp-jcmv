/**
 * Écran « Pied de page » : confort de saisie des colonnes de liens.
 *
 * Vanilla, sans build, comme produit-metabox.js (règle ADR-002, « pas d'usine
 * à gaz »). Progressif : le serveur rend déjà toutes les lignes existantes
 * plus deux lignes vides. Sans ce script, la page reste utilisable — on
 * remplit les lignes disponibles, on enregistre, deux nouvelles apparaissent.
 * Ce qu'il ajoute : ajouter, supprimer, monter, descendre sans rechargement.
 *
 * Les index des champs (jcmv_footer[c][liens][i][...]) sont réécrits après
 * chaque manipulation. C'est délibérément explicite : PHP construirait bien
 * le tableau dans l'ordre du document, mais s'appuyer là-dessus rendrait
 * l'ordre d'affichage dépendant d'un détail d'implémentation invisible.
 */
( function () {
	'use strict';

	var config = window.jcmvFooter || {};
	var i18n = config.i18n || {};
	var max = parseInt( config.max, 10 ) || 10;

	document.addEventListener( 'DOMContentLoaded', function () {
		var columns = document.querySelectorAll( '.jcmv-footer-col' );
		Array.prototype.forEach.call( columns, initColumn );
	} );

	function initColumn( column ) {
		var zone = column.querySelector( '.jcmv-footer-rows' );

		if ( ! zone ) {
			return;
		}

		var add = document.createElement( 'button' );
		add.type = 'button';
		add.className = 'button jcmv-footer-add';
		add.textContent = i18n.add || 'Ajouter un lien';

		add.addEventListener( 'click', function () {
			var rows = zone.querySelectorAll( '.jcmv-footer-row' );
			var clone = rows[ rows.length - 1 ].cloneNode( true );

			reset( clone );
			zone.appendChild( clone );
			decorate( clone, column );
			renumber( column );
			refresh( column );
			focusFirst( clone );
		} );

		column.appendChild( add );

		Array.prototype.forEach.call( zone.querySelectorAll( '.jcmv-footer-row' ), function ( row ) {
			decorate( row, column );
		} );

		refresh( column );
	}

	/* --- Une ligne -------------------------------------------------------- */

	function decorate( row, column ) {
		var tools = document.createElement( 'div' );
		tools.className = 'jcmv-footer-row__tools';

		tools.appendChild( button( '↑', i18n.up || 'Monter', function () {
			move( row, column, -1 );
		} ) );

		tools.appendChild( button( '↓', i18n.down || 'Descendre', function () {
			move( row, column, 1 );
		} ) );

		tools.appendChild( button( '×', i18n.remove || 'Supprimer', function () {
			remove( row, column );
		} ) );

		row.appendChild( tools );

		var select = row.querySelector( 'select' );

		if ( select ) {
			select.addEventListener( 'change', function () {
				syncTarget( row );
			} );
		}

		syncTarget( row );
	}

	/**
	 * Une page choisie rend le champ URL sans objet : on le neutralise plutôt
	 * que de laisser croire que les deux se cumulent. Un champ désactivé n'est
	 * pas envoyé — c'est exactement ce qu'on veut ici.
	 */
	function syncTarget( row ) {
		var select = row.querySelector( 'select' );
		var url = row.querySelector( '.jcmv-footer-row__url' );

		if ( ! select || ! url ) {
			return;
		}

		var internal = '0' !== select.value;

		url.disabled = internal;
		url.classList.toggle( 'jcmv-footer-row__url--off', internal );
	}

	function reset( row ) {
		var tools = row.querySelector( '.jcmv-footer-row__tools' );

		if ( tools ) {
			tools.parentNode.removeChild( tools );
		}

		Array.prototype.forEach.call( row.querySelectorAll( 'input' ), function ( input ) {
			input.value = '';
			input.disabled = false;
		} );

		var select = row.querySelector( 'select' );

		if ( select ) {
			select.value = '0';
		}
	}

	/* --- Manipulations ---------------------------------------------------- */

	function move( row, column, direction ) {
		var sibling = direction < 0 ? row.previousElementSibling : row.nextElementSibling;

		if ( ! sibling || ! sibling.classList.contains( 'jcmv-footer-row' ) ) {
			return;
		}

		if ( direction < 0 ) {
			sibling.parentNode.insertBefore( row, sibling );
		} else {
			sibling.parentNode.insertBefore( sibling, row );
		}

		renumber( column );
		focusFirst( row );
	}

	function remove( row, column ) {
		var zone = row.parentNode;

		// Jamais zéro ligne : la dernière est vidée, pas supprimée — sans quoi
		// il n'y aurait plus de gabarit à cloner pour « Ajouter un lien ».
		if ( zone.querySelectorAll( '.jcmv-footer-row' ).length <= 1 ) {
			reset( row );
			decorate( row, column );
		} else {
			zone.removeChild( row );
		}

		renumber( column );
		refresh( column );
	}

	function renumber( column ) {
		var index = column.getAttribute( 'data-col' );
		var rows = column.querySelectorAll( '.jcmv-footer-row' );

		Array.prototype.forEach.call( rows, function ( row, position ) {
			var prefix = 'jcmv_footer[' + index + '][liens][' + position + ']';

			Array.prototype.forEach.call( row.querySelectorAll( '[name]' ), function ( field ) {
				field.name = field.name.replace(
					/^jcmv_footer\[\d+\]\[liens\]\[\d+\]/,
					prefix
				);
			} );

			var select = row.querySelector( 'select' );

			if ( select ) {
				select.id = 'jcmv-footer-' + index + '-' + position + '-page';
			}
		} );
	}

	/** Le plafond de saisie est celui du repository : on ferme le bouton. */
	function refresh( column ) {
		var add = column.querySelector( '.jcmv-footer-add' );
		var count = column.querySelectorAll( '.jcmv-footer-row' ).length;

		if ( add ) {
			add.disabled = count >= max;
		}
	}

	/* --- Outils ----------------------------------------------------------- */

	function button( symbol, label, handler ) {
		var element = document.createElement( 'button' );

		element.type = 'button';
		element.className = 'button button-small';
		element.innerHTML = '<span aria-hidden="true">' + symbol + '</span>';
		element.setAttribute( 'aria-label', label );
		element.title = label;
		element.addEventListener( 'click', handler );

		return element;
	}

	function focusFirst( row ) {
		var input = row.querySelector( 'input[type="text"]' );

		if ( input ) {
			input.focus();
		}
	}
}() );
