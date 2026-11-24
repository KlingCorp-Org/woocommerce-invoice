/**
 * Column proportion glyphs and the "Add row" layout picker.
 */
import { Button, Dropdown } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { plus } from '@wordpress/icons';
import { ROW_PRESETS } from './utils';

export function ColumnGlyph( { widths, small = false } ) {
	return (
		<span
			className={ 'odocs-glyph' + ( small ? ' is-small' : '' ) }
			aria-hidden="true"
		>
			{ widths.map( ( w, i ) => (
				<span key={ i } style={ { flexGrow: Number( w ) || 1 } } />
			) ) }
		</span>
	);
}

const ALL = [
	...ROW_PRESETS[ 1 ],
	...ROW_PRESETS[ 2 ].slice( 0, 3 ),
	...ROW_PRESETS[ 3 ].slice( 0, 2 ),
];

export function RowPresetGrid( { onPick } ) {
	return (
		<div className="odocs-row-presets">
			{ ALL.map( ( preset ) => (
				<button
					key={ preset.join( '-' ) }
					type="button"
					className="odocs-preset"
					onClick={ () => onPick( preset ) }
				>
					<ColumnGlyph widths={ preset } />
					<span>
						{ preset.length === 1
							? __( 'Full width', 'order-documents' )
							: preset.map( Math.round ).join( ' / ' ) }
					</span>
				</button>
			) ) }
		</div>
	);
}

export default function RowLayoutPicker( {
	onPick,
	label = __( 'Add row', 'order-documents' ),
	variant = 'secondary',
	className = '',
} ) {
	return (
		<Dropdown
			className={ className }
			popoverProps={ { placement: 'top-start' } }
			contentClassName="odocs-popover"
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					variant={ variant }
					icon={ plus }
					onClick={ onToggle }
					aria-expanded={ isOpen }
					__next40pxDefaultSize
				>
					{ label }
				</Button>
			) }
			renderContent={ ( { onClose } ) => (
				<div className="odocs-popover-body">
					<p className="odocs-popover-title">
						{ __( 'Choose a row layout', 'order-documents' ) }
					</p>
					<RowPresetGrid
						onPick={ ( preset ) => {
							onPick( preset );
							onClose();
						} }
					/>
				</div>
			) }
		/>
	);
}
