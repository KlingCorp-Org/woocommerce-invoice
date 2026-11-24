/**
 * Setting fields rendered from the PHP schema.
 */
import {
	BaseControl,
	Button,
	ColorIndicator,
	ColorPalette,
	Dropdown,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalToggleGroupControlOptionIcon as ToggleGroupControlOptionIcon,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	Icon,
	alignLeft,
	alignCenter,
	alignRight,
	check,
} from '@wordpress/icons';

const UNITS = [ 'mm', 'pt', '%', 'px' ];

/**
 * Splits "Font size (pt, 0 = default)" into label "Font size", unit "pt"
 * and help "0 = default".
 */
export function parseLabel( label = '' ) {
	let text = String( label );
	let unit = '';
	let help = '';
	const m = text.match( /^(.*?)\s*\(([^)]*)\)\s*$/ );
	if ( m ) {
		const parts = m[ 2 ].split( ',' ).map( ( p ) => p.trim() );
		if ( UNITS.includes( parts[ 0 ] ) ) {
			text = m[ 1 ];
			unit = parts.shift();
			help = parts.join( ', ' );
		}
	}
	const pct = text.match( /^(.*?)\s*%$/ );
	if ( ! unit && pct ) {
		text = pct[ 1 ];
		unit = '%';
	}
	return { text, unit, help };
}

export function NumberField( {
	label,
	value,
	onChange,
	min,
	max,
	step = 1,
	help,
} ) {
	const parsed = parseLabel( label );
	return (
		<NumberControl
			__next40pxDefaultSize
			label={ parsed.text }
			help={ help || parsed.help || undefined }
			value={ value ?? '' }
			min={ min }
			max={ max }
			step={ step }
			spinControls="custom"
			suffix={
				parsed.unit ? (
					<span className="odocs-unit">{ parsed.unit }</span>
				) : undefined
			}
			onChange={ ( v ) =>
				onChange( v === '' || v === undefined ? 0 : Number( v ) )
			}
		/>
	);
}

const ALIGN_ICONS = { left: alignLeft, center: alignCenter, right: alignRight };

function isAlignField( options ) {
	const keys = Object.keys( options || {} ).filter( ( k ) => k !== '' );
	return keys.length && keys.every( ( k ) => k in ALIGN_ICONS );
}

export function AlignField( { label, value, onChange, options } ) {
	return (
		<ToggleGroupControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			isDeselectable
			label={ label }
			help={
				value
					? undefined
					: __( 'Uses the template default.', 'order-documents' )
			}
			value={ value || undefined }
			onChange={ ( v ) => onChange( v || '' ) }
		>
			{ Object.keys( options )
				.filter( ( k ) => k in ALIGN_ICONS )
				.map( ( k ) => (
					<ToggleGroupControlOptionIcon
						key={ k }
						value={ k }
						icon={ ALIGN_ICONS[ k ] }
						label={ options[ k ] }
					/>
				) ) }
		</ToggleGroupControl>
	);
}

const NEUTRALS = [
	{ name: __( 'Black', 'order-documents' ), color: '#000000' },
	{ name: __( 'Charcoal', 'order-documents' ), color: '#1e1e1e' },
	{ name: __( 'Dark grey', 'order-documents' ), color: '#50575e' },
	{ name: __( 'Grey', 'order-documents' ), color: '#8c8f94' },
	{ name: __( 'Light grey', 'order-documents' ), color: '#dcdcde' },
	{ name: __( 'White', 'order-documents' ), color: '#ffffff' },
];

export const KEYWORDS = [
	{ value: 'accent', key: 'accent', name: __( 'Accent', 'order-documents' ) },
	{
		value: 'accent-light',
		key: 'accent_light',
		name: __( 'Accent, light', 'order-documents' ),
	},
	{ value: 'tint', key: 'tint', name: __( 'Tint', 'order-documents' ) },
	{ value: 'line', key: 'line', name: __( 'Line', 'order-documents' ) },
];

export function resolveColor( value, palette = {} ) {
	const kw = KEYWORDS.find( ( k ) => k.value === value );
	return kw ? palette[ kw.key ] || '' : value || '';
}

export function ColorField( {
	label,
	value,
	onChange,
	allowKeywords = true,
	palette = {},
	help,
} ) {
	const kw = KEYWORDS.find( ( k ) => k.value === value );
	const shown = resolveColor( value, palette );
	const name = kw
		? kw.name
		: value
		? value.toUpperCase()
		: __( 'Default', 'order-documents' );
	return (
		<BaseControl
			__nextHasNoMarginBottom
			label={ label }
			help={ help }
			className="odocs-color-field"
		>
			<Dropdown
				className="odocs-color-dropdown"
				contentClassName="odocs-color-popover"
				popoverProps={ {
					placement: 'left-start',
					offset: 36,
					shift: true,
				} }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						className="odocs-color-toggle"
						onClick={ onToggle }
						aria-expanded={ isOpen }
						__next40pxDefaultSize
					>
						<ColorIndicator
							colorValue={ shown || undefined }
							className={ shown ? '' : 'is-empty' }
						/>
						<span className="odocs-color-name">{ name }</span>
						{ kw && shown && (
							<span className="odocs-color-hex">
								{ shown.toUpperCase() }
							</span>
						) }
					</Button>
				) }
				renderContent={ () => (
					<div className="odocs-color-content">
						{ allowKeywords && (
							<>
								<p className="odocs-color-group">
									{ __(
										'Template colours',
										'order-documents'
									) }
								</p>
								<div className="odocs-swatches">
									{ KEYWORDS.map( ( k ) => (
										<button
											key={ k.value }
											type="button"
											className={
												'odocs-swatch' +
												( value === k.value
													? ' is-active'
													: '' )
											}
											style={ {
												background:
													palette[ k.key ] || '#ccc',
											} }
											aria-label={ k.name }
											title={ k.name }
											onClick={ () =>
												onChange( k.value )
											}
										>
											{ value === k.value && (
												<Icon
													className="odocs-swatch-check"
													icon={ check }
													size={ 20 }
												/>
											) }
										</button>
									) ) }
								</div>
								<p className="odocs-color-group">
									{ __( 'Colours', 'order-documents' ) }
								</p>
							</>
						) }
						<ColorPalette
							__experimentalIsRenderedInSidebar
							colors={ NEUTRALS }
							value={ kw ? undefined : value || undefined }
							onChange={ ( v ) => onChange( v || '' ) }
							clearable={ false }
						/>
						<div className="odocs-color-actions">
							<Button
								variant="tertiary"
								size="compact"
								disabled={ ! value }
								onClick={ () => onChange( '' ) }
							>
								{ __( 'Reset to default', 'order-documents' ) }
							</Button>
						</div>
					</div>
				) }
			/>
		</BaseControl>
	);
}

export default function Field( { field, value, onChange, palette } ) {
	const label = field.label;
	switch ( field.type ) {
		case 'checkbox':
			return (
				<ToggleControl
					__nextHasNoMarginBottom
					label={ label }
					help={ field.description || undefined }
					checked={ !! Number( value ) }
					onChange={ ( checked ) => onChange( checked ? 1 : 0 ) }
				/>
			);
		case 'number':
			return (
				<NumberField
					label={ label }
					help={ field.description }
					value={ value }
					min={ field.min }
					max={ field.max }
					step={ field.step || 1 }
					onChange={ onChange }
				/>
			);
		case 'select': {
			const options = field.options || {};
			if ( isAlignField( options ) ) {
				return (
					<AlignField
						label={ label }
						value={ value }
						options={ options }
						onChange={ onChange }
					/>
				);
			}
			const entries = Object.entries( options );
			if (
				entries.length >= 2 &&
				entries.length <= 3 &&
				entries.every( ( [ , l ] ) => String( l ).length <= 12 )
			) {
				return (
					<ToggleGroupControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						isBlock
						label={ label }
						help={ field.description || undefined }
						value={ String( value ?? '' ) }
						onChange={ ( v ) => onChange( v ?? '' ) }
					>
						{ entries.map( ( [ v, l ] ) => (
							<ToggleGroupControlOption
								key={ v }
								value={ v }
								label={ l }
							/>
						) ) }
					</ToggleGroupControl>
				);
			}
			return (
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ label }
					help={ field.description || undefined }
					value={ String( value ?? '' ) }
					options={ entries.map( ( [ v, l ] ) => ( {
						value: v,
						label: l,
					} ) ) }
					onChange={ onChange }
				/>
			);
		}
		case 'color':
			return (
				<ColorField
					label={ label }
					help={ field.description || undefined }
					value={ value || '' }
					onChange={ onChange }
					palette={ palette }
					allowKeywords={ field.allowAccent !== false }
				/>
			);
		case 'textarea':
			return (
				<TextareaControl
					__nextHasNoMarginBottom
					label={ label }
					help={ field.description || undefined }
					value={ value || '' }
					rows={ 4 }
					onChange={ onChange }
				/>
			);
		default:
			return (
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ label }
					help={ field.description || undefined }
					value={ value || '' }
					placeholder={ field.placeholder || '' }
					onChange={ onChange }
				/>
			);
	}
}
