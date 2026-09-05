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

		var max = parseInt( zone.dataset.max, 10 ) || config.galerieMax || 12;
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
			var deplacement = event.target.closest( '.jcmv-galerie__deplacer' );

			if ( deplacement ) {
				deplacer( deplacement );
				return;
			}

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

		// L'état initial des flèches vient du serveur ; on le recalcule ici pour
		// que le script en soit l'unique responsable une fois la page chargée.
		synchroniser();

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

		/**
		 * Permute une photo avec sa voisine.
		 *
		 * Le focus suit la PHOTO déplacée, pas la position : sans ça, on perd sa
		 * place à chaque clic et on ne peut pas enchaîner les déplacements au
		 * clavier — trois clics deviennent trois clics plus trois tabulations.
		 * Si la flèche empruntée vient d'être désactivée, la photo a atteint le
		 * bout : on bascule sur l'autre plutôt que de laisser le focus retomber
		 * sur le document.
		 */
		function deplacer( bouton ) {
			var element = bouton.closest( '.jcmv-galerie__item' );
			var sens = parseInt( bouton.dataset.jcmvSens, 10 );

			if ( ! element || ! sens ) {
				return;
			}

			var voisin = sens < 0 ? element.previousElementSibling : element.nextElementSibling;

			if ( ! voisin ) {
				return;
			}

			if ( sens < 0 ) {
				liste.insertBefore( element, voisin );
			} else {
				liste.insertBefore( voisin, element );
			}

			synchroniser();

			var suivant = element.querySelector( '[data-jcmv-sens="' + sens + '"]' );

			if ( ! suivant || suivant.disabled ) {
				suivant = element.querySelector( '[data-jcmv-sens="' + -sens + '"]' );
			}

			if ( suivant ) {
				suivant.focus();
			}
		}

		/**
		 * Reporte la sélection de la médiathèque sur la liste.
		 *
		 * On ne reconstruit PAS la liste de zéro, contrairement à la première
		 * version : les photos déjà présentes gardent leur place, seules les
		 * disparues sont retirées et les nouvelles ajoutées à la suite. Sans
		 * cela, rouvrir la médiathèque pour ajouter une seule photo effacerait
		 * l'ordre que le bureau vient de composer aux flèches — le genre de
		 * perte qu'on ne remarque qu'après enregistrement.
		 */
		function appliquer( attachments ) {
			var choisis = attachments.map( function ( attachment ) {
				return String( attachment.id );
			} );

			Array.prototype.forEach.call(
				liste.querySelectorAll( '.jcmv-galerie__item' ),
				function ( element ) {
					if ( -1 === choisis.indexOf( element.dataset.id ) ) {
						element.remove();
					}
				}
			);

			attachments.forEach( function ( attachment ) {
				if ( liste.children.length >= max ) {
					return;
				}

				if ( liste.querySelector( '.jcmv-galerie__item[data-id="' + attachment.id + '"]' ) ) {
					return;
				}

				liste.appendChild( creerItem( attachment.id, urlVignette( attachment ) ) );
			} );

			synchroniser();
		}

		function creerItem( id, url ) {
			var element = document.createElement( 'li' );
			element.className = 'jcmv-galerie__item';
			element.dataset.id = id;

			var img = document.createElement( 'img' );
			img.src = url;
			// Vignette d'administration purement décorative : le texte
			// alternatif du site vient du titre du produit, pas d'ici.
			img.alt = '';
			element.appendChild( img );

			var ordre = document.createElement( 'p' );
			ordre.className = 'jcmv-galerie__ordre';
			// Sans aria-label ici : synchroniser() le pose, rang compris, et
			// il est appelé juste après. Un seul endroit nomme ces boutons.
			ordre.appendChild( fleche( -1, '←' ) );
			ordre.appendChild( fleche( 1, '→' ) );
			element.appendChild( ordre );

			var retirer = document.createElement( 'button' );
			retirer.type = 'button';
			retirer.className = 'button-link jcmv-galerie__retirer';
			retirer.textContent = i18n.retirer || 'Retirer';
			element.appendChild( retirer );

			return element;
		}

		function fleche( sens, glyphe ) {
			var bouton = document.createElement( 'button' );
			bouton.type = 'button';
			bouton.className = 'button jcmv-galerie__deplacer';
			bouton.dataset.jcmvSens = sens;
			// Le glyphe est décoratif : c'est aria-label qui nomme le bouton.
			bouton.textContent = glyphe;

			return bouton;
		}

		/**
		 * Libellé d'une flèche pour un rang donné.
		 *
		 * Le repli en dur n'est pas une précaution de style : sans lui, un
		 * aria-label vide fait retomber le nom accessible du bouton sur son
		 * glyphe, et le lecteur d'écran annonce « flèche vers la gauche » au
		 * lieu de ce que fait le bouton.
		 */
		function libelleOrdre( gabarit, repli, rang ) {
			return String( gabarit || repli ).replace( '%d', rang );
		}

		/**
		 * Écrit l'ordre dans le champ caché et remet les flèches d'extrémité au
		 * bon état. Toute mutation de la liste passe par ici : c'est le seul
		 * endroit où l'ordre affiché et l'ordre enregistré se rejoignent.
		 */
		function synchroniser() {
			var items = liste.querySelectorAll( '.jcmv-galerie__item' );

			champ.value = Array.prototype.map
				.call( items, function ( element ) {
					return element.dataset.id;
				} )
				.join( ',' );

			/*
			 * Les libellés portent le rang, et sont donc réécrits à chaque
			 * mutation. C'est ce qui rend les boutons distinguables à la
			 * synthèse vocale — les vignettes sont en alt="" — et c'est aussi le
			 * compte rendu du déplacement : le focus reste sur le bouton de la
			 * photo déplacée, dont le nom vient de changer.
			 */
			Array.prototype.forEach.call( items, function ( element, index ) {
				var gauche = element.querySelector( '[data-jcmv-sens="-1"]' );
				var droite = element.querySelector( '[data-jcmv-sens="1"]' );

				if ( gauche ) {
					gauche.disabled = 0 === index;
					gauche.setAttribute(
						'aria-label',
						libelleOrdre( i18n.versGauche, 'Déplacer la photo %d vers la gauche', index + 1 )
					);
				}

				if ( droite ) {
					droite.disabled = index === items.length - 1;
					droite.setAttribute(
						'aria-label',
						libelleOrdre( i18n.versDroite, 'Déplacer la photo %d vers la droite', index + 1 )
					);
				}
			} );
		}
	}

	function urlVignette( attachment ) {
		return (
			( attachment.sizes &&
				attachment.sizes.thumbnail &&
				attachment.sizes.thumbnail.url ) ||
			attachment.url
		);
	}
} )();
