( function( blocks, element, blockEditor, components, serverSideRender, i18n ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var ServerSideRender = serverSideRender;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;

	function registerSimpleBlock( name, title, description ) {
		blocks.registerBlockType( name, {
			title: title,
			icon: 'id-alt',
			category: 'widgets',
			description: description,
			edit: function( props ) {
				return el( ServerSideRender, {
					block: name,
					attributes: props.attributes,
				} );
			},
			save: function() {
				return null; // Dynamic block — rendered server-side via render_callback.
			},
		} );
	}

	registerSimpleBlock(
		'passpress/plan-list',
		__( 'PassPress: Membership Plans', 'passpress' ),
		__( 'Displays the public list of membership plans.', 'passpress' )
	);

	registerSimpleBlock(
		'passpress/my-pass',
		__( 'PassPress: My Pass', 'passpress' ),
		__( 'Displays the logged-in member\'s pass, QR code, and bookings.', 'passpress' )
	);

	registerSimpleBlock(
		'passpress/class-schedule',
		__( 'PassPress: Class Schedule', 'passpress' ),
		__( 'Displays the weekly class schedule with book/waitlist actions.', 'passpress' )
	);

	blocks.registerBlockType( 'passpress/booking-calendar', {
		title: __( 'PassPress: Booking Calendar', 'passpress' ),
		icon: 'id-alt',
		category: 'widgets',
		description: __( 'Displays the booking calendar for one facility.', 'passpress' ),
		attributes: {
			facilityId: { type: 'number', default: 0 },
		},
		edit: function( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			return el( Fragment, {},
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Facility', 'passpress' ) },
						el( TextControl, {
							label: __( 'Facility ID', 'passpress' ),
							help: __( 'Enter the numeric post ID of the Facility to show here (see the Facilities list in wp-admin).', 'passpress' ),
							type: 'number',
							value: attributes.facilityId,
							onChange: function( value ) {
								setAttributes( { facilityId: parseInt( value, 10 ) || 0 } );
							},
						} )
					)
				),
				el( ServerSideRender, {
					block: 'passpress/booking-calendar',
					attributes: attributes,
				} )
			);
		},
		save: function() {
			return null;
		},
	} );

} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender, window.wp.i18n );
