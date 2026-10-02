<?php
/**
 * Plugin Name: Simple Accordion
 * Description: A lightweight, accessible accordion managed from a simple admin table and rendered with the [simple_accordion] shortcode.
 * Version: 1.0.2
 * Author: kimbrasil
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/kimbrasil/simple-accordion-wordpress
 * Text Domain: simple-accordion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLE_ACCORDION_VERSION', '1.0.2' );
define( 'SIMPLE_ACCORDION_FILE', __FILE__ );
define( 'SIMPLE_ACCORDION_BASENAME', plugin_basename( __FILE__ ) );
define( 'SIMPLE_ACCORDION_URL', plugin_dir_url( __FILE__ ) );
define( 'SIMPLE_ACCORDION_OPTION', 'simple_accordion_items' );
define( 'SIMPLE_ACCORDION_GITHUB_REPOSITORY', 'kimbrasil/simple-accordion-wordpress' );
define( 'SIMPLE_ACCORDION_GITHUB_API_URL', 'https://api.github.com/repos/' . SIMPLE_ACCORDION_GITHUB_REPOSITORY . '/releases/latest' );
define( 'SIMPLE_ACCORDION_UPDATE_URI', 'https://github.com/' . SIMPLE_ACCORDION_GITHUB_REPOSITORY );

function simple_accordion_get_items() {
	$items = get_option( SIMPLE_ACCORDION_OPTION, array() );

	if ( ! is_array( $items ) ) {
		return array();
	}

	$items = array_values(
		array_filter(
			array_map(
				static function ( $item ) {
					if ( ! is_array( $item ) ) {
						return null;
					}

					return array(
						'title'   => isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '',
						'content' => isset( $item['content'] ) ? wp_kses_post( (string) $item['content'] ) : '',
						'open'    => ! empty( $item['open'] ),
						'order'   => isset( $item['order'] ) ? absint( $item['order'] ) : 0,
					);
				},
				$items
			),
			static function ( $item ) {
				return is_array( $item );
			}
		)
	);

	usort(
		$items,
		static function ( $a, $b ) {
			return $a['order'] <=> $b['order'];
		}
	);

	return $items;
}

function simple_accordion_validate_update_url( $url ) {
	$url = is_string( $url ) ? esc_url_raw( $url ) : '';

	if ( '' === $url || ! wp_http_validate_url( $url ) ) {
		return false;
	}

	$parts = wp_parse_url( $url );
	$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';

	if ( 'https' !== strtolower( isset( $parts['scheme'] ) ? $parts['scheme'] : '' ) ) {
		return false;
	}

	$allowed_hosts = array( 'github.com', 'objects.githubusercontent.com', 'github-releases.githubusercontent.com' );
	if ( ! in_array( $host, $allowed_hosts, true ) ) {
		return false;
	}

	return $url;
}

function simple_accordion_get_release_package( $release ) {
	if ( ! is_object( $release ) || empty( $release->assets ) || ! is_array( $release->assets ) ) {
		return '';
	}

	foreach ( $release->assets as $asset ) {
		if ( ! is_object( $asset ) || empty( $asset->name ) || 'simple-accordion.zip' !== $asset->name ) {
			continue;
		}

		$package = simple_accordion_validate_update_url( isset( $asset->browser_download_url ) ? $asset->browser_download_url : '' );
		if ( false !== $package ) {
			return $package;
		}
	}

	return '';
}

function simple_accordion_get_latest_release() {
	$cached = get_site_transient( 'simple_accordion_latest_release' );

	if ( false !== $cached && is_object( $cached ) ) {
		return $cached;
	}

	$response = wp_safe_remote_get(
		SIMPLE_ACCORDION_GITHUB_API_URL,
		array(
			'timeout'             => 10,
			'limit_response_size' => 1024 * 1024,
			'headers'             => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WordPress Simple Accordion updater',
			),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$release = json_decode( wp_remote_retrieve_body( $response ) );

	if ( ! is_object( $release ) || empty( $release->tag_name ) || ! is_string( $release->tag_name ) ) {
		return null;
	}

	set_site_transient( 'simple_accordion_latest_release', $release, 12 * HOUR_IN_SECONDS );

	return $release;
}

function simple_accordion_check_for_updates( $transient ) {
	if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
		return $transient;
	}

	$release = simple_accordion_get_latest_release();
	if ( ! $release ) {
		return $transient;
	}

	$version = ltrim( sanitize_text_field( $release->tag_name ), 'vV' );
	if ( ! preg_match( '/^\d+(?:\.\d+){0,2}(?:[-+][0-9A-Za-z.-]+)?$/', $version ) || version_compare( $version, SIMPLE_ACCORDION_VERSION, '<=' ) ) {
		return $transient;
	}

	$package = simple_accordion_get_release_package( $release );
	if ( '' === $package ) {
		return $transient;
	}

	$transient->response[ SIMPLE_ACCORDION_BASENAME ] = (object) array(
		'slug'        => 'simple-accordion',
		'plugin'      => SIMPLE_ACCORDION_BASENAME,
		'new_version' => $version,
		'url'         => SIMPLE_ACCORDION_UPDATE_URI,
		'package'     => $package,
	);

	return $transient;
}
add_filter( 'site_transient_update_plugins', 'simple_accordion_check_for_updates' );

function simple_accordion_plugin_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! is_object( $args ) || empty( $args->slug ) || 'simple-accordion' !== $args->slug ) {
		return $result;
	}

	$release = simple_accordion_get_latest_release();
	if ( ! $release ) {
		return $result;
	}

	$version = ltrim( sanitize_text_field( $release->tag_name ), 'vV' );
	$package = simple_accordion_get_release_package( $release );

	return (object) array(
		'name'          => 'Simple Accordion',
		'slug'          => 'simple-accordion',
		'version'       => $version,
		'author'        => '<a href="https://github.com/kimbrasil">kimbrasil</a>',
		'homepage'      => SIMPLE_ACCORDION_UPDATE_URI,
		'download_link' => $package,
		'requires'      => '5.8',
		'tested'        => '6.8',
		'sections'      => array(),
		'description'   => ! empty( $release->body ) ? wpautop( wp_kses_post( $release->body ) ) : 'Simple Accordion plugin updates from GitHub.',
	);
}
add_filter( 'plugins_api_result', 'simple_accordion_plugin_info', 10, 3 );

function simple_accordion_admin_menu() {
	add_menu_page( __( 'Simple Accordion', 'simple-accordion' ), __( 'Simple Accordion', 'simple-accordion' ), 'manage_options', 'simple-accordion', 'simple_accordion_render_admin_page', 'dashicons-menu-alt3', '26.5' );
}
add_action( 'admin_menu', 'simple_accordion_admin_menu' );

function simple_accordion_admin_assets( $hook ) {
	if ( 'toplevel_page_simple-accordion' !== $hook ) {
		return;
	}

	wp_enqueue_style( 'simple-accordion-admin', SIMPLE_ACCORDION_URL . 'assets/admin.css', array(), SIMPLE_ACCORDION_VERSION );
}
add_action( 'admin_enqueue_scripts', 'simple_accordion_admin_assets' );

function simple_accordion_save_items() {
	if ( ! isset( $_POST['simple_accordion_save'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	check_admin_referer( 'simple_accordion_save_items', 'simple_accordion_nonce' );

	$raw_items = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
	$items     = array();

	foreach ( $raw_items as $raw_item ) {
		if ( ! is_array( $raw_item ) ) {
			continue;
		}

		$title   = isset( $raw_item['title'] ) ? sanitize_text_field( $raw_item['title'] ) : '';
		$content = isset( $raw_item['content'] ) ? wp_kses_post( $raw_item['content'] ) : '';
		$order   = isset( $raw_item['order'] ) ? max( 1, absint( $raw_item['order'] ) ) : count( $items ) + 1;
		$open    = ! empty( $raw_item['open'] );

		if ( '' === trim( $title ) && '' === trim( wp_strip_all_tags( $content ) ) ) {
			continue;
		}

		$items[] = array( 'title' => $title, 'content' => $content, 'open' => $open, 'order' => $order );
	}

	usort( $items, static function ( $a, $b ) { return $a['order'] <=> $b['order']; } );
	update_option( SIMPLE_ACCORDION_OPTION, array_values( $items ), false );

	add_settings_error( 'simple_accordion_messages', 'simple_accordion_saved', __( 'Accordion items saved.', 'simple-accordion' ), 'updated' );
}

function simple_accordion_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-accordion' ) );
	}

	simple_accordion_save_items();
	$items = simple_accordion_get_items();
	?>
	<div class="wrap simple-accordion-admin">
		<h1><?php echo esc_html__( 'Simple Accordion', 'simple-accordion' ); ?></h1>
		<p><?php echo esc_html__( 'Add and edit the items displayed by the [simple_accordion] shortcode.', 'simple-accordion' ); ?></p>
		<?php settings_errors( 'simple_accordion_messages' ); ?>
		<form method="post">
			<?php wp_nonce_field( 'simple_accordion_save_items', 'simple_accordion_nonce' ); ?>
			<table class="widefat striped simple-accordion-table">
				<thead><tr><th><?php echo esc_html__( 'Order', 'simple-accordion' ); ?></th><th><?php echo esc_html__( 'Title', 'simple-accordion' ); ?></th><th><?php echo esc_html__( 'Content', 'simple-accordion' ); ?></th><th><?php echo esc_html__( 'Open', 'simple-accordion' ); ?></th><th><?php echo esc_html__( 'Action', 'simple-accordion' ); ?></th></tr></thead>
				<tbody id="simple-accordion-rows">
				<?php foreach ( $items as $index => $item ) : ?>
					<tr>
						<td><input type="number" min="1" class="small-text" name="items[<?php echo esc_attr( $index ); ?>][order]" value="<?php echo esc_attr( $item['order'] ); ?>"></td>
						<td><input type="text" class="regular-text" name="items[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $item['title'] ); ?>"></td>
						<td><textarea name="items[<?php echo esc_attr( $index ); ?>][content]" rows="4"><?php echo esc_textarea( $item['content'] ); ?></textarea></td>
						<td><label><input type="checkbox" name="items[<?php echo esc_attr( $index ); ?>][open]" value="1" <?php checked( $item['open'] ); ?>> <?php echo esc_html__( 'Initially open', 'simple-accordion' ); ?></label></td>
						<td><button type="button" class="button-link-delete simple-accordion-remove"><?php echo esc_html__( 'Remove', 'simple-accordion' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="simple-accordion-add"><?php echo esc_html__( 'Add item', 'simple-accordion' ); ?></button> <input type="submit" name="simple_accordion_save" class="button button-primary" value="<?php echo esc_attr__( 'Save accordions', 'simple-accordion' ); ?>"></p>
		</form>
		<div class="simple-accordion-shortcode-help"><strong><?php echo esc_html__( 'Shortcode:', 'simple-accordion' ); ?></strong> <code>[simple_accordion]</code></div>
	</div>
	<script>
	(function () {
		'use strict';
		var addButton = document.getElementById('simple-accordion-add');
		var rows = document.getElementById('simple-accordion-rows');
		var index = rows ? rows.children.length : 0;
		if (!addButton || !rows) return;
		addButton.addEventListener('click', function () {
			var row = document.createElement('tr');
			row.innerHTML = '<td><input type="number" min="1" class="small-text" name="items[' + index + '][order]" value="' + (index + 1) + '"></td><td><input type="text" class="regular-text" name="items[' + index + '][title]"></td><td><textarea name="items[' + index + '][content]" rows="4"></textarea></td><td><label><input type="checkbox" name="items[' + index + '][open]" value="1"> Initially open</label></td><td><button type="button" class="button-link-delete simple-accordion-remove">Remove</button></td>';
			rows.appendChild(row);
			index += 1;
		});
		rows.addEventListener('click', function (event) {
			if (!event.target.classList.contains('simple-accordion-remove')) return;
			var row = event.target.closest('tr');
			if (row) row.remove();
		});
	}());
	</script>
	<?php
}

function simple_accordion_enqueue_frontend_assets() {
	wp_enqueue_style( 'simple-accordion-frontend', SIMPLE_ACCORDION_URL . 'assets/frontend.css', array(), SIMPLE_ACCORDION_VERSION );
	wp_enqueue_script( 'simple-accordion-frontend', SIMPLE_ACCORDION_URL . 'assets/frontend.js', array(), SIMPLE_ACCORDION_VERSION, true );
}

function simple_accordion_shortcode( $atts ) {
	$atts  = shortcode_atts( array( 'title' => '' ), $atts, 'simple_accordion' );
	$items = simple_accordion_get_items();
	if ( empty( $items ) ) {
		return '';
	}

	simple_accordion_enqueue_frontend_assets();
	$instance_id = wp_unique_id( 'simple-accordion-' );
	$output      = '<section class="simple-accordion" id="' . esc_attr( $instance_id ) . '">';

	foreach ( $items as $index => $item ) {
		$is_open  = ! empty( $item['open'] );
		$item_id  = $instance_id . '-item-' . ( $index + 1 );
		$panel_id = $item_id . '-panel';
		$classes  = 'simple-accordion__item' . ( $is_open ? ' is-open' : '' );
		$output  .= '<div class="' . esc_attr( $classes ) . '">';
		$output  .= '<button id="' . esc_attr( $item_id ) . '" type="button" class="simple-accordion__button" aria-expanded="' . ( $is_open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $panel_id ) . '">';
		$output  .= '<span class="simple-accordion__icon" aria-hidden="true">' . ( $is_open ? '−' : '+' ) . '</span>';
		$output  .= '<span class="simple-accordion__label">' . esc_html( $item['title'] ) . '</span></button>';
		$output  .= '<div id="' . esc_attr( $panel_id ) . '" class="simple-accordion__panel" role="region" aria-labelledby="' . esc_attr( $item_id ) . '"' . ( $is_open ? '' : ' hidden' ) . '><div class="simple-accordion__content">' . wp_kses_post( wpautop( $item['content'] ) ) . '</div></div></div>';
	}

	return $output . '</section>';
}
add_shortcode( 'simple_accordion', 'simple_accordion_shortcode' );
