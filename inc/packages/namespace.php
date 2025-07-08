<?php
/**
 * Install FAIR packages.
 *
 * @package FAIR
 */

namespace FAIR\Packages;

use FAIR\Packages\DID\PLC;
use FAIR\Packages\DID\Web;
use WP_Error;

const SERVICE_ID = 'FairPackageManagementRepo';
const CONTENT_TYPE = 'application/json+fair';
const DID_CACHE_LIFETIME = 5 * MINUTE_IN_SECONDS;

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
	if ( ! str_starts_with( $id, 'did:' ) ) {
		return new WP_Error( 'fair.packages.validate_did.not_did', __( 'ID is not a valid DID.', 'fair' ) );
	}

	$parts = explode( ':', $id, 3 );
	if ( count( $parts ) !== 3 ) {
		return new WP_Error( 'fair.packages.validate_did.not_uri', __( 'DID could not be parsed as a URI.', 'fair' ) );
	}

	switch ( $parts[1] ) {
		case PLC::TYPE:
			return new PLC( $id );

		case Web::TYPE:
			return new Web( $id );

		default:
			return new WP_Error( 'fair.packages.validate_id.invalid_type', __( 'Unsupported DID type.', 'fair' ) );
	}
}

/**
 * Return hash of DID for appending to slug.
 *
 * @param  string $id DID
 *
 * @return string
 */
function get_did_hash( string $id ) : string {
	$did = parse_did( $id );

	return substr( hash( 'sha256', $did->get_id() ), 0, 6 );
}

/**
 * Get DID document.
 *
 * @param string $id DID.
 * @return DIDDocument|WP_Error
 */
function get_did_document( string $id ) {
	$cached = wp_cache_get( $id, 'fair_did_documents', false, $found );
	if ( $found ) {
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

	wp_cache_set( $id, $document, 'fair_did_documents', DID_CACHE_LIFETIME );
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

	return fetch_metadata_doc( $repo_url );
}

/**
 * Install a plugin from a FAIR DID.
 *
 * @param string $id DID of the package to install.
 * @param string|null $version Version to install. If null, the latest version is installed.
 * @param WP_Upgrader_Skin $skin Plugin Installer Skin.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function install_plugin( string $id, ?string $version = null, $skin ) {
	$document = get_did_document( $id );
	if ( is_wp_error( $document ) ) {
		return $document;
	}

	// Fetch data from the repository.
	$service = $document->get_service( SERVICE_ID );
	if ( empty( $service ) ) {
		return new WP_Error( 'fair.packages.install_plugin.no_service', __( 'DID is not a valid package to install.', 'fair' ) );
	}
	$repo_url = $service->serviceEndpoint;

	// Filter to valid keys for signing.
	$valid_keys = $document->get_fair_signing_keys();
	if ( empty( $valid_keys ) ) {
		return new WP_Error( 'fair.packages.install_plugin.no_signing_keys', __( 'DID does not contain valid signing keys.', 'fair' ) );
	}

	$metadata = fetch_metadata_doc( $repo_url );
	if ( is_wp_error( $metadata ) ) {
		return $metadata;
	}

	// Select the appropriate release.
	$release = pick_release( $metadata->releases, $version );
	if ( empty( $release ) ) {
		return new WP_Error( 'fair.packages.install_plugin.no_releases', __( 'No releases found in the repository.', 'fair' ) );
	}

	$upgrader = new Upgrader( $skin );
	return $upgrader->install( $metadata, $release );
}

/**
 * Fetch the metadata document for a package.
 *
 * @param string $url URL for the metadata document.
 * @return MetadataDocument|WP_Error
 */
function fetch_metadata_doc( string $url ) {
	$response = wp_remote_get( $url, [
		'headers' => [
			'Accept' => sprintf( '%s;q=1.0, application/json;q=0.8', CONTENT_TYPE ),
		],
		'timeout' => 7,
	] );
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	return MetadataDocument::from_response( $response );
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
		return end( $releases );
	}

	return array_find( $releases, fn ( $release ) => $release->version === $version );
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

// phpcs:enable
