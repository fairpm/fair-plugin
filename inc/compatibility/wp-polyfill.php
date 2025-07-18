<?php
/**
 * Polyfill for WordPress functions not included in minimum supported version.
 * Requires WordPress 5.3
 */

if ( ! function_exists( 'esc_xml' ) ) {
	/**
	 * Escaping for XML blocks.
	 *
	 * @since WordPress 5.5.0
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	function esc_xml( $text ) {
		$safe_text = wp_check_invalid_utf8( $text );

		$cdata_regex = '\<\!\[CDATA\[.*?\]\]\>';
		$regex       = <<<EOF
/
	(?=.*?{$cdata_regex})                 # lookahead that will match anything followed by a CDATA Section
	(?<non_cdata_followed_by_cdata>(.*?)) # the "anything" matched by the lookahead
	(?<cdata>({$cdata_regex}))            # the CDATA Section matched by the lookahead

|	                                      # alternative

	(?<non_cdata>(.*))                    # non-CDATA Section
/sx
EOF;

		$safe_text = (string) preg_replace_callback(
			$regex,
			static function ( $matches ) {
				if ( ! isset( $matches[0] ) ) {
					return '';
				}

				if ( isset( $matches['non_cdata'] ) ) {
					// escape HTML entities in the non-CDATA Section.
					return _wp_specialchars( $matches['non_cdata'], ENT_XML1 );
				}

				// Return the CDATA Section unchanged, escape HTML entities in the rest.
				return _wp_specialchars( $matches['non_cdata_followed_by_cdata'], ENT_XML1 ) . $matches['cdata'];
			},
			$safe_text
		);

		/**
		 * Filters a string cleaned and escaped for output in XML.
		 *
		 * Text passed to esc_xml() is stripped of invalid or special characters
		 * before output. HTML named character references are converted to their
		 * equivalent code points.
		 *
		 * @since WordPress 5.5.0
		 *
		 * @param string $safe_text The text after it has been escaped.
		 * @param string $text      The text prior to being escaped.
		 */
		return apply_filters( 'esc_xml', $safe_text, $text );
	}
}

if ( ! function_exists( 'is_post_status_viewable' ) ) {
	/**
	 * Determines whether a post status is considered "viewable".
	 *
	 * For built-in post statuses such as publish and private, the 'public' value will be evaluated.
	 * For all others, the 'publicly_queryable' value will be used.
	 *
	 * @since WordPress 5.7.0
	 * @since WordPress 5.9.0 Added `is_post_status_viewable` hook to filter the result.
	 *
	 * @param string|stdClass $post_status Post status name or object.
	 * @return bool Whether the post status should be considered viewable.
	 */
	function is_post_status_viewable( $post_status ) {
		if ( is_scalar( $post_status ) ) {
			$post_status = get_post_status_object( $post_status );

			if ( ! $post_status ) {
				return false;
			}
		}

		if (
			! is_object( $post_status )
			|| $post_status->internal
			|| $post_status->protected
		) {
			return false;
		}

		$is_viewable = $post_status->publicly_queryable || ( $post_status->_builtin && $post_status->public );

		/**
		 * Filters whether a post status is considered "viewable".
		 *
		 * The returned filtered value must be a boolean type to ensure
		 * `is_post_status_viewable()` only returns a boolean. This strictness
		 * is by design to maintain backwards-compatibility and guard against
		 * potential type errors in PHP 8.1+. Non-boolean values (even falsey
		 * and truthy values) will result in the function returning false.
		 *
		 * @since WordPress 5.9.0
		 *
		 * @param bool     $is_viewable Whether the post status is "viewable" (strict type).
		 * @param stdClass $post_status Post status object.
		 */
		return true === apply_filters( 'is_post_status_viewable', $is_viewable, $post_status );
	}
}

// If _is_utf8_charset is already loaded.
if ( ! function_exists( '_is_utf8_charset' ) ) {
	/**
	 * Indicates if a given slug for a character set represents the UTF-8 text encoding.
	 *
	 * A charset is considered to represent UTF-8 if it is a case-insensitive match
	 * of "UTF-8" with or without the hyphen.
	 *
	 * Example:
	 *
	 *     true  === _is_utf8_charset( 'UTF-8' );
	 *     true  === _is_utf8_charset( 'utf8' );
	 *     false === _is_utf8_charset( 'latin1' );
	 *     false === _is_utf8_charset( 'UTF 8' );
	 *
	 *     // Only strings match.
	 *     false === _is_utf8_charset( [ 'charset' => 'utf-8' ] );
	 *
	 * `is_utf8_charset` should be used outside of this file.
	 *
	 * @ignore
	 * @since WordPress 6.6.1
	 *
	 * @param string $charset_slug Slug representing a text character encoding, or "charset".
	 *                             E.g. "UTF-8", "Windows-1252", "ISO-8859-1", "SJIS".
	 *
	 * @return bool Whether the slug represents the UTF-8 encoding.
	 */
	function _is_utf8_charset( $charset_slug ) {
		if ( ! is_string( $charset_slug ) ) {
			return false;
		}

		return (
			0 === strcasecmp( 'UTF-8', $charset_slug ) ||
			0 === strcasecmp( 'UTF8', $charset_slug )
		);
	}
}

if ( ! function_exists( 'wp_get_admin_notice' ) ) {
	/**
	 * Creates and returns the markup for an admin notice.
	 *
	 * @since WordPress 6.4.0
	 *
	 * @param string $message The message.
	 * @param array  $args {
	 *     Optional. An array of arguments for the admin notice. Default empty array.
	 *
	 *     @type string   $type               Optional. The type of admin notice.
	 *                                        For example, 'error', 'success', 'warning', 'info'.
	 *                                        Default empty string.
	 *     @type bool     $dismissible        Optional. Whether the admin notice is dismissible. Default false.
	 *     @type string   $id                 Optional. The value of the admin notice's ID attribute. Default empty string.
	 *     @type string[] $additional_classes Optional. A string array of class names. Default empty array.
	 *     @type string[] $attributes         Optional. Additional attributes for the notice div. Default empty array.
	 *     @type bool     $paragraph_wrap     Optional. Whether to wrap the message in paragraph tags. Default true.
	 * }
	 * @return string The markup for an admin notice.
	 */
	function wp_get_admin_notice( $message, $args = [] ) {
		$defaults = [
			'type'               => '',
			'dismissible'        => false,
			'id'                 => '',
			'additional_classes' => [],
			'attributes'         => [],
			'paragraph_wrap'     => true,
		];

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filters the arguments for an admin notice.
		 *
		 * @since WordPress 6.4.0
		 *
		 * @param array  $args    The arguments for the admin notice.
		 * @param string $message The message for the admin notice.
		 */
		$args       = apply_filters( 'wp_admin_notice_args', $args, $message );
		$id         = '';
		$classes    = 'notice';
		$attributes = '';

		if ( is_string( $args['id'] ) ) {
			$trimmed_id = trim( $args['id'] );

			if ( '' !== $trimmed_id ) {
				$id = 'id="' . $trimmed_id . '" ';
			}
		}

		if ( is_string( $args['type'] ) ) {
			$type = trim( $args['type'] );

			if ( str_contains( $type, ' ' ) ) {
				_doing_it_wrong(
					__FUNCTION__,
					sprintf(
						/* translators: %s: The "type" key. */
						__( 'The %s key must be a string without spaces.' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- This intentionally uses WordPress Core's translation string.
						'<code>type</code>'
					),
					'6.4.0'
				);
			}

			if ( '' !== $type ) {
				$classes .= ' notice-' . $type;
			}
		}

		if ( true === $args['dismissible'] ) {
			$classes .= ' is-dismissible';
		}

		if ( is_array( $args['additional_classes'] ) && ! empty( $args['additional_classes'] ) ) {
			$classes .= ' ' . implode( ' ', $args['additional_classes'] );
		}

		if ( is_array( $args['attributes'] ) && ! empty( $args['attributes'] ) ) {
			$attributes = '';
			foreach ( $args['attributes'] as $attr => $val ) {
				if ( is_bool( $val ) ) {
					$attributes .= $val ? ' ' . $attr : '';
				} elseif ( is_int( $attr ) ) {
					$attributes .= ' ' . esc_attr( trim( $val ) );
				} elseif ( $val ) {
					$attributes .= ' ' . $attr . '="' . esc_attr( trim( $val ) ) . '"';
				}
			}
		}

		if ( false !== $args['paragraph_wrap'] ) {
			$message = "<p>$message</p>";
		}

		$markup = sprintf( '<div %1$sclass="%2$s"%3$s>%4$s</div>', $id, $classes, $attributes, $message );

		/**
		 * Filters the markup for an admin notice.
		 *
		 * @since WordPress 6.4.0
		 *
		 * @param string $markup  The HTML markup for the admin notice.
		 * @param string $message The message for the admin notice.
		 * @param array  $args    The arguments for the admin notice.
		 */
		return apply_filters( 'wp_admin_notice_markup', $markup, $message, $args );
	}
}

if ( ! function_exists( 'wp_admin_notice' ) ) {
	/**
	 * Outputs an admin notice.
	 *
	 * @since WordPress 6.4.0
	 *
	 * @param string $message The message to output.
	 * @param array  $args {
	 *     Optional. An array of arguments for the admin notice. Default empty array.
	 *
	 *     @type string   $type               Optional. The type of admin notice.
	 *                                        For example, 'error', 'success', 'warning', 'info'.
	 *                                        Default empty string.
	 *     @type bool     $dismissible        Optional. Whether the admin notice is dismissible. Default false.
	 *     @type string   $id                 Optional. The value of the admin notice's ID attribute. Default empty string.
	 *     @type string[] $additional_classes Optional. A string array of class names. Default empty array.
	 *     @type string[] $attributes         Optional. Additional attributes for the notice div. Default empty array.
	 *     @type bool     $paragraph_wrap     Optional. Whether to wrap the message in paragraph tags. Default true.
	 * }
	 */
	function wp_admin_notice( $message, $args = [] ) {
		/**
		 * Fires before an admin notice is output.
		 *
		 * @since WordPress 6.4.0
		 *
		 * @param string $message The message for the admin notice.
		 * @param array  $args    The arguments for the admin notice.
		 */
		do_action( 'wp_admin_notice', $message, $args );

		echo wp_kses_post( wp_get_admin_notice( $message, $args ) );
	}
}
if ( ! function_exists( 'wp_get_wp_version' ) ) {
	/**
	 * Returns the current WordPress version.
	 *
	 * Returns an unmodified value of `$wp_version`. Some plugins modify the global
	 * in an attempt to improve security through obscurity. This practice can cause
	 * errors in WordPress, so the ability to get an unmodified version is needed.
	 *
	 * @since WordPress 6.7.0
	 *
	 * @return string The current WordPress version.
	 */
	function wp_get_wp_version() {
		static $wp_version;

		if ( ! isset( $wp_version ) ) {
			require ABSPATH . WPINC . '/version.php';
		}

		return $wp_version;
	}
}

if ( ! function_exists( 'get_user' ) ) {
	/**
	 * Retrieves user info by user ID.
	 *
	 * @since WordPress 6.7.0
	 *
	 * @param int $user_id User ID.
	 *
	 * @return WP_User|false WP_User object on success, false on failure.
	 */
	function get_user( $user_id ) {
		return get_user_by( 'id', $user_id );
	}
}
