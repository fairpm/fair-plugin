<?php
/**
 * Changes events to use The WP World, and news to use FAIR Planet.
 *
 * @package FAIR
 */

namespace FAIR\Dashboard_Widgets;

use const FAIR\CACHE_LIFETIME;

use WP_Error;

const EVENTS_API = 'https://api.fair.pm/fair/v1/events';

/**
 * Bootstrap.
 */
function bootstrap() {
	add_action( 'wp_ajax_get-community-events', __NAMESPACE__ . '\\get_community_events_ajax', 0 );
	remove_action( 'wp_ajax_get-community-events', 'wp_ajax_get_community_events', 1 );

	add_action( 'wp_dashboard_setup', __NAMESPACE__ . '\\on_dashboard_setup' );

	// Inject necessary styles.
	add_action( 'admin_head-index.php', __NAMESPACE__ . '\\add_admin_head' );

	add_action( 'admin_head-index.php', __NAMESPACE__ . '\\set_help_content_fair_planet_urls' );

	// Remove the primary feed and link to avoid showing WordPress.org news.
	add_filter( 'dashboard_primary_link', '__return_empty_string' );
	add_filter( 'dashboard_primary_feed', fn () => [ 'url' => null ] );

	// Configure the WordPress Events and News widget to use FAIR.
	add_filter( 'dashboard_secondary_link', __NAMESPACE__ . '\\get_fair_planet_url' );
	add_filter( 'dashboard_secondary_feed', __NAMESPACE__ . '\\get_fair_planet_feed' );
	add_filter( 'dashboard_secondary_items', fn() => 5 );
}

/**
 * Fires after core widgets for the admin dashboard have been registered.
 */
function on_dashboard_setup() : void {
	// Swap the "Primary" dashboard widget's callback.
	if ( ! empty( $GLOBALS['wp_meta_boxes']['dashboard']['side']['core']['dashboard_primary'] ) ) {
		$GLOBALS['wp_meta_boxes']['dashboard']['side']['core']['dashboard_primary']['callback'] = __NAMESPACE__ . '\\render_news_widget';
	}
}

/**
 * Add custom scripts/styles to admin head.
 *
 * @return void
 */
function add_admin_head() {
	?>
		<style>
			/* Hide the location selector. */
			.community-events .activity-block:first-child {
				display: none;
			}

			/* Add our default icon */
			.community-events .event-event .event-icon:before {
				content: "\f307";
			}
		</style>
	<?php
}

/**
 * Get community events.
 *
 * @return void Outputs JSON directly for the API.
 */
function get_community_events_ajax() : void {
	$data = get_community_events();
	if ( is_wp_error( $data ) ) {
		wp_send_json_error( [ 'error' => $data->get_error_message() ] );
		return;
	}

	wp_send_json_success( $data );
}

/**
 * Get community events.
 *
 * @return array|WP_Error List of events or WP_Error on failure.
 */
function get_community_events() {
	$response = get_transient( EVENTS_API );
	if ( ! $response ) {
		$response = wp_remote_get( EVENTS_API );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		set_transient( EVENTS_API, $response, CACHE_LIFETIME );
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) ) {
		return new WP_Error(
			'parse_error',
			__( 'Unable to fetch events (parse error).', 'fair' )
		);
	}

	// Map data into the expected format.
	$events = [];
	foreach ( $data as $event ) {
		$loc_name = _x( 'Online', 'default event location', 'fair' );
		if ( ! empty( $event['camp_map_location'] ) ) {
			$parts = [
				$event['camp_map_location']['city'] ?? null,
				$event['camp_map_location']['state'] ?? null,
				$event['camp_map_location']['country'] ?? null,
			];
			$loc_name = implode( ', ', array_slice( array_filter( $parts ), 0, 2 ) );
		}
		$start = strtotime( $event['camp_start_date'] );
		$end = strtotime( $event['camp_end_date'] );

		$url = add_query_arg( 'ref', 'fair-dashboard', $event['camp_website_url'] ?? $event['link'] );

		$events[] = [
			'type' => 'event',
			'title' => $event['title']['rendered'],
			'url' => $url,
			'meetup' => null,
			'meetup_url' => null,
			'date' => date( 'Y-m-d', $start ),
			'end_date' => date( 'Y-m-d', $end ),
			'start_unix_timestamp' => $start,
			'end_unix_timestamp' => $end,
			'location' => [
				'location' => $loc_name,
				'country' => $event['camp_map_location'] ? $event['camp_map_location']['country_short'] : '',
				'latitude' => $event['camp_lat'] ?? 0,
				'longitude' => $event['camp_lng'] ?? 0,
			],
		];
	}

	// Resort events by start date.
	usort( $events, fn ( $a, $b ) => ( $a['start_unix_timestamp'] <=> $b['start_unix_timestamp'] ) );

	// Filter to upcoming.
	$events = array_filter( $events, fn ( $ev ) => $ev['start_unix_timestamp'] > time() );

	return [
		'events' => array_slice( $events, 0, 3 ),
		'location' => [
			// Force into showing all events.
			'description' => 'everywhere',
			'location' => '',
			'country' => 'ZZ',
			'latitude' => 0,
			'longitude' => 0,
		],
	];
}

/**
 * Render the news ("Primary") widget.
 *
 * @internal Overrides the default to insert our own footer links, which are
 *           otherwise not filterable.
 */
function render_news_widget() : void {
	// Support existing software like ClassicPress, which removes this feature.
	if ( ! function_exists( 'wp_print_community_events_markup' ) ) {
		return;
	}

	wp_print_community_events_markup();

	?>

	<div class="wordpress-news hide-if-no-js">
		<?php wp_dashboard_primary(); ?>
	</div>

	<p class="community-events-footer">
		<?php
			printf(
				'<a href="%1$s" target="_blank">%2$s <span class="screen-reader-text"> %3$s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a>',
				/* translators: If a Rosetta site exists (e.g. https://es.fair.pm/news/), then use that. Otherwise, leave untranslated. */
				esc_url( _x( 'https://fair.pm/', 'Events and News dashboard widget', 'fair' ) ),
				__( 'News', 'fair' ),
				/* translators: Hidden accessibility text. */
				__( '(opens in a new tab)', 'fair' )
			);
		?>

		|

		<?php
			printf(
				'<a href="%1$s" target="_blank">%2$s <span class="screen-reader-text"> %3$s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a>',
				'https://thewp.world/events/',
				__( 'Events (by The WP World)', 'fair' ),
				/* translators: Hidden accessibility text. */
				__( '(opens in a new tab)', 'fair' )
			);
		?>
	</p>
	<?php
}

/**
 * Get the FAIR Planet URL,
 *
 * @return string
 */
function get_fair_planet_url() : string {
	if ( defined( 'FAIR_PLANET_URL' ) ) {
		return FAIR_PLANET_URL;
	}
	return 'https://planet.fair.pm/';
}

/**
 * Get the FAIR Planet feed.
 *
 * @return string
 */
function get_fair_planet_feed() : string {
	if ( defined( 'FAIR_PLANET_FEED' ) ) {
		return FAIR_PLANET_FEED;
	}
	return 'https://planet.fair.pm/atom.xml';
}

/**
 * Point the WordPress Planet URL to the Fair Planet URL in the Help -> Content tab on the Dashboard.
 * No available filter for this.
 *
 * @return void
 */
function set_help_content_fair_planet_urls() : void {
	$screen = get_current_screen();

	if ( ! $screen || 'dashboard' !== $screen->id ) {
		return;
	}

	$tab = $screen->get_help_tab( 'help-content' );
	if ( ! $tab ) {
		return;
	}

	$tab_title = $tab['title'];

	$planet_fair_url = get_fair_planet_url();
	$planet_fair_url = rtrim( $planet_fair_url, '/' );

	$new_tab_content = preg_replace(
		/* phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled */
		'/https?:\/\/planet\.wordpress\.org/',
		$planet_fair_url,
		$tab['content'],
	);

	$screen->remove_help_tab( 'help-content' );
	$screen->add_help_tab(
		[
			'id'      => 'help-content',
			'title'   => $tab_title,
			'content' => $new_tab_content,
		]
	);
}
