<?php
/**
 * Adds compatibility for FAIR packages with built-in WP-CLI commands.
 *
 * @package FAIR
 */

namespace FAIR\Packages\WP_CLI\Compat;

use FAIR\Packages as Packages;
use function WP_CLI\Utils\get_flag_value as get_flag_value;
use WP_CLI;

/**
 * Bootstrap.
 */
function bootstrap(): void {
	WP_CLI::add_hook( 'before_run_command', __NAMESPACE__ . '\\maybe_handle_command' );
}

/**
 * Maybe prime the environment for a command, or intercept it.
 *
 * - Check for a valid DID and supported commands.
 * - Run the appropriate priming function.
 * - If the command's arguments need to change, re-run the command and halt.
 *
 * @param array $args       The command line arguments.
 * @param array $assoc_args The associative command line arguments.
 * @return void
 */
function maybe_handle_command( array $args = [], array $assoc_args = [] ): void {
	$runner = WP_CLI::get_runner();
	$command_to_run = $runner->find_command_to_run( $args );

	if (
		is_string( $command_to_run ) // There was an error. WP_CLI will handle it.
		|| ! isset( $command_to_run[2][1] ) // There is no subcommand.
	) {
		return;
	}

	list( $command, $subcommand ) = $command_to_run[2];
	// TODO: Add theme support.
	if ( $command !== 'plugin' ) {
		return;
	}

	$items = (array) array_slice( $args, 2 );
	$dids = array_filter(
		$items,
		function ( $did ) {
			if ( ! str_starts_with( $did, 'did:' ) ) {
				return false;
			}

			$parsed_did = Packages\parse_did( $did );
			if ( is_wp_error( $parsed_did ) ) {
				WP_CLI::warning(
					sprintf(
						/* translators: 1: The DID, 2: The error message. */
						__( 'Could not parse %1$s - %2$s', 'fair' ),
						$did,
						$parsed_did->get_error_message()
					)
				);
				return false;
			}
			return true;
		}
	);

	if ( $dids ) {
		handle_command( $command, $subcommand, $args, $assoc_args, $items, $dids );
	}
}

/**
 * Handle a command with DIDs.
 *
 * This may prime the environment, or re-run the command and halt execution.
 *
 * @param string   $command    The main command.
 * @param string   $subcommand The subcommand.
 * @param string[] $args       The command line arguments.
 * @param string[] $assoc_args The associative command line arguments.
 * @param string[] $items      The command's items, such as slugs or DIDs.
 * @param string[] $dids       The DIDs to replace.
 * @return void
 */
function handle_command( string $command, string $subcommand, array $args, array $assoc_args, array $items, array $dids ): void {
	$hashed_items = replace_dids_with_hashed_filenames( $items, $dids );
	if ( $hashed_items === array_values( $items ) ) {
		return;
	}

	force_detection_by_did( $dids );

	switch ( $subcommand ) {
		case 'activate':
		case 'deactivate':
		case 'delete':
		case 'get':
		case 'is-active':
		case 'path':
		case 'status':
		case 'toggle':
		case 'uninstall':
		case 'update':
			$args = array_merge( [ $command, $subcommand ], $hashed_items );
			run_command_and_halt( $args, $assoc_args );
			break;
		case 'search':
			prime_for_search();
			break;
		case 'install':
			prime_for_install( $dids );

			if (
				get_flag_value( $assoc_args, 'activate' )
				|| get_flag_value( $assoc_args, 'activate-network' )
			) {
				intercept_install( $args, $assoc_args, $items );
			}
			break;
		case 'verify-checksums':
			WP_CLI::log( __( 'The verify-checksums command is not currently supported for DIDs.', 'fair' ) );
			WP_CLI::halt( 1 );
			break;
		default:
			// Do nothing.
			break;
	}
}

/**
 * Force WP to detect plugins by their DIDs.
 *
 * This adds a filter to 'all_plugins' that duplicates entries
 * for the hashed filenames to also be accessible by their DIDs.
 *
 * @param string[] $dids The DIDs to force detection for.
 * @return void
 */
function force_detection_by_did( array $dids ): void {
	add_filter(
		'all_plugins',
		function ( $all_plugins ) use ( $dids ) {
			foreach ( $dids as $did ) {
				$metadata = Packages\fetch_package_metadata( $did );
				if ( is_wp_error( $metadata ) ) {
					WP_CLI::warning(
						sprintf(
							/* translators: 1: The DID, 2: The error message. */
							__( 'Could not retrieve metadata for %1$s - %2$s', 'fair' ),
							$did,
							$metadata->get_error_message()
						)
					);
					continue;
				}

				$filename = Packages\get_hashed_filename( $metadata );
				if ( isset( $all_plugins[ $filename ] ) ) {
					$all_plugins[ $did ] = $all_plugins[ $filename ];
				}
			}
			return $all_plugins;
		}
	);
}

/**
 * Prime the environment for the search command.
 *
 * @return void
 */
function prime_for_search(): void {
	add_filter( 'plugins_api', '\\FAIR\\Packages\\search_by_did', 10, 3 );
}

/**
 * Prime the environment for the install command.
 *
 * @param string[] $dids The DIDs to install.
 * @return void
 */
function prime_for_install( array $dids ): void {
	array_map( 'FAIR\\Packages\\add_package_to_release_cache', $dids );
	add_filter( 'plugins_api', 'FAIR\\Packages\\get_plugin_information', 10, 3 );
	add_filter( 'upgrader_package_options', 'FAIR\\Packages\\cache_did_for_install', 10, 1 );
	add_filter( 'upgrader_pre_download', 'FAIR\\Packages\\upgrader_pre_download', 10, 1 );

	if ( apply_filters( 'fair.packages.updater.verify_signatures', true ) ) {
		add_filter( 'upgrader_pre_download', 'FAIR\\Updater\\verify_signature_on_download', 9, 4 );
	}
}

/**
 * Intercept the install command.
 *
 * After installation, WP-CLI cannot find the plugin by DID.
 * Forced detection for other commands does not help here.
 *
 * - Run the install command without activation.
 * - Run the activate command if installation was successful.
 *
 * @param string[] $args       The command line arguments.
 * @param string[] $assoc_args The associative command line arguments.
 * @param string[] $items      The command's items, such as slugs or DIDs.
 * @return void Does not return. Halts execution after running the command.
 */
function intercept_install( array $args, array $assoc_args, array $items ): void {
	static $intercepted = false;
	if ( $intercepted ) {
		return;
	}
	$intercepted = true;

	try {
		$network_activate = get_flag_value( $assoc_args, 'activate-network' );
		unset( $assoc_args['activate'], $assoc_args['activate-network'] );

		// The install command needs to run first, without activation.
		WP_CLI::run_command( $args, $assoc_args );

		if ( $network_activate ) {
			$assoc_args['network'] = 1;
		}
		$activation_args = array_merge( [ 'plugin', 'activate' ], $items );
		WP_CLI::run_command( $activation_args, $assoc_args );
		WP_CLI::halt( 0 );
	} catch ( WP_CLI\ExitException $e ) {
		WP_CLI::halt( $e->getCode() );
	}
}

/**
 * Run a WP-CLI command and halt execution.
 *
 * Includes infinite loop protection.
 *
 * @param array $args       The command line arguments.
 * @param array $assoc_args The associative command line arguments.
 * @return void Does not return. Halts execution after running the command.
 */
function run_command_and_halt( array $args, array $assoc_args = [] ): void {
	static $already_ran = [];

	if ( ! isset( $already_ran[ $args[1] ] ) ) {
		$already_ran[ $args[1] ] = true;

		try {
			WP_CLI::run_command( $args, $assoc_args );
			WP_CLI::halt( 0 );
		} catch ( WP_CLI\ExitException $e ) {
			WP_CLI::halt( $e->getCode() );
		}
	}
}

/**
 * Replace DIDs in an array of items with their hashed filenames.
 *
 * @param string[] $items The command line items.
 * @param string[] $dids  The DIDs to replace.
 * @return string[] The modified items.
 */
function replace_dids_with_hashed_filenames( array $items, array $dids ): array {
	return array_map(
		function ( $item ) use ( $dids ) {
			if ( in_array( $item, $dids, true ) ) {
				$metadata = Packages\fetch_package_metadata( $item );
				if ( is_wp_error( $metadata ) ) {
					WP_CLI::warning(
						sprintf(
							/* translators: 1: The DID, 2: The error message. */
							__( 'Could not retrieve metadata for %1$s - %2$s', 'fair' ),
							$item,
							$metadata->get_error_message()
						)
					);
					return $item;
				}

				return Packages\get_hashed_filename( $metadata );
			}
			return $item;
		},
		$items
	);
}
