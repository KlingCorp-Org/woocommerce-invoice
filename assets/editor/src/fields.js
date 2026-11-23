/**
 * Generic setting field renderer based on the PHP schema.
 */
import {
	CheckboxControl,
	SelectControl,
	TextControl,
	TextareaControl,
	Button,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export function ColorField( { label, value, onChange, allowAccent = true } ) {
	const isAccent = value === 'accent';
	return (
		<div className="odocs-color-field components-base-control">
			<span className="components-base-control__label">{ label }</span>
			<div className="odocs-color-row">
				<input
					type="color"
					value={ value && ! isAccent ? value : '#000000' }
					onChange={ ( e ) => onChange( e.target.value ) }
					aria-label={ label }
				/>
				<span className="odocs-color-value">
					{ isAccent ? __( 'Accent colour', 'order-documents' ) : value || __( 'Default', 'order-documents' ) }
				</span>
				{ allowAccent && ! isAccent && (
					<Button variant="link" onClick={ () => onChange( 'accent' ) }>
						{ __( 'Use accent', 'order-documents' ) }
					</Button>
				) }
				{ value && (
					<Button variant="link" isDestructive onClick={ () => onChange( '' ) }>
						{ __( 'Clear', 'order-documents' ) }
					</Button>
				) }
			</div>
		</div>
	);
}

export default function Field( { field, value, onChange } ) {
	const label = field.label;
	switch ( field.type ) {
		case 'checkbox':
			return (
				<CheckboxControl
					__nextHasNoMarginBottom
					label={ label }
					help={ field.description || undefined }
					checked={ !! Number( value ) }
					onChange={ ( checked ) => onChange( checked ? 1 : 0 ) }
				/>
			);
		case 'number':
			return (
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					type="number"
					label={ label }
					value={ value ?? '' }
					min={ field.min }
					max={ field.max }
					step={ field.step || 1 }
					onChange={ ( v ) => onChange( v === '' ? 0 : Number( v ) ) }
				/>
			);
		case 'select': {
			const options = Object.entries( field.options || {} ).map( ( [ v, l ] ) => ( { value: v, label: l } ) );
			return (
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ label }
					value={ String( value ?? '' ) }
					options={ options }
					onChange={ onChange }
				/>
			);
		}
		case 'color':
			return <ColorField label={ label } value={ value || '' } onChange={ onChange } allowAccent={ field.allowAccent !== false } />;
		case 'textarea':
			return (
				<TextareaControl
					__nextHasNoMarginBottom
					label={ label }
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
					value={ value || '' }
					placeholder={ field.placeholder || '' }
					onChange={ onChange }
				/>
			);
	}
}
