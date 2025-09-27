<?php
/**
 * Packages WP_CLI bootstrap.
 *
 * @package FAIR
 */

namespace FAIR\Packages\WP_CLI;

use function FAIR\is_wp_cli;

/**
 * Bootstrap.
 *
 * @return void
 */
function bootstrap(): void {
	if ( ! is_wp_cli() ) {
		return;
	}

	Compat\bootstrap();
}
