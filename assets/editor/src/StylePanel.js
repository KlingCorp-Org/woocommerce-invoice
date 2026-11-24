/**
 * Global design settings (shared by all templates and documents), grouped.
 */
import { useState } from '@wordpress/element';
import { Button, PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { link, linkOff } from '@wordpress/icons';
import Field, { NumberField } from './fields';

const GROUPS = [
	{
		title: __( 'Colours', 'order-documents' ),
		ids: [ 'accent_color', 'text_color', 'muted_color' ],
	},
	{
		title: __( 'Typography', 'order-documents' ),
		ids: [ 'font_family', 'font_size' ],
	},
	{
		title: __( 'Logo and tables', 'order-documents' ),
		ids: [ 'logo_height', 'table_style' ],
	},
	{
		title: __( 'Page', 'order-documents' ),
		ids: [
			'margin_top',
			'margin_right',
			'margin_bottom',
			'margin_left',
			'page_numbers',
		],
	},
];
const MARGINS = [
	'margin_top',
	'margin_right',
	'margin_bottom',
	'margin_left',
];

function Margins( { fields, style, shown, setStyle } ) {
	const values = MARGINS.map( ( id ) => Number( shown( id ) ) );
	const [ linked, setLinked ] = useState(
		values.every( ( v ) => v === values[ 0 ] )
	);
	const byId = Object.fromEntries( fields.map( ( f ) => [ f.id, f ] ) );
	const change = ( id, v ) => {
		const next = { ...style };
		( linked ? MARGINS : [ id ] ).forEach( ( m ) => ( next[ m ] = v ) );
		setStyle( next, 'margins' );
	};
	return (
		<div className="odocs-margins">
			<div className="odocs-margins-head">
				<span className="odocs-label">
					{ __( 'Margins', 'order-documents' ) }
				</span>
				<Button
					size="small"
					icon={ linked ? link : linkOff }
					isPressed={ linked }
					label={
						linked
							? __( 'Unlink sides', 'order-documents' )
							: __( 'Link sides', 'order-documents' )
					}
					onClick={ () => setLinked( ! linked ) }
				/>
			</div>
			<div className="odocs-two">
				{ MARGINS.filter( ( id ) => byId[ id ] ).map( ( id ) => (
					<NumberField
						key={ id }
						label={ byId[ id ].label.replace( / margin/i, '' ) }
						value={ shown( id ) }
						min={ byId[ id ].min ?? 0 }
						max={ byId[ id ].max ?? 60 }
						step={ byId[ id ].step || 1 }
						onChange={ ( v ) => change( id, v ) }
					/>
				) ) }
			</div>
		</div>
	);
}

export default function StylePanel( {
	fields,
	style,
	setStyle,
	effective = {},
} ) {
	const known = GROUPS.flatMap( ( g ) => g.ids );
	const rest = fields.filter( ( f ) => ! known.includes( f.id ) );
	const groups = rest.length
		? [
				...GROUPS,
				{
					title: __( 'More', 'order-documents' ),
					ids: rest.map( ( f ) => f.id ),
				},
		  ]
		: GROUPS;
	const byId = Object.fromEntries( fields.map( ( f ) => [ f.id, f ] ) );
	// Templates replace untouched defaults with their own values: show what is really used.
	const shown = ( id ) => ( id in effective ? effective[ id ] : style[ id ] );
	const fromTemplate = ( id ) =>
		id in effective &&
		String( effective[ id ] ).toLowerCase() !==
			String( style[ id ] ).toLowerCase();
	const renderField = ( id ) =>
		byId[ id ] && (
			<Field
				key={ id }
				field={ {
					...byId[ id ],
					allowAccent: false,
					description: fromTemplate( id )
						? __( 'Set by this template.', 'order-documents' )
						: byId[ id ].description,
				} }
				value={ shown( id ) }
				onChange={ ( v ) =>
					setStyle( { ...style, [ id ]: v }, 'style:' + id )
				}
			/>
		);
	return groups.map( ( g, i ) => (
		<PanelBody key={ g.title } title={ g.title } initialOpen={ i === 0 }>
			<div className="odocs-fields">
				{ g.ids.includes( 'margin_top' ) ? (
					<>
						<Margins
							fields={ fields }
							style={ style }
							shown={ shown }
							setStyle={ setStyle }
						/>
						{ g.ids
							.filter( ( id ) => ! MARGINS.includes( id ) )
							.map( renderField ) }
					</>
				) : (
					g.ids.map( renderField )
				) }
			</div>
		</PanelBody>
	) );
}
