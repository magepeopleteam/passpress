import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import apiFetch from '../api.js';
import { useRouter } from '../router.jsx';

const COUNT_LABELS = [
	{ key: 'plans', label: 'Membership plans' },
	{ key: 'facilities', label: 'Facilities' },
	{ key: 'classes', label: 'Classes' },
	{ key: 'pages', label: 'Pages' },
];

/**
 * The three-step business-template import modal — reused by both the
 * Dashboard's first-run trigger (?pp_welcome=1) and the standalone Setup
 * page, same as admin/PP_Setup_Wizard.php's render_modal() used to be.
 */
export default function SetupWizardModal( { open, onClose } ) {
	const { navigate } = useRouter();
	const queryClient = useQueryClient();
	const { data } = useQuery( {
		queryKey: [ 'setup-summaries' ],
		queryFn: () => apiFetch( { path: '/passpress/v1/setup/summaries' } ),
		enabled: open,
	} );

	const [ step, setStep ] = useState( 1 );
	const [ search, setSearch ] = useState( '' );
	const [ category, setCategory ] = useState( 'all' );
	const [ chosenSlug, setChosenSlug ] = useState( '' );
	const [ error, setError ] = useState( '' );

	const importMutation = useMutation( {
		mutationFn: ( slug ) => apiFetch( { path: '/passpress/v1/setup/import', method: 'POST', data: { business_type: slug } } ),
		onSuccess: () => {
			queryClient.invalidateQueries( { queryKey: [ 'dashboard' ] } );
			queryClient.invalidateQueries( { queryKey: [ 'setup-summaries' ] } );
			close();
			navigate( { page: 'passpress', pp_welcome: null } );
		},
		onError: ( err ) => setError( err.message || 'Something went wrong.' ),
	} );

	const close = () => {
		setStep( 1 );
		setSearch( '' );
		setCategory( 'all' );
		setChosenSlug( '' );
		setError( '' );
		onClose();
	};

	const summaries = data ? data.summaries : {};
	const categories = data ? data.categories : {};

	const filtered = useMemo( () => {
		return Object.values( summaries ).filter( ( s ) => {
			if ( 'all' !== category && s.category !== category ) {
				return false;
			}
			if ( search && ! s.label.toLowerCase().includes( search.toLowerCase() ) ) {
				return false;
			}
			return true;
		} );
	}, [ summaries, category, search ] );

	const chosen = chosenSlug ? summaries[ chosenSlug ] : null;

	if ( ! open ) {
		return null;
	}

	return (
		<div id="passpress-setup-modal" className="passpress-modal-overlay passpress-setup-modal">
			<div className="passpress-modal pp-setup-dialog" role="dialog" aria-modal="true">
				<button type="button" className="passpress-modal-close" aria-label="Close" onClick={ close }>&times;</button>

				{ 1 === step && (
					<section className="pp-setup-step">
						<span className="pp-setup-mark dashicons dashicons-id-alt" aria-hidden="true"></span>
						<h2 className="pp-setup-title">Want a head start?</h2>
						<p className="pp-setup-lede">
							PassPress can set itself up for your kind of business — starter membership plans, your spaces, and the member-facing pages. Everything it creates is editable, and you can delete any of it later.
						</p>
						<ul className="pp-setup-benefits">
							<li><span className="dashicons dashicons-tag" aria-hidden="true"></span>Membership plans priced for your business</li>
							<li><span className="dashicons dashicons-building" aria-hidden="true"></span>Facilities and classes, where they apply</li>
							<li><span className="dashicons dashicons-media-document" aria-hidden="true"></span>Ready-made pages for plans and member passes</li>
						</ul>
						<div className="pp-setup-actions">
							<button type="button" className="pp-btn-outline" onClick={ close }>No thanks, I'll start from scratch</button>
							<button type="button" className="pp-btn-solid" onClick={ () => setStep( 2 ) }>Yes, choose my business type</button>
						</div>
					</section>
				) }

				{ 2 === step && (
					<section className="pp-setup-step">
						<p className="pp-setup-eyebrow">Step 1 of 2</p>
						<h2 className="pp-setup-title">What kind of business is this?</h2>
						<p className="pp-setup-lede pp-setup-lede-tight">Pick the closest match. It only decides what starter content gets created.</p>

						<div className="pp-setup-toolbar">
							<label className="pp-setup-search">
								<span className="screen-reader-text">Search business types</span>
								<span className="dashicons dashicons-search" aria-hidden="true"></span>
								<input type="search" placeholder="Search…" autoComplete="off" value={ search } onChange={ ( e ) => setSearch( e.target.value ) } />
							</label>
							<div className="pp-setup-chips">
								<button type="button" className={ `pp-setup-chip${ 'all' === category ? ' is-active' : '' }` } onClick={ () => setCategory( 'all' ) }>All</button>
								{ Object.entries( categories ).map( ( [ catSlug, cat ] ) => (
									<button type="button" key={ catSlug } className={ `pp-setup-chip${ category === catSlug ? ' is-active' : '' }` } onClick={ () => setCategory( catSlug ) }>
										{ cat.label }
									</button>
								) ) }
							</div>
						</div>

						<div className="pp-setup-types">
							{ filtered.map( ( s ) => {
								const disabled = ! s.available || s.imported;
								return (
									<button
										type="button"
										key={ s.slug }
										className={ `pp-setup-type${ disabled ? ' is-disabled' : '' }` }
										disabled={ disabled }
										onClick={ () => { setChosenSlug( s.slug ); setStep( 3 ); } }
									>
										<span className={ `pp-setup-type-icon dashicons ${ s.icon }` } aria-hidden="true"></span>
										<span className="pp-setup-type-label">{ s.label }</span>
										{ s.imported && <span className="pp-setup-type-flag">Imported</span> }
									</button>
								);
							} ) }
						</div>

						{ 0 === filtered.length && <p className="pp-setup-nomatch">No business types match that search.</p> }

						<div className="pp-setup-actions">
							<button type="button" className="pp-btn-outline" onClick={ () => setStep( 1 ) }>Back</button>
						</div>
					</section>
				) }

				{ 3 === step && chosen && (
					<section className="pp-setup-step">
						<p className="pp-setup-eyebrow">Step 2 of 2</p>
						<h2 className="pp-setup-title">Set up <span>{ chosen.label }</span></h2>
						<p className="pp-setup-lede pp-setup-lede-tight">Here's what PassPress will create for you:</p>

						<ul className="pp-setup-counts">
							{ COUNT_LABELS.filter( ( c ) => chosen[ c.key ] > 0 ).map( ( c ) => (
								<li key={ c.key }>
									<strong>{ chosen[ c.key ] }</strong>
									<span>{ c.label }</span>
								</li>
							) ) }
						</ul>

						<p className="pp-setup-fineprint">
							<span className="dashicons dashicons-info-outline" aria-hidden="true"></span>
							Nothing already on your site is changed or removed. You can edit prices and delete anything you don't need straight afterwards.
						</p>

						{ error && <p className="pp-setup-fineprint" style={ { color: '#b32d2e' } }>{ error }</p> }

						<div className="pp-setup-actions">
							<button type="button" className="pp-btn-outline" onClick={ () => setStep( 2 ) }>Back</button>
							<button type="button" className="pp-btn-solid" disabled={ importMutation.isPending } onClick={ () => importMutation.mutate( chosenSlug ) }>
								{ importMutation.isPending ? 'Creating…' : 'Create my starter setup' }
							</button>
						</div>
					</section>
				) }
			</div>
		</div>
	);
}
