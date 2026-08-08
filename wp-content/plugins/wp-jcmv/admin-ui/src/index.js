/**
 * App admin « Saisons » (ADR-002, niveau 3).
 *
 * Sélecteur de saison, workflow préparer / activer, frais licence-adhésion,
 * grille créneaux + tarifs éditée par cours et sauvegardée par lot
 * (PUT /seasons/{id}/courses/{course_id}/schedules|pricing).
 */

import { createRoot, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';

const WEEKDAYS = [
	{ value: 1, label: 'Lundi' },
	{ value: 2, label: 'Mardi' },
	{ value: 3, label: 'Mercredi' },
	{ value: 4, label: 'Jeudi' },
	{ value: 5, label: 'Vendredi' },
	{ value: 6, label: 'Samedi' },
	{ value: 7, label: 'Dimanche' },
];

const STATUS_LABELS = {
	draft: 'Brouillon',
	active: 'Saison active',
	archived: 'Archivée',
};

const rowStyle = {
	display: 'flex',
	gap: '8px',
	alignItems: 'flex-end',
	flexWrap: 'wrap',
	marginBottom: '4px',
};

const emptySchedule = () => ( {
	location_id: 0,
	weekday: 1,
	start_time: '',
	end_time: '',
	note: '',
} );

/**
 * « 24,50 » → 24.5, « » → 0.
 *
 * Même règle que PostTypes::sanitize_amount() côté PHP, et pour la même raison :
 * un clavier français produit une virgule décimale. Un parseFloat() nu la
 * tronque — 24,50 € enregistré à 24 €, sans rien signaler. Sur des montants
 * que le bureau relit rarement, c'est le genre d'écart qui vit une saison.
 *
 * C'est la contrepartie du choix de saisie : les champs de montant sont en
 * `type="text" inputMode="decimal"` et non `type="number"`, comme la metabox
 * produit l'avait déjà tranché. Un champ `number` rejette la virgule selon la
 * locale du navigateur, et le fait en silence — la saisie disparaît sous les
 * doigts. On préfère accepter les deux séparateurs et normaliser ici.
 *
 * L'état conserve la chaîne brute et la conversion n'a lieu qu'à
 * l'enregistrement : reformater à chaque frappe ferait sauter le curseur dès
 * qu'on tape « 24, ».
 */
const toAmount = ( value ) => {
	const nombre = parseFloat( String( value ).replace( ',', '.' ) );
	return Number.isFinite( nombre ) ? nombre : 0;
};

const emptyPricing = () => ( {
	label: '',
	amount: 0,
	period: '',
	note: '',
} );

function groupByCourse( rows ) {
	const map = {};
	rows.forEach( ( row ) => {
		if ( ! map[ row.course_id ] ) {
			map[ row.course_id ] = [];
		}
		map[ row.course_id ].push( { ...row } );
	} );
	return map;
}

function SeasonBadge( { status } ) {
	const colors = {
		draft: { background: '#FFF6E9', color: '#9A6A0C' },
		active: { background: '#E2F5EA', color: '#1E7A41' },
		archived: { background: '#F5F6F8', color: '#6B7280' },
	};
	return (
		<span
			style={ {
				...colors[ status ],
				padding: '4px 12px',
				borderRadius: '30px',
				fontWeight: 600,
				fontSize: '12px',
				textTransform: 'uppercase',
			} }
		>
			{ STATUS_LABELS[ status ] || status }
		</span>
	);
}

function ScheduleRow( { row, locations, onChange, onRemove } ) {
	return (
		<div style={ rowStyle }>
			<SelectControl
				__nextHasNoMarginBottom
				label="Jour"
				value={ row.weekday }
				options={ WEEKDAYS }
				onChange={ ( v ) => onChange( { ...row, weekday: parseInt( v, 10 ) } ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				label="Début"
				type="time"
				value={ row.start_time }
				onChange={ ( v ) => onChange( { ...row, start_time: v } ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				label="Fin"
				type="time"
				value={ row.end_time }
				onChange={ ( v ) => onChange( { ...row, end_time: v } ) }
			/>
			<SelectControl
				__nextHasNoMarginBottom
				label="Lieu"
				value={ row.location_id }
				options={ [
					{ value: 0, label: '— Choisir —' },
					...locations.map( ( l ) => ( { value: l.id, label: l.title.rendered } ) ),
				] }
				onChange={ ( v ) => onChange( { ...row, location_id: parseInt( v, 10 ) } ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				label="Note"
				value={ row.note }
				onChange={ ( v ) => onChange( { ...row, note: v } ) }
			/>
			<Button isDestructive variant="tertiary" onClick={ onRemove }>
				Retirer
			</Button>
		</div>
	);
}

function PricingRow( { row, onChange, onRemove } ) {
	return (
		<div style={ rowStyle }>
			<TextControl
				__nextHasNoMarginBottom
				label="Libellé"
				placeholder="2 séances de 1h / semaine"
				value={ row.label }
				onChange={ ( v ) => onChange( { ...row, label: v } ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				label="Montant (€)"
				type="text"
				inputMode="decimal"
				value={ row.amount }
				onChange={ ( v ) => onChange( { ...row, amount: v } ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				label="Période"
				placeholder="/ an"
				value={ row.period }
				onChange={ ( v ) => onChange( { ...row, period: v } ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				label="Note"
				value={ row.note }
				onChange={ ( v ) => onChange( { ...row, note: v } ) }
			/>
			<Button isDestructive variant="tertiary" onClick={ onRemove }>
				Retirer
			</Button>
		</div>
	);
}

/*
 * Les lignes sont un état local, initialisé une seule fois : la carte est
 * remontée par sa `key` quand il faut la resynchroniser (changement de saison,
 * ou sauvegarde de CE cours). Voir la construction de la clé dans App.
 *
 * Il y avait ici un `useEffect` de resynchronisation sur `[ schedules, pricing ]`.
 * Ces deux props sont des tableaux recalculés à chaque rendu d'App — leur
 * identité changeait donc en permanence, et le moindre changement d'état du
 * parent (une notice affichée, une autre carte en cours d'enregistrement,
 * la fermeture d'un message) rejouait l'effet : les lignes en cours de saisie
 * repassaient à l'état serveur et `dirty` retombait à false, sans un mot.
 */
function CourseCard( { course, locations, schedules, pricing, onSave, saving } ) {
	const [ sch, setSch ] = useState( schedules );
	const [ pri, setPri ] = useState( pricing );
	const [ dirty, setDirty ] = useState( false );

	const update = ( setter ) => ( next ) => {
		setter( next );
		setDirty( true );
	};

	const updateSch = update( setSch );
	const updatePri = update( setPri );

	return (
		<Card style={ { marginBottom: '16px' } }>
			<CardHeader>
				<strong>{ course.title.rendered }</strong>
				<Button
					variant="primary"
					isBusy={ saving }
					disabled={ ! dirty || saving }
					onClick={ () => onSave( course.id, sch, pri ) }
				>
					{ dirty ? 'Enregistrer' : 'Enregistré' }
				</Button>
			</CardHeader>
			<CardBody>
				<h4 style={ { marginTop: 0 } }>Créneaux</h4>
				{ sch.length === 0 && <p><em>Aucun créneau.</em></p> }
				{ sch.map( ( row, i ) => (
					<ScheduleRow
						key={ i }
						row={ row }
						locations={ locations }
						onChange={ ( next ) => updateSch( sch.map( ( r, j ) => ( j === i ? next : r ) ) ) }
						onRemove={ () => updateSch( sch.filter( ( _, j ) => j !== i ) ) }
					/>
				) ) }
				<Button variant="secondary" onClick={ () => updateSch( [ ...sch, emptySchedule() ] ) }>
					Ajouter un créneau
				</Button>

				<h4>Tarifs</h4>
				{ pri.length === 0 && <p><em>Aucun tarif.</em></p> }
				{ pri.map( ( row, i ) => (
					<PricingRow
						key={ i }
						row={ row }
						onChange={ ( next ) => updatePri( pri.map( ( r, j ) => ( j === i ? next : r ) ) ) }
						onRemove={ () => updatePri( pri.filter( ( _, j ) => j !== i ) ) }
					/>
				) ) }
				<Button variant="secondary" onClick={ () => updatePri( [ ...pri, emptyPricing() ] ) }>
					Ajouter un tarif
				</Button>
			</CardBody>
		</Card>
	);
}

function FeesPanel( { season, onSaved, setNotice } ) {
	const [ fees, setFees ] = useState( {
		licence_amount: season.licence_amount,
		adhesion_amount: season.adhesion_amount,
		licence_note: season.licence_note,
		adhesion_note: season.adhesion_note,
	} );
	const [ dirty, setDirty ] = useState( false );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		setFees( {
			licence_amount: season.licence_amount,
			adhesion_amount: season.adhesion_amount,
			licence_note: season.licence_note,
			adhesion_note: season.adhesion_note,
		} );
		setDirty( false );
	}, [ season ] );

	const set = ( key ) => ( v ) => {
		setFees( { ...fees, [ key ]: v } );
		setDirty( true );
	};

	const save = async () => {
		setSaving( true );
		try {
			const updated = await apiFetch( {
				path: `/jcmv/v1/seasons/${ season.id }/fees`,
				method: 'PUT',
				data: {
					...fees,
					// TextControl rend toujours une chaîne, et une chaîne vide
					// quand le champ est effacé — ce que le bureau fait pour
					// dire « zéro ». Le schéma REST attend un nombre : sans
					// cette conversion, effacer un montant renverrait une erreur
					// de type au lieu d'enregistrer 0.
					licence_amount: toAmount( fees.licence_amount ),
					adhesion_amount: toAmount( fees.adhesion_amount ),
				},
			} );
			onSaved( updated );
			setDirty( false );
			setNotice( { status: 'success', message: 'Frais enregistrés.' } );
		} catch ( e ) {
			setNotice( { status: 'error', message: e.message || 'Erreur de sauvegarde.' } );
		}
		setSaving( false );
	};

	return (
		<Card style={ { marginBottom: '16px' } }>
			<CardHeader>
				<strong>Licence FFJDA &amp; adhésion club</strong>
				<Button variant="primary" isBusy={ saving } disabled={ ! dirty || saving } onClick={ save }>
					{ dirty ? 'Enregistrer' : 'Enregistré' }
				</Button>
			</CardHeader>
			<CardBody>
				<div style={ rowStyle }>
					<TextControl
						__nextHasNoMarginBottom
						label="Licence (€)"
						type="text"
						inputMode="decimal"
						value={ fees.licence_amount }
						onChange={ set( 'licence_amount' ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						label="Mention licence"
						value={ fees.licence_note }
						onChange={ set( 'licence_note' ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						label="Adhésion (€)"
						type="text"
						inputMode="decimal"
						value={ fees.adhesion_amount }
						onChange={ set( 'adhesion_amount' ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						label="Mention adhésion"
						value={ fees.adhesion_note }
						onChange={ set( 'adhesion_note' ) }
					/>
				</div>
			</CardBody>
		</Card>
	);
}

function App() {
	const [ loading, setLoading ] = useState( true );
	const [ seasons, setSeasons ] = useState( [] );
	const [ courses, setCourses ] = useState( [] );
	const [ locations, setLocations ] = useState( [] );
	const [ seasonId, setSeasonId ] = useState( 0 );
	const [ grid, setGrid ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ savingCourse, setSavingCourse ] = useState( 0 );
	/*
	 * Compteur d'enregistrements par cours. Il entre dans la `key` de la carte :
	 * l'incrémenter la remonte sur la donnée fraîche du serveur. Par cours, et
	 * non global, pour qu'enregistrer un cours ne vide pas la saisie en cours
	 * dans un autre.
	 */
	const [ savedAt, setSavedAt ] = useState( {} );

	useEffect( () => {
		Promise.all( [
			apiFetch( { path: '/jcmv/v1/seasons' } ),
			apiFetch( { path: '/wp/v2/jcmv_cours?per_page=100&orderby=menu_order&order=asc&_fields=id,title' } ),
			apiFetch( { path: '/wp/v2/jcmv_lieu?per_page=100&_fields=id,title' } ),
		] )
			.then( ( [ s, c, l ] ) => {
				setSeasons( s );
				setCourses( c );
				setLocations( l );
				const active = s.find( ( x ) => x.status === 'active' );
				setSeasonId( active ? active.id : ( s[ 0 ] ? s[ 0 ].id : 0 ) );
			} )
			.catch( ( e ) => setNotice( { status: 'error', message: e.message || 'Chargement impossible.' } ) )
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		if ( ! seasonId ) {
			setGrid( null );
			return;
		}
		setGrid( null );
		apiFetch( { path: `/jcmv/v1/seasons/${ seasonId }/grid` } )
			.then( setGrid )
			.catch( ( e ) => setNotice( { status: 'error', message: e.message || 'Chargement de la grille impossible.' } ) );
	}, [ seasonId ] );

	const season = seasons.find( ( s ) => s.id === seasonId );

	const refreshSeasons = ( list ) => {
		setSeasons( list );
	};

	const runSeasonAction = async ( path, confirmMsg, successMsg ) => {
		if ( confirmMsg && ! window.confirm( confirmMsg ) ) {
			return;
		}
		try {
			const list = await apiFetch( { path, method: 'POST' } );
			refreshSeasons( list );
			setNotice( { status: 'success', message: successMsg } );
		} catch ( e ) {
			setNotice( { status: 'error', message: e.message || 'Action impossible.' } );
		}
	};

	const deleteDraft = async () => {
		if ( ! window.confirm( `Supprimer définitivement le brouillon ${ season.label } et ses créneaux/tarifs ?` ) ) {
			return;
		}
		try {
			await apiFetch( { path: `/jcmv/v1/seasons/${ seasonId }`, method: 'DELETE' } );
			const list = await apiFetch( { path: '/jcmv/v1/seasons' } );
			setSeasons( list );
			setSeasonId( list[ 0 ] ? list[ 0 ].id : 0 );
			setNotice( { status: 'success', message: 'Brouillon supprimé.' } );
		} catch ( e ) {
			setNotice( { status: 'error', message: e.message || 'Suppression impossible.' } );
		}
	};

	const createFirstSeason = async () => {
		const year = window.prompt( 'Année de début de la saison (ex. 2026 pour 2026-2027) :' );
		if ( ! year ) {
			return;
		}
		try {
			const created = await apiFetch( {
				path: '/jcmv/v1/seasons',
				method: 'POST',
				data: { start_year: parseInt( year, 10 ) },
			} );
			const list = await apiFetch( { path: '/jcmv/v1/seasons' } );
			setSeasons( list );
			setSeasonId( created.id );
		} catch ( e ) {
			setNotice( { status: 'error', message: e.message || 'Création impossible.' } );
		}
	};

	const saveCourse = async ( courseId, sch, pri ) => {
		setSavingCourse( courseId );
		try {
			/*
			 * Une seule requête pour les deux lots. Il y en avait deux, à la
			 * suite : un échec sur la seconde laissait la première écrite, et
			 * le message d'erreur mentait au bureau. Le serveur les écrit
			 * désormais dans la même transaction.
			 */
			const freshGrid = await apiFetch( {
				path: `/jcmv/v1/seasons/${ seasonId }/courses/${ courseId }`,
				method: 'PUT',
				data: {
					schedules: sch,
					// Montants normalisés ici comme ceux des frais : le
					// repository sait aussi lire une virgule, mais c'est son
					// rôle de dernier rempart pour les autres écrivains
					// (WP-CLI, import), pas la façon dont l'app doit écrire.
					pricing: pri.map( ( r ) => ( { ...r, amount: toAmount( r.amount ) } ) ),
				},
			} );
			setGrid( freshGrid );
			// L'écriture étant atomique, ce compteur ne peut plus remonter la
			// carte sur un état serveur à moitié à jour.
			setSavedAt( ( prev ) => ( { ...prev, [ courseId ]: ( prev[ courseId ] || 0 ) + 1 } ) );
			setNotice( { status: 'success', message: 'Créneaux et tarifs enregistrés.' } );
		} catch ( e ) {
			setNotice( { status: 'error', message: e.message || 'Sauvegarde impossible.' } );
		}
		setSavingCourse( 0 );
	};

	if ( loading ) {
		return <Spinner />;
	}

	if ( seasons.length === 0 ) {
		return (
			<div>
				<p>Aucune saison n'existe encore.</p>
				<Button variant="primary" onClick={ createFirstSeason }>
					Créer la première saison
				</Button>
			</div>
		);
	}

	const schByCourse = grid ? groupByCourse( grid.schedules ) : {};
	const priByCourse = grid ? groupByCourse( grid.pricing ) : {};

	return (
		<div style={ { maxWidth: '1080px' } }>
			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) } isDismissible>
					{ notice.message }
				</Notice>
			) }

			<div style={ { ...rowStyle, margin: '16px 0' } }>
				<SelectControl
					__nextHasNoMarginBottom
					label="Saison"
					value={ seasonId }
					options={ seasons.map( ( s ) => ( {
						value: s.id,
						label: `${ s.label } — ${ STATUS_LABELS[ s.status ] || s.status }`,
					} ) ) }
					onChange={ ( v ) => setSeasonId( parseInt( v, 10 ) ) }
				/>
				{ season && <SeasonBadge status={ season.status } /> }
				{ season && season.status !== 'active' && (
					<Button
						variant="secondary"
						onClick={ () =>
							runSeasonAction(
								`/jcmv/v1/seasons/${ seasonId }/activate`,
								`Activer la saison ${ season.label } ? La saison active actuelle sera archivée.`,
								'Saison activée.'
							)
						}
					>
						Activer cette saison
					</Button>
				) }
				{ season && (
					<Button
						variant="secondary"
						onClick={ () =>
							runSeasonAction(
								`/jcmv/v1/seasons/${ seasonId }/prepare`,
								`Préparer la saison ${ season.start_year + 1 }-${ season.start_year + 2 } en dupliquant les créneaux et tarifs de ${ season.label } ?`,
								'Saison suivante préparée (brouillon).'
							)
						}
					>
						Préparer la saison suivante
					</Button>
				) }
				{ season && season.status === 'draft' && (
					<Button isDestructive variant="tertiary" onClick={ deleteDraft }>
						Supprimer ce brouillon
					</Button>
				) }
			</div>

			{ season && <FeesPanel season={ season } onSaved={ ( updated ) => setSeasons( seasons.map( ( s ) => ( s.id === updated.id ? updated : s ) ) ) } setNotice={ setNotice } /> }

			{ ! grid && <Spinner /> }

			{ grid && courses.length === 0 && (
				<Notice status="info" isDismissible={ false }>
					Aucun cours défini : créer d'abord les cours dans le menu JCMV → Cours.
				</Notice>
			) }

			{ grid &&
				courses.map( ( course ) => (
					<CourseCard
						key={ `${ seasonId }-${ course.id }-${ savedAt[ course.id ] || 0 }` }
						course={ course }
						locations={ locations }
						schedules={ schByCourse[ course.id ] || [] }
						pricing={ priByCourse[ course.id ] || [] }
						onSave={ saveCourse }
						saving={ savingCourse === course.id }
					/>
				) ) }
		</div>
	);
}

const el = document.getElementById( 'jcmv-saisons-app' );
if ( el ) {
	createRoot( el ).render( <App /> );
}
