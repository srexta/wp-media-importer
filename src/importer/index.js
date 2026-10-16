/**
 * Adds a "From <Provider>" button per connected provider:
 *
 * - inside every WordPress media browser (Media → Library grid, Add Media,
 *   block editor media library, featured image) via the toolbar of
 *   wp.media.view.AttachmentsBrowser;
 * - beside "Add New Media File" on Media → Library in list mode, which has
 *   no media browser.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { sprintf, __ } from '@wordpress/i18n';

import ImportModal from './ImportModal';
import './style.scss';

const boot = window.wpmiImporter || { providers: [] };

const buttonLabel = ( provider ) =>
	sprintf(
		/* translators: %s: provider name, e.g. Pexels. */
		__( 'From %s', 'wp-media-importer' ),
		provider.label
	);

function open( provider, onImported ) {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );

	const root = createRoot( container );
	const close = () => {
		root.unmount();
		container.remove();
	};

	root.render(
		<ImportModal
			provider={ provider }
			restNamespace={ boot.restNamespace }
			maxSelection={ boot.maxSelection || 5 }
			onImported={ onImported }
			onClose={ close }
		/>
	);
}

/*
 * Media browsers. Runs at load (not domReady) so it is in place before
 * media-grid.js builds the Media → Library grid on DOM ready.
 */
const media = window.wp?.media;

if ( media?.view?.AttachmentsBrowser ) {
	const Browser = media.view.AttachmentsBrowser;

	media.view.AttachmentsBrowser = Browser.extend( {
		createToolbar() {
			Browser.prototype.createToolbar.apply( this, arguments );

			boot.providers.forEach( ( provider, index ) => {
				this.toolbar.set(
					`wpmi-${ provider.slug }`,
					new media.view.Button( {
						text: buttonLabel( provider ),
						className: 'wpmi-open',
						priority: -40 - index,
						click: () =>
							open( provider, ( ids ) =>
								this.addImported( ids )
							),
					} ).render()
				);
			} );
		},

		// Show the new photos at the top of the library and select them.
		async addImported( ids ) {
			const attachments = ids.map( ( id ) => media.attachment( id ) );

			await Promise.all(
				attachments.map( ( attachment ) => attachment.fetch() )
			);

			this.collection.add( attachments, { at: 0 } );

			const selection = this.controller.state?.()?.get( 'selection' );

			if ( selection ) {
				selection.add( attachments );
			}
		},
	} );
}

// Media → Library list mode: header buttons, reload to show new rows.
domReady( () => {
	if ( document.getElementById( 'wp-media-grid' ) ) {
		return;
	}

	let anchor = document.querySelector(
		'.upload-php .wrap .page-title-action'
	);

	if ( ! anchor ) {
		return;
	}

	boot.providers.forEach( ( provider ) => {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'page-title-action wpmi-open';
		button.textContent = buttonLabel( provider );
		button.addEventListener( 'click', () =>
			open( provider, () => window.location.reload() )
		);

		anchor.after( button );
		anchor = button;
	} );
} );
