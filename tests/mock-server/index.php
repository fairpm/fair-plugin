<?php
/**
 * Mock DID / FAIR Repository Server for Integration Tests.
 *
 * Emulates PLC Directory and FAIR Repository APIs using fixture JSON.
 * Routes by path only (Host header ignored). Logs all requests to
 * a file so integration tests can assert on HTTP interactions.
 *
 *   GET /did:plc:{identifier}          → DID document
 *   GET /metadata/{did}                → Metadata document
 *   GET /{did}/metadata                → Metadata document (alt format)
 *   GET /health                        → 200 OK + fixture count
 *   GET /log                           → Request log (file-based, persistent)
 *   POST /log/reset                    → Clear request log
 *
 * Usage: php -S 0.0.0.0:8080 index.php
 *
 * @package FAIR
 */

declare( strict_types = 1 );

// ── Configuration ──────────────────────────────────────────────────
$FIXTURES_DIR = __DIR__ . '/fixtures';
$LOG_FILE     = sys_get_temp_dir() . '/fair-mock-server-log.jsonl';

// ── Fixtures ───────────────────────────────────────────────────────
function load_fixtures( string $dir ): array {
	$fixtures = [];
	$files    = glob( "{$dir}/*.json" );
	if ( $files === false ) {
		return $fixtures;
	}
	foreach ( $files as $file ) {
		$fixtures[ basename( $file, '.json' ) ] = file_get_contents( $file );
	}
	return $fixtures;
}

$FIXTURES = load_fixtures( $FIXTURES_DIR );

$DID_MAP = [
	'did:plc:z72i7hdynmk6r22z27h6tvur'       => 'did-doc-integration',
	'did:plc:no-services'                      => 'did-doc-no-services-integration',
	'did:plc:ppicmk23c5pimdivve34bcp2'        => 'did-doc-valid',
	'did:plc:alias-valid'                      => 'did-doc-alias-valid',
	'did:plc:alias-invalid-domain'             => 'did-doc-alias-invalid-domain',
	'did:plc:no-keys'                          => 'did-doc-no-keys',
	'did:plc:hellodolly000000000000001'        => 'did-doc-hello-dolly',
];

$META_MAP = [
	'did:plc:z72i7hdynmk6r22z27h6tvur'        => 'metadata-doc-full',
	'did:plc:minimal12345678901234567890'      => 'metadata-doc-minimal',
	'did:plc:hellodolly000000000000001'        => 'metadata-doc-hello-dolly',
];

// ── File-based logging ─────────────────────────────────────────────

function log_entry( string $method, string $uri, int $code ): void {
	$entry = json_encode( [
		'time'   => gmdate( 'c' ),
		'method' => $method,
		'uri'    => $uri,
		'code'   => $code,
	] );
	@file_put_contents( $GLOBALS['LOG_FILE'], $entry . "\n", FILE_APPEND | LOCK_EX );
}

function read_log(): array {
	if ( ! file_exists( $GLOBALS['LOG_FILE'] ) ) {
		return [];
	}
	$lines = file( $GLOBALS['LOG_FILE'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	if ( $lines === false ) {
		return [];
	}
	return array_map( 'json_decode', $lines );
}

function reset_log(): void {
	@unlink( $GLOBALS['LOG_FILE'] );
}

// ── Routing ────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = rtrim( parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ), '/' );

// Health.
if ( $uri === '/health' ) {
	http_response_code( 200 );
	header( 'Content-Type: application/json' );
	echo json_encode( [ 'status' => 'ok', 'fixtures' => count( $FIXTURES ) ] );
	log_entry( $method, $uri, 200 );
	exit;
}

// Log read.
if ( $uri === '/log' && $method === 'GET' ) {
	http_response_code( 200 );
	header( 'Content-Type: application/json' );
	echo json_encode( read_log(), JSON_PRETTY_PRINT );
	exit;
}

// Log reset.
if ( $uri === '/log/reset' && $method === 'POST' ) {
	reset_log();
	http_response_code( 200 );
	header( 'Content-Type: application/json' );
	echo json_encode( [ 'status' => 'reset' ] );
	exit;
}

// DID document (PLC Directory).
if ( preg_match( '#^/(did:plc:[a-z0-9]+)(/.*)?$#', $uri, $m ) ) {
	$did    = $m[1];
	$sub    = $m[2] ?? '';

	if ( $sub === '/log/audit' || $sub === '/log/last' || $sub === '/log' ) {
		http_response_code( 200 );
		header( 'Content-Type: application/json' );
		echo '[]';
		log_entry( $method, $uri, 200 );
		exit;
	}

	if ( isset( $DID_MAP[ $did ] ) ) {
		http_response_code( 200 );
		header( 'Content-Type: application/json' );
		echo $FIXTURES[ $DID_MAP[ $did ] ] ?? '{}';
		log_entry( $method, $uri, 200 );
		exit;
	}

	http_response_code( 404 );
	header( 'Content-Type: application/json' );
	echo json_encode( [ 'error' => 'DID not found' ] );
	log_entry( $method, $uri, 404 );
	exit;
}

// Metadata document (FAIR Repository).
$did = null;
if ( preg_match( '#^/metadata/(did:plc:[a-z0-9]+)$#', $uri, $m ) ) {
	$did = $m[1];
} elseif ( preg_match( '#^/(did:plc:[a-z0-9]+)/metadata$#', $uri, $m ) ) {
	$did = $m[1];
}

if ( $did !== null ) {
	if ( isset( $META_MAP[ $did ] ) ) {
		http_response_code( 200 );
		header( 'Content-Type: application/json+fair' );
		echo $FIXTURES[ $META_MAP[ $did ] ] ?? '{}';
		log_entry( $method, $uri, 200 );
		exit;
	}

	http_response_code( 404 );
	header( 'Content-Type: application/json' );
	echo json_encode( [ 'error' => 'Metadata not found' ] );
	log_entry( $method, $uri, 404 );
	exit;
}

// Zip artifact serving (so browser tests can install packages).
if ( preg_match( '#^/artifacts/(.+\.zip)$#', $uri, $m ) ) {
	$zip_file = __DIR__ . '/fixtures/zips/' . basename( $m[1] );
	if ( file_exists( $zip_file ) ) {
		http_response_code( 200 );
		header( 'Content-Type: application/zip' );
		header( 'Content-Length: ' . filesize( $zip_file ) );
		readfile( $zip_file );
		log_entry( $method, $uri, 200 );
		exit;
	}
	http_response_code( 404 );
	header( 'Content-Type: application/json' );
	echo json_encode( [ 'error' => 'Artifact not found' ] );
	log_entry( $method, $uri, 404 );
	exit;
}

// Fallback.
http_response_code( 404 );
header( 'Content-Type: application/json' );
echo json_encode( [ 'error' => 'Not found', 'uri' => $uri ] );
log_entry( $method, $uri, 404 );
