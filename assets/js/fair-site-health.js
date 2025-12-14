const { __, sprintf } = wp.i18n;

wp.hooks.addFilter( 'site_status_test_result', 'fair/fair-plugin/site-health', function( response_data ) {
	if ( 'dotorg_communication' !== response_data['test'] ) {
		return response_data;
	}

	// parsing the API error message out of a core translation as there is no current way to get that directly
	const regex = new RegExp( fairSiteHealth.errorMessageRegex, 'gim' );
	let matches = regex.exec( response_data.description );
	const errorMessage = (matches && matches[1] != null) ? matches[1] : '';

	response_data.description = sprintf(
		'<p>%s</p>',
		__( 'Communicating with the update servers is used to check for new versions, and to both install and update WordPress core, themes or plugins.', 'fair' )
	);
	switch ( response_data['status'] ) {
		case 'critical' :
			response_data.label = sprintf(
				/* translators: %s is the domain. */
				__( 'Could not reach %s' , 'fair' ),
				fairSiteHealth.defaultRepoDomain
			);
			response_data.description += sprintf(
				'<p>%s</p>',
				sprintf(
					'<span class="error"><span class="screen-reader-text">%s</span></span> %s',
					/* translators: Hidden accessibility text. */
					__( 'Error', 'fair' ),
					sprintf(
						/* translators: 1: The IP address WordPress.org resolves to. 2: The error returned by the lookup. */
						__( 'Your site is unable to reach update server at %1$s, and returned the error: %2$s', 'fair' ),
						fairSiteHealth.repoIPAddress,
						errorMessage
					)
				)
			);
			response_data.actions = sprintf(
				'<p><a href="%s" target="_blank">%s<span class="screen-reader-text"> %s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
				/* translators: Localized Support reference. */
				__( 'https://wordpress.org/support/forums/', 'fair' ),
				__( 'Get help resolving this issue.', 'fair' ),
				/* translators: Hidden accessibility text. */
				__( '(opens in a new tab)', 'fair' )
			);
			break;
		case 'good' :
			response_data.label = sprintf(
				/* translators: %s is the domain. */
				__( 'Can communicate with %s' , 'fair' ),
				fairSiteHealth.defaultRepoDomain
			);
			break;
	}
	return response_data;
});
