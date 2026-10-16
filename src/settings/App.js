/**
 * Settings screen: one API key field per registered provider.
 */
import apiFetch from '@wordpress/api-fetch';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import {
	Button,
	Card,
	CardBody,
	CardFoot,
	CardHead,
	Field,
	Help,
	NumberInput,
	Select,
	TextInput,
} from './components/Controls';

const boot = window.wpmiSettings || { providers: [] };

export default function App() {
	const [ providers, setProviders ] = useState( boot.providers );
	const [ image, setImage ] = useState( boot.image );
	const [ imageDirty, setImageDirty ] = useState( false );
	// Only keys the user typed are sent; untouched providers keep their key.
	const [ drafts, setDrafts ] = useState( {} );
	const [ visible, setVisible ] = useState( {} );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const isDirty = Object.keys( drafts ).length > 0 || imageDirty;

	const setImageField = ( key, value ) => {
		setImage( { ...image, [ key ]: value } );
		setImageDirty( true );
	};

	const save = async ( data ) => {
		setIsSaving( true );
		setNotice( null );

		try {
			const updated = await apiFetch( {
				path: boot.restPath,
				method: 'POST',
				data,
			} );
			setProviders( updated.providers );
			setImage( updated.image );
			setDrafts( {} );
			setImageDirty( false );
			setNotice( {
				type: 'success',
				text: __( 'Settings saved.', 'wp-media-importer' ),
			} );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				text:
					error.message ||
					__( 'Could not save settings.', 'wp-media-importer' ),
			} );
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<div className="wpmi-wrap">
			<div className="page-head">
				<div>
					<h1>{ __( 'Media Importer', 'wp-media-importer' ) }</h1>
					<p className="sub">
						{ __(
							'Connect stock photo sites, then use the buttons on Media → Library to import photos.',
							'wp-media-importer'
						) }
					</p>
				</div>
			</div>

			{ notice && (
				<div
					className={ `wpmi-notice is-${ notice.type }` }
					role="status"
				>
					{ notice.text }
				</div>
			) }

			<Card>
				<CardHead title={ __( 'API keys', 'wp-media-importer' ) }>
					{ __(
						'Keys are stored on your server and never shown in full again after saving.',
						'wp-media-importer'
					) }
				</CardHead>
				<CardBody>
					{ providers.map( ( provider ) => {
						const id = `wpmi-key-${ provider.slug }`;

						if ( ! provider.requiresKey ) {
							return (
								<Field
									key={ provider.slug }
									label={ provider.label }
								>
									<Help>
										{ __(
											'No API key needed — ready to use. Only images licensed for commercial use are shown.',
											'wp-media-importer'
										) }
									</Help>
								</Field>
							);
						}

						return (
							<Field
								key={ provider.slug }
								label={ provider.label }
								htmlFor={ id }
								description={
									<a
										href={ provider.keyUrl }
										target="_blank"
										rel="noreferrer"
									>
										{ __(
											'Get a free API key ↗',
											'wp-media-importer'
										) }
									</a>
								}
							>
								<div className="input-group">
									<TextInput
										id={ id }
										type={
											visible[ provider.slug ]
												? 'text'
												: 'password'
										}
										className="mono"
										autoComplete="off"
										placeholder={
											provider.masked ||
											__(
												'Paste your API key',
												'wp-media-importer'
											)
										}
										value={ drafts[ provider.slug ] }
										onChange={ ( value ) =>
											setDrafts( {
												...drafts,
												[ provider.slug ]: value,
											} )
										}
									/>
									<Button
										onClick={ () =>
											setVisible( {
												...visible,
												[ provider.slug ]:
													! visible[ provider.slug ],
											} )
										}
									>
										{ visible[ provider.slug ]
											? __( 'Hide', 'wp-media-importer' )
											: __(
													'Show',
													'wp-media-importer'
												) }
									</Button>
									{ provider.masked && (
										<Button
											variant="danger"
											disabled={ isSaving }
											onClick={ () =>
												save( {
													[ provider.slug ]: '',
												} )
											}
										>
											{ __(
												'Remove',
												'wp-media-importer'
											) }
										</Button>
									) }
								</div>
								<Help>
									{ provider.masked
										? sprintf(
												/* translators: %s: masked API key. */
												__(
													'Connected — key %s saved.',
													'wp-media-importer'
												),
												provider.masked
											)
										: __(
												'Not connected.',
												'wp-media-importer'
											) }
								</Help>
							</Field>
						);
					} ) }
				</CardBody>
			</Card>

			<Card>
				<CardHead
					title={ __( 'Image processing', 'wp-media-importer' ) }
				>
					{ __(
						'Applied to every photo as it is imported. Photos already in the library are not changed.',
						'wp-media-importer'
					) }
				</CardHead>
				<CardBody>
					<Field
						label={ __( 'Photos per import', 'wp-media-importer' ) }
						htmlFor="wpmi-max-selection"
						description={ __(
							'The most photos someone can select and add in one go.',
							'wp-media-importer'
						) }
					>
						<NumberInput
							id="wpmi-max-selection"
							min={ 1 }
							max={ 50 }
							value={ image.max_selection }
							onChange={ ( value ) =>
								setImageField( 'max_selection', value )
							}
						/>
					</Field>
					<Field
						label={ __( 'Maximum size', 'wp-media-importer' ) }
						htmlFor="wpmi-max-width"
						description={ __(
							'Larger photos are scaled down to fit. Smaller ones are never enlarged. Use 0 for no limit.',
							'wp-media-importer'
						) }
					>
						<div className="input-group">
							<NumberInput
								id="wpmi-max-width"
								min={ 0 }
								max={ 10000 }
								value={ image.max_width }
								onChange={ ( value ) =>
									setImageField( 'max_width', value )
								}
								aria-label={ __(
									'Maximum width in pixels',
									'wp-media-importer'
								) }
							/>
							<span>×</span>
							<NumberInput
								min={ 0 }
								max={ 10000 }
								value={ image.max_height }
								onChange={ ( value ) =>
									setImageField( 'max_height', value )
								}
								aria-label={ __(
									'Maximum height in pixels',
									'wp-media-importer'
								) }
							/>
							<span>px</span>
						</div>
					</Field>
					<Field
						label={ __( 'Quality', 'wp-media-importer' ) }
						htmlFor="wpmi-quality"
						description={ __(
							'Lower means smaller files. 82 is the WordPress default and looks the same as the original to most people.',
							'wp-media-importer'
						) }
					>
						<div className="range">
							<input
								id="wpmi-quality"
								type="range"
								min={ 1 }
								max={ 100 }
								value={ image.quality }
								onChange={ ( event ) =>
									setImageField(
										'quality',
										Number( event.target.value )
									)
								}
							/>
							<output htmlFor="wpmi-quality">
								{ image.quality }
							</output>
						</div>
					</Field>
					<Field
						label={ __( 'Format', 'wp-media-importer' ) }
						htmlFor="wpmi-format"
						description={ __(
							'WebP files are usually about a third smaller than JPEG at the same quality.',
							'wp-media-importer'
						) }
					>
						<Select
							id="wpmi-format"
							value={ image.format }
							onChange={ ( value ) =>
								setImageField( 'format', value )
							}
							options={ [
								{
									value: 'original',
									label: __(
										'Keep original (JPEG/PNG)',
										'wp-media-importer'
									),
								},
								{
									value: 'webp',
									label: __(
										'Convert to WebP',
										'wp-media-importer'
									),
									disabled: ! boot.webpSupported,
								},
							] }
						/>
						{ ! boot.webpSupported && (
							<Help>
								{ __(
									'Your server’s image library cannot write WebP, so this option is unavailable.',
									'wp-media-importer'
								) }
							</Help>
						) }
					</Field>
				</CardBody>
				<CardFoot>
					<Button
						variant="primary"
						disabled={ ! isDirty || isSaving }
						onClick={ () =>
							save( {
								keys: drafts,
								...( imageDirty ? { image } : {} ),
							} )
						}
					>
						{ isSaving
							? __( 'Saving…', 'wp-media-importer' )
							: __( 'Save changes', 'wp-media-importer' ) }
					</Button>
				</CardFoot>
			</Card>
		</div>
	);
}
