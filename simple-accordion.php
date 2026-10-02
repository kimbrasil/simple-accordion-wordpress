<?php
/**
 * Plugin Name: Simple Accordion
 * Description: A lightweight, accessible accordion manager that supports multiple accordions and renders them with the [simple_accordion] shortcode.
 * Version: 1.1.1
 * Author: kimbrasil
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/kimbrasil/simple-accordion-wordpress
 * Text Domain: simple-accordion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLE_ACCORDION_VERSION', '1.1.1' );
define( 'SIMPLE_ACCORDION_FILE', __FILE__ );
define( 'SIMPLE_ACCORDION_BASENAME', plugin_basename( __FILE__ ) );
define( 'SIMPLE_ACCORDION_URL', plugin_dir_url( __FILE__ ) );
define( 'SIMPLE_ACCORDION_OPTION', 'simple_accordion_items' );
define( 'SIMPLE_ACCORDION_GITHUB_REPOSITORY', 'kimbrasil/simple-accordion-wordpress' );
define( 'SIMPLE_ACCORDION_GITHUB_API_URL', 'https://api.github.com/repos/' . SIMPLE_ACCORDION_GITHUB_REPOSITORY . '/releases/latest' );
define( 'SIMPLE_ACCORDION_UPDATE_URI', 'https://github.com/' . SIMPLE_ACCORDION_GITHUB_REPOSITORY );

function simple_accordion_normalize_items( $items ) {
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
						'title'   => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
						'content' => isset( $item['content'] ) ? wp_kses_post( $item['content'] ) : '',
						'open'    => ! empty( $item['open'] ),
						'order'   => isset( $item['order'] ) ? max( 1, absint( $item['order'] ) ) : 1,
					);
				},
				$items
			),
			static function ( $item ) {
				return is_array( $item );
			}
		)
	);

	usort( $items, static function ( $a, $b ) { return $a['order'] <=> $b['order']; } );
	return $items;
}

function simple_accordion_get_accordions() {
	$saved = get_option( SIMPLE_ACCORDION_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	$is_legacy = empty( $saved ) || isset( $saved[0] ) || ( isset( $saved['title'] ) && isset( $saved['content'] ) );
	if ( $is_legacy ) {
		$saved = array( 'default' => array( 'title' => 'Default Accordion', 'items' => $saved ) );
	}

	$accordions = array();
	foreach ( $saved as $id => $accordion ) {
		if ( ! is_array( $accordion ) ) {
			continue;
		}
		$id = sanitize_key( $id );
		if ( '' === $id ) {
			continue;
		}
		$accordions[ $id ] = array(
			'title' => isset( $accordion['title'] ) ? sanitize_text_field( $accordion['title'] ) : ucfirst( str_replace( '-', ' ', $id ) ),
			'items' => simple_accordion_normalize_items( isset( $accordion['items'] ) ? $accordion['items'] : array() ),
		);
	}

	if ( empty( $accordions ) ) {
		$accordions['default'] = array( 'title' => 'Default Accordion', 'items' => array() );
	}
	return $accordions;
}

function simple_accordion_get_items( $accordion_id = 'default' ) {
	$accordions = simple_accordion_get_accordions();
	$accordion_id = sanitize_key( $accordion_id );
	return isset( $accordions[ $accordion_id ] ) ? $accordions[ $accordion_id ]['items'] : array();
}

function simple_accordion_admin_menu() {
	add_menu_page( __( 'Simple Accordions', 'simple-accordion' ), __( 'Simple Accordions', 'simple-accordion' ), 'manage_options', 'simple-accordion', 'simple_accordion_render_admin_page', 'dashicons-menu-alt3', '26.5' );
}
add_action( 'admin_menu', 'simple_accordion_admin_menu' );

function simple_accordion_admin_assets( $hook ) {
	if ( 'toplevel_page_simple-accordion' === $hook ) {
		wp_enqueue_style( 'simple-accordion-admin', SIMPLE_ACCORDION_URL . 'assets/admin.css', array(), SIMPLE_ACCORDION_VERSION );
	}
}
add_action( 'admin_enqueue_scripts', 'simple_accordion_admin_assets' );

function simple_accordion_save_items() {
	if ( ! isset( $_POST['simple_accordion_save'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	check_admin_referer( 'simple_accordion_save_items', 'simple_accordion_nonce' );
	$accordion_id = isset( $_POST['accordion_id'] ) ? sanitize_key( wp_unslash( $_POST['accordion_id'] ) ) : '';
	$accordion_id = '' !== $accordion_id ? $accordion_id : 'default';
	$title = isset( $_POST['accordion_title'] ) ? sanitize_text_field( wp_unslash( $_POST['accordion_title'] ) ) : '';
	$raw_items = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
	$items = array();

	foreach ( $raw_items as $raw_item ) {
		if ( ! is_array( $raw_item ) ) {
			continue;
		}
		$item = array(
			'title'   => isset( $raw_item['title'] ) ? sanitize_text_field( $raw_item['title'] ) : '',
			'content' => isset( $raw_item['content'] ) ? wp_kses_post( $raw_item['content'] ) : '',
			'order'   => isset( $raw_item['order'] ) ? max( 1, absint( $raw_item['order'] ) ) : count( $items ) + 1,
			'open'    => ! empty( $raw_item['open'] ),
		);
		if ( '' !== trim( $item['title'] ) || '' !== trim( wp_strip_all_tags( $item['content'] ) ) ) {
			$items[] = $item;
		}
	}

	$accordions = simple_accordion_get_accordions();
	$accordions[ $accordion_id ] = array(
		'title' => '' !== $title ? $title : ucfirst( str_replace( '-', ' ', $accordion_id ) ),
		'items' => simple_accordion_normalize_items( $items ),
	);
	update_option( SIMPLE_ACCORDION_OPTION, $accordions, false );
	add_settings_error( 'simple_accordion_messages', 'simple_accordion_saved', __( 'Accordion saved.', 'simple-accordion' ), 'updated' );
}

function simple_accordion_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-accordion' ) );
	}

	simple_accordion_save_items();
	$accordions = simple_accordion_get_accordions();
	$request_id = isset( $_GET['accordion'] ) ? sanitize_key( wp_unslash( $_GET['accordion'] ) ) : '';
	$is_new = 'new-accordion' === $request_id;
	$selected_id = $is_new ? 'new-accordion' : ( $request_id && isset( $accordions[ $request_id ] ) ? $request_id : (string) key( $accordions ) );
	$selected = $is_new ? array( 'title' => '', 'items' => array() ) : $accordions[ $selected_id ];
	?>
	<div class="wrap simple-accordion-admin">
		<h1><?php echo esc_html__( 'Simple Accordions', 'simple-accordion' ); ?></h1>
		<?php settings_errors( 'simple_accordion_messages' ); ?>
		<p><?php echo esc_html__( 'Create separate accordions and place each one with its own shortcode.', 'simple-accordion' ); ?></p>
		<form method="get">
			<input type="hidden" name="page" value="simple-accordion">
			<label for="simple-accordion-selector"><strong><?php echo esc_html__( 'Accordion', 'simple-accordion' ); ?></strong></label>
			<select id="simple-accordion-selector" name="accordion" onchange="this.form.submit()">
				<?php foreach ( $accordions as $id => $accordion ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $selected_id, $id ); ?>><?php echo esc_html( $accordion['title'] . ' (' . $id . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-accordion&accordion=new-accordion' ) ); ?>"><?php echo esc_html__( 'Create new', 'simple-accordion' ); ?></a>
		</form>
		<form method="post">
			<?php wp_nonce_field( 'simple_accordion_save_items', 'simple_accordion_nonce' ); ?>
			<p><label for="simple-accordion-id"><strong><?php echo esc_html__( 'Shortcode ID', 'simple-accordion' ); ?></strong></label><br><input id="simple-accordion-id" type="text" name="accordion_id" value="<?php echo esc_attr( $selected_id ); ?>" pattern="[a-zA-Z0-9_-]+" required> <span class="description"><?php echo esc_html__( 'Use this ID in [simple_accordion id="your-id"].', 'simple-accordion' ); ?></span></p>
			<p><label for="simple-accordion-title"><strong><?php echo esc_html__( 'Accordion title', 'simple-accordion' ); ?></strong></label><br><input id="simple-accordion-title" class="regular-text" type="text" name="accordion_title" value="<?php echo esc_attr( $selected['title'] ); ?>"></p>
			<table class="widefat striped simple-accordion-table">
				<thead><tr><th><?php echo esc_html__( 'Order', 'simple-accordion' ); ?></th><th><?php echo esc_html__( 'Title', 'simple-accordion' ); ?></th><th><?php echo esc_html__( 'Content', 'simple-accordion' ); ?></th><th><?php echo esc_html__( 'Open', 'simple-accordion' ); ?></th><th><?php echo esc_html__( 'Action', 'simple-accordion' ); ?></th></tr></thead>
				<tbody id="simple-accordion-rows">
				<?php foreach ( $selected['items'] as $index => $item ) : ?>
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
			<p><button type="button" class="button" id="simple-accordion-add"><?php echo esc_html__( 'Add item', 'simple-accordion' ); ?></button> <input type="submit" name="simple_accordion_save" class="button button-primary" value="<?php echo esc_attr__( 'Save accordion', 'simple-accordion' ); ?>"></p>
		</form>
		<p><strong><?php echo esc_html__( 'Shortcode:', 'simple-accordion' ); ?></strong> <code>[simple_accordion id="<?php echo esc_attr( $selected_id ); ?>"]</code></p>
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
	$atts = shortcode_atts( array( 'id' => 'default', 'title' => '' ), $atts, 'simple_accordion' );
	$id = sanitize_key( $atts['id'] );
	$items = simple_accordion_get_items( $id );
	if ( empty( $items ) ) {
		return '';
	}

	simple_accordion_enqueue_frontend_assets();
	$instance_id = wp_unique_id( 'simple-accordion-' );
	$output = '<section class="simple-accordion" id="' . esc_attr( $instance_id ) . '" data-accordion-id="' . esc_attr( $id ) . '">';
	if ( '' !== trim( $atts['title'] ) ) {
		$output .= '<h2 class="simple-accordion__title">' . esc_html( $atts['title'] ) . '</h2>';
	}

	foreach ( $items as $index => $item ) {
		$is_open = ! empty( $item['open'] );
		$item_id = $instance_id . '-item-' . ( $index + 1 );
		$panel_id = $item_id . '-panel';
		$output .= '<div class="simple-accordion__item' . ( $is_open ? ' is-open' : '' ) . '">';
		$output .= '<button id="' . esc_attr( $item_id ) . '" type="button" class="simple-accordion__button" aria-expanded="' . ( $is_open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $panel_id ) . '">';
		$output .= '<span class="simple-accordion__icon" aria-hidden="true">' . ( $is_open ? '−' : '+' ) . '</span><span class="simple-accordion__label">' . esc_html( $item['title'] ) . '</span></button>';
		$output .= '<div id="' . esc_attr( $panel_id ) . '" class="simple-accordion__panel" role="region" aria-labelledby="' . esc_attr( $item_id ) . '"' . ( $is_open ? '' : ' hidden' ) . '><div class="simple-accordion__content">' . wp_kses_post( wpautop( $item['content'] ) ) . '</div></div></div>';
	}

	return $output . '</section>';
}
add_shortcode( 'simple_accordion', 'simple_accordion_shortcode' );
