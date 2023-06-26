<?php
/*
Plugin Name: OPML Importer
Plugin URI: https://wordpress.org/extend/plugins/opml-importer/
Description: Import links in OPML format.
Author: wordpressdotorg
Author URI: https://wordpress.org/
Version: 0.3.2
Stable tag: 0.3.2
License: GPL version 2 or later - https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

if ( !defined('WP_LOAD_IMPORTERS') )
	return;

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}

/** Load WordPress Administration Bootstrap */
$parent_file = 'tools.php';
$submenu_file = 'import.php';
$title = __('Import Blogroll', 'opml-importer');

/**
 * OPML Importer
 *
 * @package WordPress
 * @subpackage Importer
 */
if ( class_exists( 'WP_Importer' ) ) {
class OPML_Import extends WP_Importer {

	function dispatch() {
		global $wpdb, $user_ID;
		$step = isset( $_POST['step'] ) ? $_POST['step'] : 0;
		$this->check_link_manager();

switch ($step) {
	case 0: {
		include_once( ABSPATH . 'wp-admin/admin-header.php' );
		if ( !current_user_can('manage_links') )
			wp_die(__('Cheatin&#8217; uh?', 'opml-importer'));

		$opmltype = 'blogrolling'; // default.
?>

<div class="wrap">
<?php
if ( version_compare( get_bloginfo( 'version' ), '3.8.0', '<' ) ) {
	screen_icon();
}
?>
<h2><?php _e('Import your blogroll from another system', 'opml-importer') ?> </h2>
<form enctype="multipart/form-data" action="admin.php?import=opml" method="post" name="blogroll">
<?php wp_nonce_field('import-bookmarks') ?>

<p><?php _e('If a program or website you use allows you to export your links or subscriptions as OPML you may import them here.', 'opml-importer'); ?></p>
<div style="width: 70%; margin: auto; height: 8em;">
<input type="hidden" name="step" value="1" />
<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo wp_max_upload_size(); ?>" />
<div style="width: 48%;" class="alignleft">
<h3><label for="opml_url"><?php _e('Specify an OPML URL:', 'opml-importer'); ?></label></h3>
<input type="text" name="opml_url" id="opml_url" size="50" class="code" style="width: 90%;" value="https://" />
</div>

<div style="width: 48%;" class="alignleft">
<h3><label for="userfile"><?php _e('Or choose from your local disk:', 'opml-importer'); ?></label></h3>
<input id="userfile" name="userfile" type="file" size="30" />
</div>

</div>

<?php
$categories = get_terms('link_category', array('get' => 'all'));

if ( is_wp_error( $categories ) ) { ?>
	<p><?php echo $categories->get_error_message(); ?></p>
<?php } else if ( is_array( $categories ) && ! empty( $categories ) ) {
?>
<p style="clear: both; margin-top: 1em;"><label for="cat_id"><?php _e('Now select a category you want to put these links in.', 'opml-importer') ?></label><br />
<?php _e('Category:', 'opml-importer') ?> <select name="cat_id" id="cat_id">
<?php foreach ($categories as $category) { ?>
<option value="<?php echo $category->term_id; ?>"><?php echo esc_html(apply_filters('link_category', $category->name)); ?></option>
<?php
	} // end foreach
} // end if
?>
</select></p>

<p class="submit"><input type="submit" name="submit" value="<?php esc_attr_e('Import OPML File', 'opml-importer') ?>" /></p>
</form>

</div>
<?php
		break;
	} // end case 0

	case 1: {
		check_admin_referer('import-bookmarks');

		include_once( ABSPATH . 'wp-admin/admin-header.php' );
		if ( !current_user_can('manage_links') )
			wp_die(__('Cheatin&#8217; uh?', 'opml-importer'));
?>
<div class="wrap">

<h2><?php _e('Importing...', 'opml-importer') ?></h2>
<?php
		$cat_id = isset( $_POST['cat_id'] ) ? abs( (int) $_POST['cat_id'] ) : 1;
		if ( $cat_id < 1 ) {
			$cat_id = 1;
		}

		$opml_url = $_POST['opml_url'];
		if ( isset($opml_url) && $opml_url != '' && $opml_url != 'https://' ) {
			$blogrolling = true;
		} else { // try to get the upload file.
			$overrides = array('test_form' => false, 'test_type' => false);
			$_FILES['userfile']['name'] .= '.txt';
			$file = wp_handle_upload($_FILES['userfile'], $overrides);

			if ( isset($file['error']) )
				wp_die($file['error']);

			$url = $file['url'];
			$opml_url = $file['file'];
			$blogrolling = false;
		}

		global $opml, $updated_timestamp, $all_links, $map, $names, $urls, $targets, $descriptions, $feeds;
		if ( isset($opml_url) && $opml_url != '' ) {
			if ( $blogrolling === true ) {
				$opml = wp_remote_fopen($opml_url);
			} else {
				$opml = file_get_contents($opml_url);
			}

			/** Load OPML Parser */
			include_once( ABSPATH . 'wp-admin/link-parse-opml.php' );

			$link_count    = count($names);
			$link_inserted = 0;
			for ( $i = 0; $i < $link_count; $i++ ) {
				$link = array(
					'link_url'         => $urls[$i],
					'link_name'        => $names[$i],
					'link_category'    => array( $cat_id ),
					'link_description' => $descriptions[$i],
					'link_owner'       => $user_ID,
					'link_rss'         => $feeds[$i],
				);
				if ( wp_insert_link( $link ) !== 0 ) {
					++$link_inserted;
					echo sprintf('<p>'.__('Inserted <strong>%s</strong>', 'opml-importer').'</p>', $names[$i]);
				}
			}
?>

<p>
<?php
	if ( is_null($cat_id) ) {
		printf(__('Inserted %1$d links. All done! Go <a href="%2$s">manage those links</a>.', 'opml-importer'), $link_inserted, 'link-manager.php');
	} else {
		printf(__('Inserted %1$d links into category %2$s. All done! Go <a href="%3$s">manage those links</a>.', 'opml-importer'), $link_inserted, $cat_id, 'link-manager.php');
	}
?>
</p>
<?php
} // end if got url
else
{
	echo "<p>" . __("You need to supply your OPML url. Press back on your browser and try again", 'opml-importer') . "</p>\n";
} // end else

if ( ! $blogrolling )
	do_action( 'wp_delete_file', $opml_url);
	@unlink($opml_url);
?>
</div>
<?php
		break;
	} // end case 1
} // end switch
	}

	// Check if the Link Manager is enabled
	function check_link_manager() {
		// The Link Manager has been disabled in WordPress >= 3.5.0, no need to do additional checks
		if ( version_compare( get_bloginfo( 'version' ), '3.8.0', '<' ) ) {
			return;
		}

		add_filter( 'pre_option_link_manager_enabled', '__return_true', 100 );
		$really_can_manage_links = current_user_can( 'manage_links' );
		remove_filter( 'pre_option_link_manager_enabled', '__return_true', 100 );

		if ( $really_can_manage_links ) {
			$plugins = get_plugins();

			// Check if the user has Link Manager plugin
			if ( empty( $plugins['link-manager/link-manager.php'] ) ) {
				if ( current_user_can( 'install_plugins' ) ) {
					$install_url = wp_nonce_url(
						self_admin_url( 'update.php?action=install-plugin&plugin=link-manager' ),
						'install-plugin_link-manager'
					);

					wp_die(
						sprintf(
							/* translators: %s: A link to install the Link Manager plugin. */
							__( 'If you are looking to use the OPML importer, please install the <a href="%s">Link Manager plugin</a>.' ),
							esc_url( $install_url )
						)
					);
				}
			} elseif ( is_plugin_inactive( 'link-manager/link-manager.php' ) ) {
				if ( current_user_can( 'activate_plugins' ) ) {
					$activate_url = wp_nonce_url(
						self_admin_url( 'plugins.php?action=activate&plugin=link-manager/link-manager.php' ),
						'activate-plugin_link-manager/link-manager.php'
					);

					wp_die(
						sprintf(
							/* translators: %s: A link to activate the Link Manager plugin. */
							__( 'Please activate the <a href="%s">Link Manager plugin</a> to use the OPML importer.' ),
							esc_url( $activate_url )
						)
					);
				}
			}
		}
	}
}

$opml_importer = new OPML_Import();

register_importer('opml', __('Blogroll', 'opml-importer'), __('Import links in OPML format.', 'opml-importer'), array(&$opml_importer, 'dispatch'));

} // class_exists( 'WP_Importer' )

function opml_importer_init() {
    load_plugin_textdomain( 'opml-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'opml_importer_init' );
