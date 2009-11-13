<?php
/**
 * Theme editor administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once('admin.php');

if ( !current_user_can('edit_themes') )
	wp_die('<p>'.__('You do not have sufficient permissions to edit templates for this blog.').'</p>');

$title = __("Edit Themes");
$parent_file = 'themes.php';

wp_reset_vars(array('action', 'redirect', 'profile', 'error', 'warning', 'a', 'file', 'theme', 'dir'));

wp_admin_css( 'theme-editor' );

$themes = get_themes();

if (empty($theme)) {
	$theme = get_current_theme();
} else {
	$theme = stripslashes($theme);
}

if ( ! isset($themes[$theme]) )
	wp_die(__('The requested theme does not exist.'));

$allowed_files = array_merge($themes[$theme]['Stylesheet Files'], $themes[$theme]['Template Files']);

if (empty($file)) {
	$file = $allowed_files[0];
} else {
	if ( 'theme' == $dir ) {
		$file = dirname(dirname($themes[$theme]['Template Dir'])) . $file ; 
	} else if ( 'style' == $dir) {
		$file = dirname(dirname($themes[$theme]['Stylesheet Dir'])) . $file ; 
	}
}

$real_file = validate_file_to_edit($file, $allowed_files);
$scrollto = isset($_REQUEST['scrollto']) ? (int) $_REQUEST['scrollto'] : 0;

$file_show = basename( $file );

switch($action) {

case 'update':

	check_admin_referer('edit-theme_' . $file . $theme);

	$newcontent = stripslashes($_POST['newcontent']);
	$theme = urlencode($theme);
	if (is_writeable($real_file)) {
		//is_writable() not always reliable, check return value. see comments @ http://uk.php.net/is_writable
		$f = fopen($real_file, 'w+');
		if ($f !== FALSE) {
			fwrite($f, $newcontent);
			fclose($f);
			$location = "theme-editor.php?file=$file&theme=$theme&a=te&scrollto=$scrollto";
		} else {
			$location = "theme-editor.php?file=$file&theme=$theme&scrollto=$scrollto";
		}
	} else {
		$location = "theme-editor.php?file=$file&theme=$theme&scrollto=$scrollto";
	}

	$location = wp_kses_no_null($location);
	$strip = array('%0d', '%0a', '%0D', '%0A');
	$location = _deep_replace($strip, $location);
	header("Location: $location");
	exit();

break;

default:

	require_once('admin-header.php');

	update_recently_edited($file);

	if ( !is_file($real_file) )
		$error = 1;

	if ( !$error && filesize($real_file) > 0 ) {
		$f = fopen($real_file, 'r');
		$content = fread($f, filesize($real_file));

		if ( '.php' == substr( $real_file, strrpos( $real_file, '.' ) ) ) {
			$functions = wp_doc_link_parse( $content );

			$docs_select = '<select name="docs-list" id="docs-list">';
			$docs_select .= '<option value="">' . esc_attr__( 'Function Name...' ) . '</option>';
			foreach ( $functions as $function ) {
				$docs_select .= '<option value="' . esc_attr( urlencode( $function ) ) . '">' . htmlspecialchars( $function ) . '()</option>';
			}
			$docs_select .= '</select>';
		}

		$content = htmlspecialchars( $content );
		$codepress_lang = codepress_get_lang($real_file);
	}

	?>
<?php if (isset($_GET['a'])) : ?>
 <div id="message" class="updated fade"><p><?php _e('File edited successfully.') ?></p></div>
<?php endif;

$description = get_file_description($file);
$desc_header = ( $description != $file_show ) ? "<strong>$description</strong> (%s)" : "%s";
?>
<div class="wrap">
<?php screen_icon(); ?>
<h2><?php echo esc_html( $title ); ?></h2>

<div class="fileedit-sub">
<div class="alignleft">
<big><?php echo sprintf($desc_header, $file_show); ?></big>
</div>
<div class="alignright">
	<form action="theme-editor.php" method="post">
		<strong><label for="theme"><?php _e('Select theme to edit:'); ?> </label></strong>
		<select name="theme" id="theme">
<?php
	foreach ($themes as $a_theme) {
	$theme_name = $a_theme['Name'];
	if ($theme_name == $theme) $selected = " selected='selected'";
	else $selected = '';
	$theme_name = esc_attr($theme_name);
	echo "\n\t<option value=\"$theme_name\" $selected>$theme_name</option>";
}
?>
		</select>
		<input type="submit" name="Submit" value="<?php esc_attr_e('Select') ?>" class="button" />
	</form>
</div>
<br class="clear" />
</div>
<br class="clear" />
	<div id="templateside">
	<h3><?php _e("Theme Files"); ?></h3>

<?php
if ($allowed_files) :
?>
	<h4><?php _e('Templates'); ?></h4>
	<ul>
<?php
	$template_mapping = array();
	$template_dir = $themes[$theme]['Template Dir'];
	foreach ( $themes[$theme]['Template Files'] as $template_file ) {
		$description = trim( get_file_description($template_file) );
		$template_show = basename($template_file);
		$filedesc = ( $description != $template_file ) ? "$description <span class='nonessential'>($template_show)</span>" : "$description";
		$filedesc = ( $template_file == $file ) ? "<span class='highlight'>$description <span class='nonessential'>($template_show)</span></span>" : $filedesc;

		// If we have two files of the same name prefer the one in the Template Directory
		// This means that we display the correct files for child themes which overload Templates as well as Styles
		if( array_key_exists($description, $template_mapping ) ) {
			if ( false !== strpos( $template_file, $template_dir ) )  {
				$template_mapping[ $description ] = array( _get_template_edit_filename($template_file, $template_dir), $filedesc );
			}
		} else {
			$template_mapping[ $description ] = array( _get_template_edit_filename($template_file, $template_dir), $filedesc );
		}
	}
	ksort( $template_mapping );
	while ( list( $template_sorted_key, list( $template_file, $filedesc ) ) = each( $template_mapping ) ) :
	?>
		<li><a href="theme-editor.php?file=<?php echo "$template_file"; ?>&amp;theme=<?php echo urlencode($theme) ?>&amp;dir=theme"><?php echo $filedesc ?></a></li>
<?php endwhile; ?>
	</ul>
	<h4><?php /* translators: Theme stylesheets in theme editor */ echo _x('Styles', 'Theme stylesheets in theme editor'); ?></h4>
	<ul>
<?php
	$template_mapping = array();
	$stylesheet_dir = $themes[$theme]['Stylesheet Dir'];
	foreach ( $themes[$theme]['Stylesheet Files'] as $style_file ) {
		$description = trim( get_file_description($style_file) );
		$style_show = basename($style_file);
		$filedesc = ( $description != $style_file ) ? "$description <span class='nonessential'>($style_show)</span>" : "$description";
		$filedesc = ( $style_file == $file ) ? "<span class='highlight'>$description <span class='nonessential'>($style_show)</span></span>" : $filedesc;
		$template_mapping[ $description ] = array( _get_template_edit_filename($style_file, $stylesheet_dir), $filedesc );
	}
	ksort( $template_mapping );
	while ( list( $template_sorted_key, list( $style_file, $filedesc ) ) = each( $template_mapping ) ) :
		?>
		<li><a href="theme-editor.php?file=<?php echo "$style_file"; ?>&amp;theme=<?php echo urlencode($theme) ?>&amp;dir=style"><?php echo $filedesc ?></a></li>
<?php endwhile; ?>
	</ul>
<?php endif; ?>
</div>
<?php if (!$error) { ?>
	<form name="template" id="template" action="theme-editor.php" method="post">
	<?php wp_nonce_field('edit-theme_' . $file . $theme) ?>
		 <div><textarea cols="70" rows="25" name="newcontent" id="newcontent" tabindex="1" class="codepress <?php echo $codepress_lang ?>"><?php echo $content ?></textarea>
		 <input type="hidden" name="action" value="update" />
		 <input type="hidden" name="file" value="<?php echo esc_attr($file) ?>" />
		 <input type="hidden" name="theme" value="<?php echo esc_attr($theme) ?>" />
		 <input type="hidden" name="scrollto" id="scrollto" value="<?php echo $scrollto; ?>" />
		 </div>
	<?php if ( isset($functions ) && count($functions) ) { ?>
		<div id="documentation">
		<label for="docs-list"><?php _e('Documentation:') ?></label>
		<?php echo $docs_select; ?>
		<input type="button" class="button" value=" <?php esc_attr_e( 'Lookup' ); ?> " onclick="if ( '' != jQuery('#docs-list').val() ) { window.open( 'http://api.wordpress.org/core/handbook/1.0/?function=' + escape( jQuery( '#docs-list' ).val() ) + '&locale=<?php echo urlencode( get_locale() ) ?>&version=<?php echo urlencode( $wp_version ) ?>&redirect=true'); }" />
		</div>
	<?php } ?>

		<div>
<?php if ( is_writeable($real_file) ) : ?>
			<p class="submit">
<?php
	echo "<input type='submit' name='submit' class='button-primary' value='" . esc_attr__('Update File') . "' tabindex='2' />";
?>
</p>
<?php else : ?>
<p><em><?php _e('You need to make this file writable before you can save your changes. See <a href="http://codex.wordpress.org/Changing_File_Permissions">the Codex</a> for more information.'); ?></em></p>
<?php endif; ?>
		</div>
	</form>
<?php
	} else {
		echo '<div class="error"><p>' . __('Oops, no such file exists! Double check the name and try again, merci.') . '</p></div>';
	}
?>
<br class="clear" />
</div>
<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready(function($){
	$('#template').submit(function(){ $('#scrollto').val( $('#newcontent').scrollTop() ); });
	$('#newcontent').scrollTop( $('#scrollto').val() );
});
/* ]]> */
</script>
<?php
break;
}

include("admin-footer.php");
