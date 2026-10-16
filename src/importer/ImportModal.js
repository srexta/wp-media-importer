/**
 * Search a provider and import the selected photos.
 */
import apiFetch from '@wordpress/api-fetch';
import { Modal, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

const SearchIcon = () => (
	<svg
		width="20"
		height="20"
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="2"
		strokeLinecap="round"
		aria-hidden="true"
	>
		<circle cx="11" cy="11" r="7" />
		<path d="m20 20-3.5-3.5" />
	</svg>
);

const ImageIcon = () => (
	<svg
		width="80"
		height="80"
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="1.5"
		strokeLinejoin="round"
		aria-hidden="true"
	>
		<rect x="3" y="3" width="18" height="18" rx="2" />
		<circle cx="9" cy="9" r="2" />
		<path d="m21 15-5-5L5 21" />
	</svg>
);

const CloseIcon = () => (
	<svg
		width="24"
		height="24"
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="1.75"
		strokeLinecap="round"
		aria-hidden="true"
	>
		<path d="M18 6 6 18M6 6l12 12" />
	</svg>
);

const CheckIcon = () => (
	<svg
		width="16"
		height="16"
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="2.5"
		strokeLinecap="round"
		strokeLinejoin="round"
		aria-hidden="true"
	>
		<path d="M20 6 9 17l-5-5" />
	</svg>
);

export default function ImportModal( {
	provider,
	restNamespace,
	maxSelection,
	onImported,
	onClose,
} ) {
	const [ query, setQuery ] = useState( '' );
	const [ searched, setSearched ] = useState( '' );
	const [ photos, setPhotos ] = useState( [] );
	const [ page, setPage ] = useState( 1 );
	const [ hasMore, setHasMore ] = useState( false );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ selected, setSelected ] = useState( [] );
	const [ progress, setProgress ] = useState( null );

	const base = `/${ restNamespace }/${ provider.slug }`;
	const isImporting = null !== progress;
	const [ limitHit, setLimitHit ] = useState( false );

	const search = async ( term, nextPage ) => {
		setIsLoading( true );
		setError( '' );

		try {
			const params = new URLSearchParams( {
				query: term,
				page: nextPage,
			} );
			const result = await apiFetch( {
				path: `${ base }/search?${ params }`,
			} );

			setPhotos(
				1 === nextPage ? result.photos : [ ...photos, ...result.photos ]
			);
			setHasMore( result.has_more );
			setPage( nextPage );
			setSearched( term );

			if ( 1 === nextPage ) {
				setSelected( [] );
			}
		} catch ( err ) {
			setError(
				err.message || __( 'Search failed.', 'wp-media-importer' )
			);
		} finally {
			setIsLoading( false );
		}
	};

	const toggle = ( id ) => {
		if ( selected.includes( id ) ) {
			setSelected( selected.filter( ( item ) => item !== id ) );
			setLimitHit( false );
		} else if ( selected.length >= maxSelection ) {
			// Results stay fully visible; only the import count is capped.
			setLimitHit( true );
		} else {
			setSelected( [ ...selected, id ] );
		}
	};

	const importSelected = async () => {
		const imported = [];
		const failed = [];

		// One at a time keeps each request well inside PHP time/memory limits.
		for ( let i = 0; i < selected.length; i++ ) {
			setProgress( i + 1 );

			try {
				const result = await apiFetch( {
					path: `${ base }/import`,
					method: 'POST',
					data: { id: selected[ i ] },
				} );
				imported.push( result.id );
			} catch ( err ) {
				failed.push( err.message );
			}
		}

		setProgress( null );

		if ( imported.length ) {
			onImported( imported );
		}

		if ( failed.length ) {
			setError( failed[ 0 ] );
		} else {
			onClose();
		}
	};

	let body;

	if ( photos.length ) {
		body = (
			<>
				<ul className="wpmi-grid">
					{ photos.map( ( photo ) => {
						const isSelected = selected.includes( photo.id );

						return (
							<li key={ photo.id }>
								<button
									type="button"
									className={ `wpmi-tile${ isSelected ? ' is-selected' : '' }` }
									aria-pressed={ isSelected }
									onClick={ () => toggle( photo.id ) }
									disabled={ isImporting }
								>
									<img
										src={ photo.thumb }
										alt={ photo.alt }
										loading="lazy"
									/>
									<span className="wpmi-check">
										<CheckIcon />
									</span>
								</button>
								<a
									className="wpmi-author"
									href={ photo.author_url }
									target="_blank"
									rel="noreferrer"
								>
									{ photo.author }
								</a>
							</li>
						);
					} ) }
				</ul>
				{ hasMore && (
					<div className="wpmi-more">
						<button
							type="button"
							className="wpmi-btn"
							disabled={ isLoading }
							onClick={ () => search( searched, page + 1 ) }
						>
							{ isLoading ? (
								<Spinner />
							) : (
								__( 'Load more', 'wp-media-importer' )
							) }
						</button>
					</div>
				) }
			</>
		);
	} else if ( isLoading ) {
		body = (
			<div className="wpmi-empty">
				<Spinner />
			</div>
		);
	} else {
		body = (
			<div className="wpmi-empty">
				<ImageIcon />
				<p>
					{ searched
						? sprintf(
								/* translators: %s: search terms. */
								__(
									'No photos found for “%s”.',
									'wp-media-importer'
								),
								searched
							)
						: sprintf(
								/* translators: %s: provider name. */
								__(
									'Search for free high-quality photos from %s',
									'wp-media-importer'
								),
								provider.label
							) }
				</p>
			</div>
		);
	}

	const requestClose = () => ! isImporting && onClose();

	return (
		<Modal
			title={ sprintf(
				/* translators: %s: provider name. */
				__( 'Import from %s', 'wp-media-importer' ),
				provider.label
			) }
			onRequestClose={ requestClose }
			className="wpmi-modal"
			overlayClassName="wpmi-overlay"
			__experimentalHideHeader
		>
			<div className="wpmi-head">
				<div>
					<h1>
						{ sprintf(
							/* translators: %s: provider name. */
							__( 'Import from %s', 'wp-media-importer' ),
							provider.label
						) }
					</h1>
					<p>
						{ __(
							'Search for free high-quality photos. Imported photos are saved to your media library.',
							'wp-media-importer'
						) }
					</p>
				</div>
				<button
					type="button"
					className="wpmi-close"
					onClick={ requestClose }
					aria-label={ __( 'Close', 'wp-media-importer' ) }
				>
					<CloseIcon />
				</button>
			</div>

			<form
				className="wpmi-search"
				onSubmit={ ( event ) => {
					event.preventDefault();
					search( query.trim(), 1 );
				} }
			>
				<label
					className="wpmi-search-field"
					htmlFor="wpmi-search-input"
				>
					<SearchIcon />
					<span className="screen-reader-text">
						{ __( 'Search', 'wp-media-importer' ) }
					</span>
					<input
						id="wpmi-search-input"
						type="search"
						value={ query }
						onChange={ ( event ) => setQuery( event.target.value ) }
						placeholder={ sprintf(
							/* translators: %s: provider name. */
							__( 'Search %s photos…', 'wp-media-importer' ),
							provider.label
						) }
						// eslint-disable-next-line jsx-a11y/no-autofocus
						autoFocus
					/>
				</label>
				<button
					type="submit"
					className="wpmi-btn"
					disabled={ ! query.trim() || isLoading }
				>
					{ __( 'Search', 'wp-media-importer' ) }
				</button>
			</form>

			{ limitHit && (
				<p className="wpmi-error is-info" role="status">
					{ sprintf(
						/* translators: %d: maximum photos per import. */
						_n(
							'You can add up to %d photo at a time.',
							'You can add up to %d photos at a time.',
							maxSelection,
							'wp-media-importer'
						),
						maxSelection
					) }
				</p>
			) }

			{ error && (
				<p className="wpmi-error" role="alert">
					{ error }
				</p>
			) }

			<div className="wpmi-results">{ body }</div>

			<div className="wpmi-footer">
				<span className="wpmi-count" aria-live="polite">
					{ sprintf(
						/* translators: 1: selected photos, 2: maximum allowed. */
						__( '%1$d of %2$d selected', 'wp-media-importer' ),
						selected.length,
						maxSelection
					) }
				</span>
				<a
					className="wpmi-credit"
					href={ provider.siteUrl }
					target="_blank"
					rel="noreferrer"
				>
					{ sprintf(
						/* translators: %s: provider name. */
						__( 'Photos provided by %s', 'wp-media-importer' ),
						provider.label
					) }
				</a>
				<button
					type="button"
					className="wpmi-btn is-secondary"
					onClick={ onClose }
					disabled={ isImporting }
				>
					{ __( 'Cancel', 'wp-media-importer' ) }
				</button>
				<button
					type="button"
					className="wpmi-btn is-primary"
					disabled={ ! selected.length || isImporting }
					onClick={ importSelected }
				>
					{ isImporting ? (
						<>
							<Spinner />
							{ sprintf(
								/* translators: 1: current photo number, 2: total photos. */
								__(
									'Importing %1$d of %2$d…',
									'wp-media-importer'
								),
								progress,
								selected.length
							) }
						</>
					) : (
						<>
							<CheckIcon />
							{ selected.length
								? sprintf(
										/* translators: %d: number of photos. */
										_n(
											'Add %d photo',
											'Add %d photos',
											selected.length,
											'wp-media-importer'
										),
										selected.length
									)
								: __( 'Add photos', 'wp-media-importer' ) }
						</>
					) }
				</button>
			</div>
		</Modal>
	);
}
