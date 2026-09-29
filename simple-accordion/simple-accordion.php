<?php
/**
 * Plugin Name: Simple Accordion
 * Description: A lightweight, accessible accordion managed from a simple admin table and rendered with the [simple_accordion] shortcode.
 * Version: 1.1.0
 * Author: kimbrasil
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/kimbrasil/simple-accordion-wordpress
 * Text Domain: simple-accordion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLE_ACCORDION_VERSION', '1.1.0' );
define( 'SIMPLE_ACCORDION_FILE', __FILE__ );
define( 'SIMPLE_ACCORDION_BASENAME', plugin_basename( __FILE__ ) );
define( 'SIMPLE_ACCORDION_URL', plugin_dir_url( __FILE__ ) );
define( 'SIMPLE_ACCORDION_OPTION', 'simple_accordion_items' );
define( 'SIMPLE_ACCORDION_GROUPS_OPTION', 'simple_accordion_groups' );
define( 'SIMPLE_ACCORDION_DEFAULT_GROUP_ID', 'default' );
define( 'SIMPLE_ACCORDION_GITHUB_REPOSITORY', 'kimbrasil/simple-accordion-wordpress' );
define( 'SIMPLE_ACCORDION_GITHUB_API_URL', 'https://api.github.com/repos/' . SIMPLE_ACCORDION_GITHUB_REPOSITORY . '/releases/latest' );
define( 'SIMPLE_ACCORDION_UPDATE_URI', 'https://github.com/' . SIMPLE_ACCORDION_GITHUB_REPOSITORY );

/**
 * Sanitize a group id into a stable slug.
 *
 * @param string $raw_id Raw id.
 * @return string
 */
function simple_accordion_sanitize_group_id( $raw_id ) {
	$group_id = sanitize_title( (string) $raw_id );

	if ( '' === $group_id ) {
		$group_id = SIMPLE_ACCORDION_DEFAULT_GROUP_ID;
	}

	return $group_id;
}

/**
 * Normalize saved accordion items.
 *
 * @param mixed $items Raw items.
 * @return array<int, array{title:string,content:string,open:bool,order:int}>
 */
function simple_accordion_normalize_items( $items ) {
	if ( ! is_array( $items ) ) {
		return array();
	}

	$normalized = array_values(
		array_filter(
			array_map(
				function ( $item ) {
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
			function ( $item ) {
				return is_array( $item );
			}
		)
	);

	usort(
		$normalized,
		function ( $a, $b ) {
			return $a['order'] <=> $b['order'];
		}
	);

	return $normalized;
}

/**
 * Build the default group shape.
 *
 * @return array{title:string,items:array<int,array{title:string,content:string,open:bool,order:int}>}
 */
function simple_accordion_default_group() {
	return array(
		'title' => '',
		'items' => array(),
	);
}

/**
 * Normalize one group.
 *
 * @param string $group_id Group id.
 * @param mixed  $group    Raw group.
 * @return array{title:string,items:array<int,array{title:string,content:string,open:bool,order:int}>}
 */
function simple_accordion_normalize_group( $group_id, $group ) {
	$defaults = simple_accordion_default_group();

	if ( ! is_array( $group ) ) {
		return $defaults;
	}

	$title = isset( $group['title'] ) ? sanitize_text_field( (string) $group['title'] ) : '';
	$items = isset( $group['items'] ) ? $group['items'] : array();

	return array(
		'title' => $title,
		'items' => simple_accordion_normalize_items( $items ),
	);
}

/**
 * Ensure new groups option exists, migrating from legacy single-group option.
 *
 * @return array<string, array{title:string,items:array<int,array{title:string,content:string,open:bool,order:int}>}>
 */
function simple_accordion_get_groups() {
	$missing_sentinel = '__simple_accordion_groups_missing__';
	$groups           = get_option( SIMPLE_ACCORDION_GROUPS_OPTION, $missing_sentinel );

	if ( $missing_sentinel === $groups ) {
		$legacy_items = get_option( SIMPLE_ACCORDION_OPTION, array() );
		$groups       = array(
			SIMPLE_ACCORDION_DEFAULT_GROUP_ID => array(
				'title' => '',
				'items' => simple_accordion_normalize_items( $legacy_items ),
			),
		);

		update_option( SIMPLE_ACCORDION_GROUPS_OPTION, $groups );
	}

	if ( ! is_array( $groups ) ) {
		$groups = array();
	}

	$normalized = array();

	foreach ( $groups as $raw_group_id => $group ) {
		$group_id              = simple_accordion_sanitize_group_id( $raw_group_id );
		$normalized[ $group_id ] = simple_accordion_normalize_group( $group_id, $group );
	}

	if ( empty( $normalized[ SIMPLE_ACCORDION_DEFAULT_GROUP_ID ] ) ) {
		$normalized[ SIMPLE_ACCORDION_DEFAULT_GROUP_ID ] = simple_accordion_default_group();
	}

	return $normalized;
}

/**
 * Persist groups in the normalized format.
 *
 * @param array<string, array{title:string,items:array<int,array{title:string,content:string,open:bool,order:int}>}> $groups Groups.
 * @return void
 */
function simple_accordion_update_groups( $groups ) {
	$normalized = array();

	if ( is_array( $groups ) ) {
		foreach ( $groups as $raw_group_id => $group ) {
			$group_id                = simple_accordion_sanitize_group_id( $raw_group_id );
			$normalized[ $group_id ] = simple_accordion_normalize_group( $group_id, $group );
		}
	}

	if ( empty( $normalized[ SIMPLE_ACCORDION_DEFAULT_GROUP_ID ] ) ) {
		$normalized[ SIMPLE_ACCORDION_DEFAULT_GROUP_ID ] = simple_accordion_default_group();
	}

	update_option( SIMPLE_ACCORDION_GROUPS_OPTION, $normalized );
}

/**
 * Return one group by id.
 *
 * @param string $group_id Group id.
 * @return array{title:string,items:array<int,array{title:string,content:string,open:bool,order:int}>}|null
 */
function simple_accordion_get_group( $group_id ) {
	$groups   = simple_accordion_get_groups();
	$group_id = simple_accordion_sanitize_group_id( $group_id );

	if ( isset( $groups[ $group_id ] ) ) {
		return $groups[ $group_id ];
	}

	return null;
}

/**
 * Return the saved accordion items in a predictable format (legacy helper).
 *
 * @return array<int, array{title:string,content:string,open:bool,order:int}>
 */
function simple_accordion_get_items() {
	$group = simple_accordion_get_group( SIMPLE_ACCORDION_DEFAULT_GROUP_ID );

	if ( ! is_array( $group ) ) {
		return array();
	}

	return isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();
}

/**
 * Request the latest GitHub release metadata.
 *
 * The release must include a ZIP asset named simple-accordion.zip. This is the
 * package WordPress downloads during an automatic update.
 *
 * @return object|null
 */
function simple_accordion_get_latest_release() {
	$cached = get_site_transient( 'simple_accordion_latest_release' );

	if ( false !== $cached ) {
		return $cached;
	}

	$response = wp_remote_get(
		SIMPLE_ACCORDION_GITHUB_API_URL,
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WordPress Simple Accordion updater',
			),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$release = json_decode( wp_remote_retrieve_body( $response ) );

	if ( ! is_object( $release ) || empty( $release->tag_name ) ) {
		return null;
	}

	set_site_transient( 'simple_accordion_latest_release', $release, 12 * HOUR_IN_SECONDS );

	return $release;
}

/**
 * Add a GitHub release to WordPress plugin updates.
 *
 * @param object $transient WordPress update transient.
 * @return object
 */
function simple_accordion_check_for_updates( $transient ) {
	if ( empty( $transient->checked ) || ! is_object( $transient ) ) {
		return $transient;
	}

	$release = simple_accordion_get_latest_release();

	if ( ! $release ) {
		return $transient;
	}

	$version = ltrim( (string) $release->tag_name, 'vV' );

	if ( version_compare( $version, SIMPLE_ACCORDION_VERSION, '<=' ) ) {
		return $transient;
	}

	$package = '';

	if ( ! empty( $release->assets ) && is_array( $release->assets ) ) {
		foreach ( $release->assets as $asset ) {
			if ( ! empty( $asset->name ) && 'simple-accordion.zip' === $asset->name && ! empty( $asset->browser_download_url ) ) {
				$package = $asset->browser_download_url;
				break;
			}
		}
	}

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

/**
 * Provide plugin information in the WordPress updates modal.
 *
 * @param false|object|array $result Existing result.
 * @param string             $action API action.
 * @param object             $args    API arguments.
 * @return false|object
 */
function simple_accordion_plugin_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'simple-accordion' !== $args->slug ) {
		return $result;
	}

	$release = simple_accordion_get_latest_release();

	if ( ! $release ) {
		return $result;
	}

	$version = ltrim( (string) $release->tag_name, 'vV' );
	$package = '';

	if ( ! empty( $release->assets ) && is_array( $release->assets ) ) {
		foreach ( $release->assets as $asset ) {
			if ( ! empty( $asset->name ) && 'simple-accordion.zip' === $asset->name && ! empty( $asset->browser_download_url ) ) {
				$package = $asset->browser_download_url;
				break;
			}
		}
	}

	$description = ! empty( $release->body ) ? wpautop( wp_kses_post( $release->body ) ) : 'Simple Accordion plugin updates from GitHub.';

	return (object) array(
		'name'          => 'Simple Accordion',
		'slug'          => 'simple-accordion',
		'version'       => $version,
		'author'        => '<a href="https://github.com/kimbrasil">kimbrasil</a>',
		'homepage'      => SIMPLE_ACCORDION_UPDATE_URI,
		'download_link' => $package,
		'requires'      => '5.8',
		'tested'        => '6.8',
		'sections'      => array(
			'description' => $description,
			'changelog'   => $description,
		),
	);
}
add_filter( 'plugins_api', 'simple_accordion_plugin_info', 10, 3 );

/**
 * Admin actions processing.
 *
 * @return string Selected group id after handling actions.
 */
function simple_accordion_handle_admin_actions() {
	$groups             = simple_accordion_get_groups();
	$selected_group_id  = isset( $_REQUEST['group'] ) ? simple_accordion_sanitize_group_id( wp_unslash( $_REQUEST['group'] ) ) : SIMPLE_ACCORDION_DEFAULT_GROUP_ID;

	if ( ! isset( $groups[ $selected_group_id ] ) ) {
		$selected_group_id = SIMPLE_ACCORDION_DEFAULT_GROUP_ID;
	}

	if ( ! isset( $_POST['simple_accordion_action'] ) ) {
		return $selected_group_id;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return $selected_group_id;
	}

	$action = sanitize_key( wp_unslash( $_POST['simple_accordion_action'] ) );

	if ( 'save_group' === $action ) {
		check_admin_referer( 'simple_accordion_save_group', 'simple_accordion_nonce' );

		$group_id = isset( $_POST['group_id'] ) ? simple_accordion_sanitize_group_id( wp_unslash( $_POST['group_id'] ) ) : SIMPLE_ACCORDION_DEFAULT_GROUP_ID;

		if ( ! isset( $groups[ $group_id ] ) ) {
			$groups[ $group_id ] = simple_accordion_default_group();
		}

		$group_title = isset( $_POST['group_title'] ) ? sanitize_text_field( wp_unslash( $_POST['group_title'] ) ) : '';
		$raw_items   = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
		$items       = array();

		foreach ( $raw_items as $raw_item ) {
			if ( ! is_array( $raw_item ) ) {
				continue;
			}

			$title   = isset( $raw_item['title'] ) ? sanitize_text_field( $raw_item['title'] ) : '';
			$content = isset( $raw_item['content'] ) ? wp_kses_post( $raw_item['content'] ) : '';
			$order   = isset( $raw_item['order'] ) ? absint( $raw_item['order'] ) : count( $items ) + 1;
			$open    = ! empty( $raw_item['open'] );

			if ( '' === trim( $title ) && '' === trim( wp_strip_all_tags( $content ) ) ) {
				continue;
			}

			$items[] = array(
				'title'   => $title,
				'content' => $content,
				'open'    => $open,
				'order'   => $order,
			);
		}

		usort(
			$items,
			function ( $a, $b ) {
				return $a['order'] <=> $b['order'];
			}
		);

		$groups[ $group_id ] = array(
			'title' => $group_title,
			'items' => array_values( $items ),
		);

		simple_accordion_update_groups( $groups );

		add_settings_error(
			'simple_accordion_messages',
			'simple_accordion_saved',
			__( 'Accordion group saved.', 'simple-accordion' ),
			'updated'
		);

		return $group_id;
	}

	if ( 'create_group' === $action ) {
		check_admin_referer( 'simple_accordion_manage_groups', 'simple_accordion_groups_nonce' );

		$raw_new_group_id = isset( $_POST['new_group_id'] ) ? wp_unslash( $_POST['new_group_id'] ) : '';
		$new_group_id     = sanitize_title( (string) $raw_new_group_id );
		$new_group_title = isset( $_POST['new_group_title'] ) ? sanitize_text_field( wp_unslash( $_POST['new_group_title'] ) ) : '';

		if ( '' === $new_group_id ) {
			add_settings_error( 'simple_accordion_messages', 'simple_accordion_group_invalid', __( 'Group ID is required.', 'simple-accordion' ), 'error' );
			return $selected_group_id;
		}

		if ( isset( $groups[ $new_group_id ] ) ) {
			add_settings_error( 'simple_accordion_messages', 'simple_accordion_group_exists', __( 'This group ID already exists.', 'simple-accordion' ), 'error' );
			return $selected_group_id;
		}

		$groups[ $new_group_id ] = array(
			'title' => $new_group_title,
			'items' => array(),
		);
		simple_accordion_update_groups( $groups );

		add_settings_error( 'simple_accordion_messages', 'simple_accordion_group_created', __( 'Accordion group created.', 'simple-accordion' ), 'updated' );

		return $new_group_id;
	}

	if ( 'delete_group' === $action ) {
		check_admin_referer( 'simple_accordion_manage_groups', 'simple_accordion_groups_nonce' );

		$delete_group_id = isset( $_POST['delete_group_id'] ) ? simple_accordion_sanitize_group_id( wp_unslash( $_POST['delete_group_id'] ) ) : '';
		$confirmed       = ! empty( $_POST['confirm_delete_group'] );

		if ( '' === $delete_group_id || ! isset( $groups[ $delete_group_id ] ) ) {
			add_settings_error( 'simple_accordion_messages', 'simple_accordion_group_missing', __( 'Group not found.', 'simple-accordion' ), 'error' );
			return $selected_group_id;
		}

		if ( SIMPLE_ACCORDION_DEFAULT_GROUP_ID === $delete_group_id ) {
			add_settings_error( 'simple_accordion_messages', 'simple_accordion_group_delete_default', __( 'The default group cannot be deleted.', 'simple-accordion' ), 'error' );
			return $selected_group_id;
		}

		if ( ! $confirmed ) {
			add_settings_error( 'simple_accordion_messages', 'simple_accordion_group_delete_confirm', __( 'Please confirm group deletion before continuing.', 'simple-accordion' ), 'error' );
			return $selected_group_id;
		}

		unset( $groups[ $delete_group_id ] );
		simple_accordion_update_groups( $groups );

		add_settings_error( 'simple_accordion_messages', 'simple_accordion_group_deleted', __( 'Accordion group deleted.', 'simple-accordion' ), 'updated' );

		if ( $selected_group_id === $delete_group_id || ! isset( $groups[ $selected_group_id ] ) ) {
			$selected_group_id = SIMPLE_ACCORDION_DEFAULT_GROUP_ID;
		}

		return $selected_group_id;
	}

	return $selected_group_id;
}

function simple_accordion_admin_menu() {
	add_menu_page(
		__( 'Simple Accordion', 'simple-accordion' ),
		__( 'Simple Accordion', 'simple-accordion' ),
		'manage_options',
		'simple-accordion',
		'simple_accordion_render_admin_page',
		'dashicons-menu-alt3',
		'26.5'
	);
}
add_action( 'admin_menu', 'simple_accordion_admin_menu' );

function simple_accordion_admin_assets( $hook ) {
	if ( 'toplevel_page_simple-accordion' !== $hook ) {
		return;
	}

	wp_enqueue_style(
		'simple-accordion-admin',
		SIMPLE_ACCORDION_URL . 'assets/admin.css',
		array(),
		SIMPLE_ACCORDION_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'simple_accordion_admin_assets' );

function simple_accordion_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-accordion' ) );
	}

	$selected_group_id = simple_accordion_handle_admin_actions();
	$groups            = simple_accordion_get_groups();

	if ( ! isset( $groups[ $selected_group_id ] ) ) {
		$selected_group_id = SIMPLE_ACCORDION_DEFAULT_GROUP_ID;
	}

	$group = isset( $groups[ $selected_group_id ] ) ? $groups[ $selected_group_id ] : simple_accordion_default_group();
	$items = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();
	?>
	<div class="wrap simple-accordion-admin">
		<h1><?php echo esc_html__( 'Simple Accordion', 'simple-accordion' ); ?></h1>
		<p><?php echo esc_html__( 'Create independent accordion groups and use each one with the shortcode ID.', 'simple-accordion' ); ?></p>

		<?php settings_errors( 'simple_accordion_messages' ); ?>

		<div class="simple-accordion-groups-panel">
			<form method="get" class="simple-accordion-group-select">
				<input type="hidden" name="page" value="simple-accordion">
				<label for="simple-accordion-group-select"><strong><?php echo esc_html__( 'Current group', 'simple-accordion' ); ?></strong></label>
				<select id="simple-accordion-group-select" name="group">
					<?php foreach ( $groups as $group_id => $group_data ) : ?>
						<option value="<?php echo esc_attr( $group_id ); ?>" <?php selected( $selected_group_id, $group_id ); ?>><?php echo esc_html( $group_id ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button"><?php echo esc_html__( 'Edit group', 'simple-accordion' ); ?></button>
			</form>

			<form method="post" class="simple-accordion-group-create">
				<?php wp_nonce_field( 'simple_accordion_manage_groups', 'simple_accordion_groups_nonce' ); ?>
				<input type="hidden" name="simple_accordion_action" value="create_group">
				<p>
					<label for="new-group-id"><?php echo esc_html__( 'New group ID (slug)', 'simple-accordion' ); ?></label>
					<input type="text" id="new-group-id" name="new_group_id" class="regular-text" placeholder="faq">
				</p>
				<p>
					<label for="new-group-title"><?php echo esc_html__( 'Group title (optional)', 'simple-accordion' ); ?></label>
					<input type="text" id="new-group-title" name="new_group_title" class="regular-text" placeholder="Frequently Asked Questions">
				</p>
				<p><button type="submit" class="button"><?php echo esc_html__( 'Create group', 'simple-accordion' ); ?></button></p>
			</form>

			<form method="post" class="simple-accordion-group-delete" onsubmit="return window.confirm('<?php echo esc_js( __( 'Delete this group? This cannot be undone.', 'simple-accordion' ) ); ?>');">
				<?php wp_nonce_field( 'simple_accordion_manage_groups', 'simple_accordion_groups_nonce' ); ?>
				<input type="hidden" name="simple_accordion_action" value="delete_group">
				<input type="hidden" name="delete_group_id" value="<?php echo esc_attr( $selected_group_id ); ?>">
				<p><label><input type="checkbox" name="confirm_delete_group" value="1"> <?php echo esc_html__( 'I understand this group will be permanently deleted.', 'simple-accordion' ); ?></label></p>
				<p><button type="submit" class="button button-secondary" <?php disabled( SIMPLE_ACCORDION_DEFAULT_GROUP_ID, $selected_group_id ); ?>><?php echo esc_html__( 'Delete selected group', 'simple-accordion' ); ?></button></p>
			</form>
		</div>

		<form method="post">
			<?php wp_nonce_field( 'simple_accordion_save_group', 'simple_accordion_nonce' ); ?>
			<input type="hidden" name="simple_accordion_action" value="save_group">
			<input type="hidden" name="group_id" value="<?php echo esc_attr( $selected_group_id ); ?>">

			<table class="form-table simple-accordion-group-meta" role="presentation">
				<tr>
					<th scope="row"><label for="group-title"><?php echo esc_html__( 'Group title', 'simple-accordion' ); ?></label></th>
					<td><input type="text" id="group-title" name="group_title" class="regular-text" value="<?php echo esc_attr( $group['title'] ); ?>"><p class="description"><?php echo esc_html__( 'Optional metadata for this group. Frontend heading remains disabled by default.', 'simple-accordion' ); ?></p></td>
				</tr>
			</table>

			<table class="widefat striped simple-accordion-table">
				<thead>
					<tr>
						<th class="simple-accordion-order"><?php echo esc_html__( 'Order', 'simple-accordion' ); ?></th>
						<th><?php echo esc_html__( 'Title', 'simple-accordion' ); ?></th>
						<th><?php echo esc_html__( 'Content', 'simple-accordion' ); ?></th>
						<th class="simple-accordion-open"><?php echo esc_html__( 'Open', 'simple-accordion' ); ?></th>
						<th class="simple-accordion-actions"><?php echo esc_html__( 'Action', 'simple-accordion' ); ?></th>
					</tr>
				</thead>
				<tbody id="simple-accordion-rows">
					<?php foreach ( $items as $index => $item ) : ?>
						<tr>
							<td><input type="number" min="1" class="small-text" name="items[<?php echo esc_attr( $index ); ?>][order]" value="<?php echo esc_attr( $item['order'] ); ?>"></td>
							<td><input type="text" class="regular-text" name="items[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $item['title'] ); ?>" placeholder="<?php echo esc_attr__( 'Accordion title', 'simple-accordion' ); ?>"></td>
							<td><textarea name="items[<?php echo esc_attr( $index ); ?>][content]" rows="4" placeholder="<?php echo esc_attr__( 'Text or safe HTML content', 'simple-accordion' ); ?>"><?php echo esc_textarea( $item['content'] ); ?></textarea></td>
							<td class="simple-accordion-open"><label><input type="checkbox" name="items[<?php echo esc_attr( $index ); ?>][open]" value="1" <?php checked( $item['open'] ); ?>><span class="screen-reader-text"><?php echo esc_html__( 'Open by default', 'simple-accordion' ); ?></span></label></td>
							<td class="simple-accordion-actions"><button type="button" class="button-link-delete simple-accordion-remove"><?php echo esc_html__( 'Remove', 'simple-accordion' ); ?></button></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p><button type="button" class="button" id="simple-accordion-add"><?php echo esc_html__( 'Add item', 'simple-accordion' ); ?></button> <input type="submit" class="button button-primary" value="<?php echo esc_attr__( 'Save group', 'simple-accordion' ); ?>"></p>
		</form>

		<div class="simple-accordion-shortcode-help"><strong><?php echo esc_html__( 'Shortcodes:', 'simple-accordion' ); ?></strong> <code>[simple_accordion]</code> <code>[simple_accordion id="<?php echo esc_attr( $selected_group_id ); ?>"]</code></div>
	</div>

	<script>
	(function () {
		var addButton = document.getElementById('simple-accordion-add');
		var rows = document.getElementById('simple-accordion-rows');
		var index = rows ? rows.children.length : 0;
		if (!addButton || !rows) return;
		addButton.addEventListener('click', function () {
			var row = document.createElement('tr');
			row.innerHTML = '<td><input type="number" min="1" class="small-text" name="items[' + index + '][order]" value="' + (index + 1) + '"></td><td><input type="text" class="regular-text" name="items[' + index + '][title]" placeholder="Accordion title"></td><td><textarea name="items[' + index + '][content]" rows="4" placeholder="Text or safe HTML content"></textarea></td><td class="simple-accordion-open"><label><input type="checkbox" name="items[' + index + '][open]" value="1"><span class="screen-reader-text">Open by default</span></label></td><td class="simple-accordion-actions"><button type="button" class="button-link-delete simple-accordion-remove">Remove</button></td>';
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
	$atts = shortcode_atts(
		array(
			'id'    => SIMPLE_ACCORDION_DEFAULT_GROUP_ID,
			'title' => '',
		),
		$atts,
		'simple_accordion'
	);

	$raw_id = isset( $atts['id'] ) ? (string) $atts['id'] : '';
	if ( '' === trim( $raw_id ) ) {
		$raw_id = SIMPLE_ACCORDION_DEFAULT_GROUP_ID;
	}

	$group_id = simple_accordion_sanitize_group_id( $raw_id );
	$group    = simple_accordion_get_group( $group_id );

	if ( ! is_array( $group ) || empty( $group['items'] ) || ! is_array( $group['items'] ) ) {
		return '';
	}

	simple_accordion_enqueue_frontend_assets();

	$instance_id = wp_unique_id( 'simple-accordion-' );
	$output      = '<section class="simple-accordion" id="' . esc_attr( $instance_id ) . '">';

	foreach ( $group['items'] as $index => $item ) {
		$is_open  = ! empty( $item['open'] );
		$item_id  = $instance_id . '-item-' . ( $index + 1 );
		$panel_id = $item_id . '-panel';
		$classes  = 'simple-accordion__item' . ( $is_open ? ' is-open' : '' );

		$output .= '<div class="' . esc_attr( $classes ) . '">';
		$output .= '<button id="' . esc_attr( $item_id ) . '" type="button" class="simple-accordion__button" aria-expanded="' . ( $is_open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $panel_id ) . '">';
		$output .= '<span class="simple-accordion__icon" aria-hidden="true">' . ( $is_open ? '−' : '+' ) . '</span>';
		$output .= '<span class="simple-accordion__label">' . esc_html( $item['title'] ) . '</span>';
		$output .= '</button>';
		$output .= '<div id="' . esc_attr( $panel_id ) . '" class="simple-accordion__panel" role="region" aria-labelledby="' . esc_attr( $item_id ) . '"' . ( $is_open ? '' : ' hidden' ) . '>';
		$output .= '<div class="simple-accordion__content">' . wp_kses_post( wpautop( $item['content'] ) ) . '</div>';
		$output .= '</div></div>';
	}

	return $output . '</section>';
}
add_shortcode( 'simple_accordion', 'simple_accordion_shortcode' );
