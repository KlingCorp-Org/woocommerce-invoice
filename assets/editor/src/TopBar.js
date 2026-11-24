/**
 * Editor header: document/template/data pickers, history, zoom and saving.
 */
import {
	Button,
	Dropdown,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	MenuItemsChoice,
	Tooltip,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	Icon,
	chevronDown,
	chevronLeft,
	undo as undoIcon,
	redo as redoIcon,
	listView,
	drawerRight,
	moreVertical,
	check,
	page as pageIcon,
	reset as resetIcon,
	keyboard,
	fullscreen,
	caution,
} from '@wordpress/icons';

export const SAMPLE = '0';
const ZOOMS = [ 0.5, 0.75, 1, 1.25 ];

function Picker( {
	label,
	value,
	children,
	className = '',
	contentClassName = 'odocs-popover',
	placement = 'bottom-start',
} ) {
	return (
		<Dropdown
			className={ 'odocs-picker ' + className }
			contentClassName={ contentClassName }
			popoverProps={ { placement, offset: 6 } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<button
					type="button"
					className="odocs-picker-toggle"
					onClick={ onToggle }
					aria-expanded={ isOpen }
				>
					<span className="odocs-picker-label">{ label }</span>
					<span className="odocs-picker-value">
						{ value }
						<Icon icon={ chevronDown } size={ 16 } />
					</span>
				</button>
			) }
			renderContent={ children }
		/>
	);
}

export default function TopBar( props ) {
	const {
		config,
		type,
		template,
		orderId,
		orders,
		onType,
		onTemplate,
		onOrder,
		history,
		zoom,
		setZoom,
		scale,
		panes,
		togglePane,
		dirty,
		saving,
		onSave,
		onPreviewPdf,
		onReset,
		canReset,
		wpMenu,
		toggleWpMenu,
		openShortcuts,
		settingsUrl,
	} = props;
	const docType = config.documentTypes.find( ( t ) => t.id === type );
	const tpl = config.templates.find( ( t ) => t.id === template );
	const order = orders.find( ( o ) => String( o.id ) === String( orderId ) );
	const notUsed = docType?.template && docType.template !== template;

	return (
		<header className="odocs-topbar">
			<div className="odocs-topbar-start">
				<Tooltip
					text={ __(
						'Back to Order Documents settings',
						'order-documents'
					) }
				>
					<a
						className="odocs-back"
						href={ settingsUrl }
						aria-label={ __(
							'Back to Order Documents settings',
							'order-documents'
						) }
					>
						<Icon icon={ chevronLeft } size={ 24 } />
					</a>
				</Tooltip>
				<Picker
					label={ __( 'Document', 'order-documents' ) }
					value={ docType?.label || type }
				>
					{ ( { onClose } ) => (
						<MenuGroup>
							<MenuItemsChoice
								value={ type }
								choices={ config.documentTypes.map( ( t ) => ( {
									value: t.id,
									label: t.label,
									info: t.enabled
										? undefined
										: __( 'Turned off', 'order-documents' ),
								} ) ) }
								onSelect={ ( v ) => {
									onClose();
									onType( v );
								} }
							/>
						</MenuGroup>
					) }
				</Picker>
				<Picker
					label={ __( 'Template', 'order-documents' ) }
					value={ tpl?.name || template }
					contentClassName="odocs-popover odocs-template-popover"
				>
					{ ( { onClose } ) => (
						<div className="odocs-popover-body">
							<p className="odocs-popover-title">
								{ __(
									'Choose a template to edit',
									'order-documents'
								) }
							</p>
							<div className="odocs-templates">
								{ config.templates.map( ( t ) => (
									<button
										key={ t.id }
										type="button"
										className={
											'odocs-template' +
											( t.id === template
												? ' is-active'
												: '' )
										}
										onClick={ () => {
											onClose();
											onTemplate( t.id );
										} }
										title={ t.description }
									>
										<span className="odocs-template-thumb">
											{ t.thumbnail ? (
												<img
													src={ t.thumbnail }
													alt=""
													loading="lazy"
												/>
											) : (
												<Icon
													icon={ pageIcon }
													size={ 32 }
												/>
											) }
											{ t.id === template && (
												<Icon
													className="odocs-template-check"
													icon={ check }
													size={ 20 }
												/>
											) }
										</span>
										<span className="odocs-template-name">
											{ t.name }
										</span>
										{ t.id === docType?.template && (
											<span className="odocs-badge">
												{ __(
													'In use',
													'order-documents'
												) }
											</span>
										) }
									</button>
								) ) }
							</div>
						</div>
					) }
				</Picker>
				{ notUsed && (
					<Tooltip
						text={ __(
							'This document type uses another template. Choose templates under Order Documents → Documents.',
							'order-documents'
						) }
					>
						<span className="odocs-chip-warning" tabIndex={ 0 }>
							<Icon icon={ caution } size={ 16 } />
							{ __( 'Not in use', 'order-documents' ) }
						</span>
					</Tooltip>
				) }
				<Picker
					label={ __( 'Preview with', 'order-documents' ) }
					value={
						order
							? order.label
							: __( 'Sample data', 'order-documents' )
					}
					className="odocs-picker-order"
				>
					{ ( { onClose } ) => (
						<MenuGroup className="odocs-order-menu">
							<MenuItemsChoice
								value={ String( orderId ) }
								choices={ [
									{
										value: SAMPLE,
										label: __(
											'Sample data',
											'order-documents'
										),
										info: __(
											'A made-up order with every field filled in',
											'order-documents'
										),
									},
									...orders.map( ( o ) => ( {
										value: String( o.id ),
										label: o.label,
									} ) ),
								] }
								onSelect={ ( v ) => {
									onClose();
									onOrder( v );
								} }
							/>
						</MenuGroup>
					) }
				</Picker>
			</div>

			<div className="odocs-topbar-center">
				<Button
					icon={ undoIcon }
					label={ __( 'Undo', 'order-documents' ) }
					shortcut="Ctrl+Z"
					disabled={ ! history.canUndo }
					onClick={ history.undo }
					accessibleWhenDisabled
				/>
				<Button
					icon={ redoIcon }
					label={ __( 'Redo', 'order-documents' ) }
					shortcut="Ctrl+Shift+Z"
					disabled={ ! history.canRedo }
					onClick={ history.redo }
					accessibleWhenDisabled
				/>
				<DropdownMenu
					icon={ null }
					text={ Math.round( scale * 100 ) + '%' }
					label={ __( 'Zoom', 'order-documents' ) }
					className="odocs-zoom"
					toggleProps={ { className: 'odocs-zoom-toggle' } }
				>
					{ ( { onClose } ) => (
						<MenuGroup>
							<MenuItemsChoice
								value={ String( zoom ) }
								choices={ [
									{
										value: 'fit',
										label: __(
											'Fit to width',
											'order-documents'
										),
									},
									...ZOOMS.map( ( z ) => ( {
										value: String( z ),
										label: Math.round( z * 100 ) + '%',
									} ) ),
								] }
								onSelect={ ( v ) => {
									setZoom(
										v === 'fit' ? 'fit' : Number( v )
									);
									onClose();
								} }
							/>
						</MenuGroup>
					) }
				</DropdownMenu>
			</div>

			<div className="odocs-topbar-end">
				<span
					className={ 'odocs-status' + ( dirty ? ' is-dirty' : '' ) }
					aria-live="polite"
				>
					{ saving
						? __( 'Saving…', 'order-documents' )
						: dirty
						? __( 'Unsaved changes', 'order-documents' )
						: __( 'All changes saved', 'order-documents' ) }
				</span>
				<Button
					icon={ listView }
					label={ __( 'Structure', 'order-documents' ) }
					isPressed={ panes.structure }
					onClick={ () => togglePane( 'structure' ) }
				/>
				<Button
					icon={ drawerRight }
					label={ __( 'Settings', 'order-documents' ) }
					isPressed={ panes.settings }
					onClick={ () => togglePane( 'settings' ) }
				/>
				<Button
					variant="secondary"
					onClick={ onPreviewPdf }
					__next40pxDefaultSize
				>
					{ __( 'Preview PDF', 'order-documents' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ onSave }
					isBusy={ saving }
					disabled={ ! dirty || saving }
					accessibleWhenDisabled
					shortcut="Ctrl+S"
					__next40pxDefaultSize
				>
					{ __( 'Save', 'order-documents' ) }
				</Button>
				<DropdownMenu
					icon={ moreVertical }
					label={ __( 'More', 'order-documents' ) }
					popoverProps={ { placement: 'bottom-end' } }
				>
					{ ( { onClose } ) => (
						<>
							<MenuGroup>
								<MenuItem
									icon={ fullscreen }
									onClick={ () => (
										toggleWpMenu(), onClose()
									) }
									isSelected={ ! wpMenu }
									role="menuitemcheckbox"
								>
									{ __( 'Full screen', 'order-documents' ) }
								</MenuItem>
								<MenuItem
									icon={ keyboard }
									onClick={ () => (
										openShortcuts(), onClose()
									) }
									shortcut="?"
								>
									{ __(
										'Keyboard shortcuts',
										'order-documents'
									) }
								</MenuItem>
							</MenuGroup>
							<MenuGroup>
								<MenuItem
									icon={ resetIcon }
									disabled={ ! canReset }
									onClick={ () => ( onReset(), onClose() ) }
									info={ __(
										'Restore the template’s original layout',
										'order-documents'
									) }
								>
									{ __( 'Reset layout', 'order-documents' ) }
								</MenuItem>
							</MenuGroup>
						</>
					) }
				</DropdownMenu>
			</div>
		</header>
	);
}
