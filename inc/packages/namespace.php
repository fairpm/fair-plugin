<?php
/**
 * Install FAIR packages.
 *
 * @package FAIR
 */

namespace FAIR\Packages;

use const FAIR\CACHE_BASE;
use const FAIR\CACHE_LIFETIME;
use FAIR\Packages\DID\Document as DIDDocument;
use FAIR\Packages\DID\PLC;
use FAIR\Packages\DID\Web;
use FAIR\Updater;
use WP_Error;
use WP_Upgrader;

const CACHE_KEY = CACHE_BASE . 'packages-';
const CACHE_METADATA_DOCUMENTS = CACHE_BASE . 'metadata-documents-';
const CACHE_RELEASE_PACKAGES = CACHE_BASE . 'release-packages';
const CONTENT_TYPE = 'application/json+fair';
const SERVICE_ID = 'FairPackageManagementRepo';

// phpcs:disable WordPress.NamingConventions.ValidVariableName

/**
 * Bootstrap.
 *
 * @return void
 */
function bootstrap() {
	Admin\bootstrap();
}

/**
 * Parse DID.
 *
 * @param string $id DID.
 * @return DID|WP_Error
 */
function parse_did( string $id ) {
	if ( ! str_starts_with( $id, 'did:plc:' ) ) {
		return new WP_Error( 'fair.packages.validate_did.not_did', __( 'ID is not a valid DID.', 'fair' ) );
	}

	$parts = explode( ':', $id, 3 );
	if ( count( $parts ) !== 3 ) {
		return new WP_Error( 'fair.packages.validate_did.not_uri', __( 'DID could not be parsed as a URI.', 'fair' ) );
	}

	switch ( $parts[1] ) {
		case PLC::METHOD:
			return new PLC( $id );

		case Web::METHOD:
			return new Web( $id );

		default:
			return new WP_Error( 'fair.packages.validate_did.invalid_method', __( 'Unsupported DID method.', 'fair' ) );
	}
}

/**
 * Return hash of DID.
 *
 * This ensures a consistent representation of a DID
 * regardless of its method, method-specific-id format, or length.
 *
 * @param  string $id DID
 *
 * @return string|WP_Error
 */
function get_did_hash( string $id ) {
	$did = parse_did( $id );
	if ( is_wp_error( $did ) ) {
		return $did;
	}

	return substr( hash( 'sha256', $did->get_id() ), 0, 6 );
}

/**
 * Get DID document.
 *
 * @param string $id DID.
 * @return DIDDocument|WP_Error
 */
function get_did_document( string $id ) {
	$cached = get_transient( CACHE_METADATA_DOCUMENTS . $id );
	if ( $cached ) {
		return $cached;
	}

	// Parse the DID, then fetch the details.
	$did = parse_did( $id );
	if ( is_wp_error( $did ) ) {
		return $did;
	}

	$document = $did->fetch_document();
	if ( is_wp_error( $document ) ) {
		return $document;
	}
	set_transient( CACHE_METADATA_DOCUMENTS . $id, $document, CACHE_LIFETIME );

	return $document;
}

/**
 * Fetch metadata for a package.
 *
 * @param string $id DID of the package to fetch metadata for.
 * @return MetadataDocument|WP_Error Metadata document on success, WP_Error on failure.
 */
function fetch_package_metadata( string $id ) {
	$document = get_did_document( $id );
	if ( is_wp_error( $document ) ) {
		return $document;
	}

	// Fetch data from the repository.
	$service = $document->get_service( SERVICE_ID );
	if ( empty( $service ) ) {
		return new WP_Error( 'fair.packages.fetch_metadata.no_service', __( 'DID is not a valid package to fetch metadata for.', 'fair' ) );
	}
	$repo_url = $service->serviceEndpoint;

	$metadata = fetch_metadata_doc( $repo_url );

	if ( is_wp_error( $metadata ) ) {
		return $metadata;
	}

	if ( $metadata->id !== $id ) {
		return new WP_Error( 'fair.packages.fetch_metadata.mismatch', __( 'Fetched metadata does not match the requested DID.', 'fair' ) );
	}

	return $metadata;
}

/**
 * Fetch the metadata document for a package.
 *
 * @param string $url URL for the metadata document.
 * @return MetadataDocument|WP_Error
 */
function fetch_metadata_doc( string $url ) {
	$cache_key = CACHE_KEY . md5( $url );
	$response = get_transient( $cache_key );
	$response = fetch_metadata_from_local( $response, $url );

	if ( ! $response ) {
		$options = [
			'headers' => [
				'Accept' => sprintf( '%s;q=1.0, application/json;q=0.8', CONTENT_TYPE ),
			],
		];

		// Set low timeout for local package.
		if ( str_contains( $url, home_url() ) ) {
			$options['timeout'] = 1;
		}
		$response = wp_remote_get( $url, $options );
		$code = wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( $code !== 200 ) {
			return new WP_Error( 'fair.packages.metadata.failure', __( 'HTTP error code received', 'fair' ) );
		}
		set_transient( $cache_key, $response, CACHE_LIFETIME );
	}

	return MetadataDocument::from_response( $response );
}

/**
 * Fetch Metadata from local source.
 *
 * Solves issue where Metadata source is from same site.
 * Mini-FAIR REST endpoint may time out under these circumstances.
 * Directly calling the WP_REST_Request does not return complete data.
 *
 * @param  bool|array $response Response from cache.
 * @param  string $url URI for Metadata.
 * @return bool|array
 */
function fetch_metadata_from_local( $response, $url ) {
	if ( ! $response && str_contains( $url, home_url() ) ) {
		$did = explode( '/', parse_url( $url, PHP_URL_PATH ) );
		$did = array_pop( $did );
		$body = get_transient( 'fair-metadata-endpoint-' . $did );
		$response = [];
		$response = [
			'headers' => [],
			'body' => json_encode( $body ),
		];
		$response = ! $body ? false : $response;
		if ( $response ) {
			set_transient( CACHE_KEY . md5( $url ), $response, CACHE_LIFETIME );
		}
	}

	return $response;
}

/**
 * Select the best release from a list of releases.
 *
 * @param array $releases List of releases to choose from.
 * @param string|null $version Version to select. If null, the latest release is returned.
 * @return ReleaseDocument|null The selected release or null if not found.
 */
function pick_release( array $releases, ?string $version = null ) : ?ReleaseDocument {
	// Sort releases by version, descending.
	usort( $releases, fn ( $a, $b ) => version_compare( $b->version, $a->version ) );

	// If no version is specified, return the latest release.
	if ( empty( $version ) ) {
		return reset( $releases );
	}

	return array_find( $releases, fn ( $release ) => $release->version === $version );
}

/**
 * Get the latest release for a DID.
 *
 * @param  string $id DID.
 *
 * @return ReleaseDocument|WP_Error The latest release, or a WP_Error object on failure.
 */
function get_latest_release_from_did( $id ) {
	$document = get_did_document( $id );
	if ( is_wp_error( $document ) ) {
		return $document;
	}

	$valid_keys = $document->get_fair_signing_keys();
	if ( empty( $valid_keys ) ) {
		return new WP_Error( 'fair.packages.install.no_signing_keys', __( 'DID does not contain valid signing keys.', 'fair' ) );
	}

	$metadata = fetch_package_metadata( $id );
	if ( is_wp_error( $metadata ) ) {
		return $metadata;
	}

	$release = pick_release( $metadata->releases );
	if ( empty( $release ) ) {
		return new WP_Error( 'fair.packages.install.no_releases', __( 'No releases found in the repository.', 'fair' ) );
	}

	return $release;
}

/**
 * Get viable languages for a given locale.
 *
 * Based on the RFC4647 language matching algorithm, with slight modifications.
 * In particular, the base language code (e.g. "de") is treated as equivalent
 * to language-plus-country/region with the same name (e.g. "de-DE").
 *
 * Additionally, for WordPress-compatibility, underscores are treated as
 * separators equivalent to hyphens. The default language is "en-US" or "en".
 *
 * The priority list can be filtered using the
 * `fair.packages.language_priority_list` filter.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc4647
 * @see https://datatracker.ietf.org/doc/html/rfc5646
 *
 * @param string|null $locale Locale to match against. Defaults to the current locale.
 * @return string[]|null Prioritized list of language codes.
 */
function get_language_priority_list( ?string $locale = null ) {
	$locale = $locale ?: get_locale();
	$locale = strtolower( str_replace( '_', '-', $locale ) );
	$langs = [];
	$langs[] = $locale;

	if ( strpos( $locale, '-' ) !== false ) {
		// Add all possible prefixes.
		$i = strlen( $locale );
		do {
			$i = strrpos( substr( $locale, 0, $i ), '-' );
			if ( $i === false ) {
				break;
			}

			// If this is just "x", skip it.
			if ( substr( $locale, $i - 1, 1 ) === 'x' ) {
				continue;
			}

			$langs[] = substr( $locale, 0, $i );
		} while ( $i > 0 );
	}

	/*
	 * Double the primary language code, to catch cases where the
	 * locale matches the country code. (e.g. de becomes de-DE.)
	 */
	$primary = substr( $locale, 0, strpos( $locale, '-' ) );
	$langs[] = $primary . '-' . $primary;

	// Defaults.
	$langs[] = 'en-us';
	$langs[] = 'en';

	/**
	 * Filter the list of languages to prioritize.
	 */
	return apply_filters( 'fair.packages.language_priority_list', $langs, $locale );
}

/**
 * Pick the best matching artifact based on the current locale.
 *
 * Uses the language priority list to pick the best scoring artifact. The
 * algorithm can be overridden by the
 * `fair.packages.pick_artifact_by_lang` filter.
 *
 * @see get_language_priority_list()
 *
 * @param array $artifacts List of artifacts to choose from.
 * @param string|null $locale Locale to match against. Defaults to the current locale.
 * @return stdClass|null The best matching artifact or null if none found.
 */
function pick_artifact_by_lang( array $artifacts, ?string $locale = null ) {
	$langs = get_language_priority_list( $locale );

	// Score artifacts based on match.
	$score_artifact = function ( $artifact ) use ( $langs ) {
		$score = 0;

		// Check for lang match.
		$idx = array_search( strtolower( $artifact->lang ), $langs, true );
		if ( $idx !== false ) {
			$score += ( count( $langs ) - $idx ) * 100;
		}

		return $score;
	};
	usort( $artifacts, function ( $a, $b ) use ( $score_artifact ) {
		$a_score = $score_artifact( $a );
		$b_score = $score_artifact( $b );

		return $b_score <=> $a_score;
	} );

	// Return the best match.
	$selected = reset( $artifacts );

	/**
	 * Filter the selected artifact.
	 */
	return apply_filters( 'fair.packages.pick_artifact_by_lang', $selected, $artifacts, $locale, $langs );
}

/**
 * Get version requirements.
 *
 * @param ReleaseDocument $release Release document.
 *
 * @return array
 */
function version_requirements( ReleaseDocument $release ) {
	$required_versions = [];
	foreach ( $release->requires as $pkg => $vers ) {
		$vers = preg_replace( '/^[^0-9]+/', '', $vers );
		if ( $pkg === 'env:php' ) {
			$required_versions['requires_php'] = $vers;
		}
		if ( $pkg === 'env:wp' ) {
			$required_versions['requires_wp'] = $vers;
		}
	}
	foreach ( $release->suggests as $pkg => $vers ) {
		$vers = preg_replace( '/^[^0-9]+/', '', $vers );
		if ( $pkg === 'env:wp' ) {
			$required_versions['tested_to'] = $vers;
		}
	}

	return $required_versions;
}

/**
 * Get unmet requirements.
 *
 * @param array $requirements Requirements to check. Map of package names to requirement strings.
 * @return array Map of package names to unmet requirements.
 */
function get_unmet_requirements( array $requirements ) : array {
	$unmet = [];
	foreach ( $requirements as $pkg => $req_list ) {
		$req_parts = explode( ',', $req_list );
		$req_unmet = [];
		foreach ( $req_parts as $req ) {
			$req = trim( $req );
			$comp_spn = strspn( $req, '<>=!' );
			if ( $comp_spn === 0 ) {
				// Invalid requirement, for now.
				continue;
			}

			$comp = trim( substr( $req, 0, $comp_spn ) );
			$ver = trim( substr( $req, $comp_spn ) );

			switch ( true ) {
				case $pkg === 'env:wp':
					// From is_wp_version_compatible()
					// We use our own copy to allow passing $comp.
					if (
						defined( 'WP_RUN_CORE_TESTS' )
						&& WP_RUN_CORE_TESTS
						&& isset( $GLOBALS['_wp_tests_wp_version'] )
					) {
						$wp_version = $GLOBALS['_wp_tests_wp_version'];
					} else {
						$wp_version = wp_get_wp_version();
					}

					$valid = version_compare( $wp_version, $ver, $comp );
					if ( ! $valid ) {
						$req_unmet[] = $req;
					}
					break;

				case $pkg === 'env:php':
					$valid = version_compare( PHP_VERSION, $ver, $comp );
					if ( ! $valid ) {
						$req_unmet[] = $req;
					}
					break;

				case str_starts_with( $pkg, 'env:php-' ):
					// todo: check extensions.
					break;

				case str_starts_with( $pkg, 'env:' ):
					// todo: check other env, or fail.
					break;

				default:
					// todo: check packages.
					break;
			}
		}
		if ( ! empty( $req_unmet ) ) {
			$unmet[ $pkg ] = implode( ', ', $req_unmet );
		}
	}

	return $unmet;
}

/**
 * Check if a release meets the requirements.
 *
 * @param ReleaseDocument $release Release document.
 *
 * @return bool True if the release meets the requirements, false otherwise.
 */
function check_requirements( ReleaseDocument $release ) {
	$requires = get_unmet_requirements( (array) $release->requires );
	return empty( $requires );
}

/**
 * Get the installed version of a package.
 *
 * @param string $id DID of the package to check.
 * @param string $type Type of the package (e.g. 'plugin', 'theme').
 *
 * @return string|null The installed version, or null if not installed.
 */
function get_installed_version( string $id, string $type ) {
	$type .= 's';
	$packages = Updater\get_packages();

	if ( empty( $packages[ $type ][ $id ] ) ) {
		// Not installed.
		return null;
	}

	return get_file_data( $packages[ $type ][ $id ], [ 'Version' => 'Version' ] )['Version'];
}

/**
 * Get icons.
 *
 * @param  array $icons Array of icon data.
 *
 * @return array
 */
function get_icons( $icons ) : array {
	if ( empty( $icons ) ) {
		return [];
	}

	$icons_arr = [];
	$regular = array_find( $icons, fn ( $icon ) => $icon->width === 772 && $icon->height === 250 );
	$high_res = array_find( $icons, fn ( $icon ) => $icon->width === 1544 && $icon->height === 500 );
	$svg = array_find( $icons, fn ( $icon ) => str_contains( $icon->{'content-type'}, 'svg+xml' ) );

	if ( empty( $regular ) && empty( $high_res ) && empty( $svg ) ) {
		return [];
	}

	$icons_arr['1x'] = $regular->url ?? '';
	$icons_arr['2x'] = $high_res->url ?? '';
	if ( str_contains( $svg->url, 's.w.org/plugins' ) ) {
		$icons_arr['default'] = $svg->url;
	} else {
		$icons_arr['svg'] = $svg->url ?? '';
	}

	return $icons_arr;
}

/**
 * Get banners.
 *
 * @param  array $banners Array of banner data.
 *
 * @return array
 */
function get_banners( $banners ) : array {
	if ( empty( $banners ) ) {
		return [];
	}

	$banners_arr = [];
	$regular = array_find( $banners, fn ( $banner ) => $banner->width === 772 && $banner->height === 250 );
	$high_res = array_find( $banners, fn ( $banner ) => $banner->width === 1544 && $banner->height === 500 );

	if ( empty( $regular ) && empty( $high_res ) ) {
		return [];
	}

	$banners_arr['low'] = $regular->url;
	$banners_arr['high'] = $high_res->url;

	return $banners_arr;
}

/**
 * Get hashed file name from MetadataDocument.
 *
 * @param  MetadataDocument $metadata MetadataDocument.
 *
 * @return string
 */
function get_hashed_filename( $metadata ) : string {
	$filename = $metadata->filename;
	$type = str_replace( 'wp-', '', $metadata->type );
	$did_hash = '-' . get_did_hash( $metadata->id );

	list( $slug, $file ) = explode( '/', $filename, 2 );
	if ( 'plugin' === $type ) {
		if ( ! str_contains( $slug, $did_hash ) ) {
			$slug .= $did_hash;
		}
		$filename = $slug . '/' . $file;
	} else {
		$filename = $slug . $did_hash;
	}

	return $filename;
}

/**
 * Get update data for use with transient and API responses.
 *
 * @param string $did DID.
 * @return array|WP_Error
 */
function get_update_data( $did ) {
	$metadata = fetch_package_metadata( $did );
	if ( is_wp_error( $metadata ) ) {
		return $metadata;
	}

	$release = get_latest_release_from_did( $did );
	if ( is_wp_error( $release ) ) {
		return $release;
	}

	$required_versions = version_requirements( $release );
	$filename = get_hashed_filename( $metadata );
	$type = str_replace( 'wp-', '', $metadata->type );

	$response = [
		'name'             => $metadata->name,
		'author'           => $metadata->authors[0]->name,
		'author_uri'       => $metadata->authors[0]->url,
		'slug'             => $metadata->slug . '-' . get_did_hash( $did ),
		$type              => $filename,
		'file'             => $filename,
		'url'              => $metadata->url ?? $metadata->slug,
		'sections'         => (array) $metadata->sections,
		'icons'            => isset( $release->artifacts->icon ) ? get_icons( $release->artifacts->icon ) : [],
		'banners'          => isset( $release->artifacts->banner ) ? get_banners( $release->artifacts->banner ) : [],
		'update-supported' => true,
		'requires'         => $required_versions['requires_wp'] ?? '',
		'requires_php'     => $required_versions['requires_php'] ?? '',
		'new_version'      => $release->version,
		'version'          => $release->version,
		'remote_version'   => $release->version,
		'package'          => $release->artifacts->package[0]->url,
		'download_link'    => $release->artifacts->package[0]->url,
		'tested'           => $required_versions['tested_to'] ?? '',
		'external'         => 'xxx',
		'_fair'            => $metadata,
	];
	if ( 'theme' === $type ) {
		$response['theme_uri'] = $response['url'];
	}

	return $response;
}

/**
 * Send upgrader_pre_download filter to hook `upgrader_source_selection` during AJAX
 * and send to `maybe_add_accept_header()`.
 *
 * @param bool $false Whether to bail without returning the package.
 *                    Default false.
 * @return bool
 */
function upgrader_pre_download( $false ) : bool {
	add_filter( 'http_request_args', 'FAIR\\Packages\\maybe_add_accept_header', 20, 2 );
	add_filter( 'upgrader_source_selection', __NAMESPACE__ . '\\rename_source_selection', 10, 3 );
	return $false;
}

/**
 * Renames a package's directory when it doesn't match the slug.
 *
 * This is commonly required for packages from Git hosts.
 *
 * @param string $source        Path of $source.
 * @param string $remote_source Path of $remote_source.
 * @param WP_Upgrader $upgrader An Upgrader object.
 *
 * @return string|WP_Error
 */
function rename_source_selection( string $source, string $remote_source, WP_Upgrader $upgrader ) {
	global $wp_filesystem;

	$did = get_transient( Admin\ACTION_INSTALL_DID );

	if ( ! $did ) {
		return $source;
	}

	$metadata = fetch_package_metadata( $did );
	if ( is_wp_error( $metadata ) ) {
		return $metadata;
	}

	// Sanity check.
	if ( $upgrader->new_plugin_data['Name'] !== $metadata->name ) {
		return $source;
	}

	if ( str_contains( $source, get_did_hash( $did ) ) && basename( $source ) === $metadata->slug ) {
		return $source;
	}

	$new_source = trailingslashit( $remote_source ) . $metadata->slug . '-' . get_did_hash( $did );

	if ( trailingslashit( strtolower( $source ) ) !== trailingslashit( strtolower( $new_source ) ) ) {
		$wp_filesystem->move( $source, $new_source, true );
	}

	return trailingslashit( $new_source );
}

/**
 * Add FAIR ReleaseDocument data to cache.
 *
 * @param string $did DID.
 * @return void
 */
function add_package_to_release_cache( string $did ) : void {
	if ( empty( $did ) ) {
		return;
	}
	$releases = get_transient( CACHE_RELEASE_PACKAGES ) ?: [];
	$releases[ $did ] = get_latest_release_from_did( $did );
	set_transient( CACHE_RELEASE_PACKAGES, $releases );
}

/**
 * Maybe add accept header for release asset package binary.
 *
 * ReleaseDocument artifact package content-type will be application/octet-stream.
 * Only for GitHub release assets.
 *
 * @param array  $args Array of http args.
 * @param string $url  Download URL.
 *
 * @return array
 */
function maybe_add_accept_header( $args, $url ) : array {
	$releases = get_transient( CACHE_RELEASE_PACKAGES ) ?: [];

	if ( ! str_contains( $url, 'api.github.com' ) ) {
		return $args;
	}

	foreach ( $releases as $release ) {
		if ( $url === $release->artifacts->package[0]->url ) {
			$content_type = $release->artifacts->package[0]->{'content-type'};
			if ( $content_type === 'application/octet-stream' ) {
				$args = array_merge( $args, [ 'headers' => [ 'Accept' => $content_type ] ] );
				break;
			}
		}
	}

	return $args;
}

/**
 * Validate the package alias for a DID.
 *
 * Uses `fair://` aliases from the DID document to determine the alias for the
 * package. Performs bidirectional validation using DNS to ensure the DID is
 * valid for the given alias.
 *
 * Uses cached result for one hour.
 *
 * @param DIDDocument $did DID to validate.
 * @return string|WP_Error|null Alias domain if successfully validated, null if no valid alias is set, or error otherwise.
 */
function validate_package_alias( DIDDocument $did ) {
	$cache_key = sprintf( 'fair_did_alias_%s', $did->id );
	$cached = get_site_transient( $cache_key );
	if ( $cached ) {
		return $cached;
	}

	$alias = fetch_and_validate_package_alias( $did );
	set_site_transient( $cache_key, $alias, HOUR_IN_SECONDS );
	return $alias;
}

/**
 * Validate the package alias for a DID.
 *
 * Uses `fair://` aliases from the DID document to determine the alias for the
 * package. Performs bidirectional validation using DNS to ensure the DID is
 * valid for the given alias.
 *
 * This function queries DNS directly, and is uncached.
 *
 * @param DIDDocument $did DID to validate.
 * @return string|WP_Error|null Alias domain if successfully validated, null if no valid alias is set, or error otherwise.
 */
function fetch_and_validate_package_alias( DIDDocument $did ) {
	$aliases = array_filter( $did->alsoKnownAs, fn ( $alias ) => is_string( $alias ) && str_starts_with( $alias, 'fair://' ) );

	// Packages may only have a single alias, so ignore multiple.
	if ( empty( $aliases ) ) {
		return null;
	}
	if ( count( $aliases ) !== 1 ) {
		return new WP_Error(
			'fair.packages.get_package_alias.too_many_aliases',
			_x( 'Multiple aliases set in DID; packages may only have a single alias', 'alias validation error', 'fair' ),
			compact( 'aliases' )
		);
	}

	// Check the domain is valid.
	$alias = reset( $aliases );
	if ( ! preg_match( '#^fair://([a-z0-9][a-z0-9\-]{1,63}(\.[a-z0-9][a-z0-9\-]{1,63})+)/?$#', $alias, $domain_match ) ) {
		return new WP_Error(
			'fair.packages.get_package_alias.invalid_domain',
			_x( 'Invalid FAIR alias format', 'alias validation error', 'fair' ),
			compact( 'alias' )
		);
	}
	$domain = $domain_match[1];
	$validation_domain = '_fairpm.' . $domain;
	if ( strlen( $validation_domain ) > 255 ) {
		return new WP_Error(
			'fair.packages.get_package_alias.domain_too_long',
			_x( 'FAIR alias format exceeds valid domain length', 'alias validation error', 'fair' ),
			compact( 'validation_domain' )
		);
	}

	// Check DNS record.
	$records = dns_get_record( '_fairpm.' . $domain, DNS_TXT );
	$validation_records = array_filter( $records, fn ( $record ) => str_starts_with( $record['txt'], 'did=' ) );
	if ( count( $validation_records ) !== 1 ) {
		return new WP_Error(
			'fair.packages.get_package_alias.missing_record',
			sprintf(
				/* translators: %s: domain */
				_x( 'Missing verification record for "%s"', 'alias validation error', 'fair' ),
				$domain
			),
			compact( 'domain', 'records' )
		);
	}

	$record = reset( $validation_records );
	if ( ! preg_match( '/^did="?([^"]+)"?$/', $record['txt'], $record_match ) ) {
		// Invalid format.
		return new WP_Error(
			'fair.packages.get_package_alias.invalid_record',
			sprintf(
				/* translators: %s: domain */
				_x( 'Verification record for "%s" is invalid', 'alias validation error', 'fair' ),
				$domain
			),
			compact( 'domain' )
		);
	}
	$expected_did = $record_match[1];

	if ( $expected_did !== $did->id ) {
		return new WP_Error(
			'fair.packages.get_package_alias.mismatched_did',
			_x( 'DID in validation record does not match', 'alias validation error', 'fair' ),
			compact( 'expected_did' )
		);
	}

	// Validated, so return the valid domain.
	return $domain;
}

// phpcs:enable
