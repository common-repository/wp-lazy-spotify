<?php
/*

Plugin Name: WP Lazy Spotify
Description: Lazy loading for spotify
Version: 1.0
License: GPL
Plugin URI: http://twmorton.com
Author: Tom Morton
Author URI: http://twmorton.com.com/
Text Domain: wp-lazy-spotify

=================================================================

Copyright 2013 Tom Morton

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

//Lets Go!

class WP_Lazy_Spotify {
	public static $instance;
	private $options;

	const OPTION        = 'wp_lazy_spotify';
	const PAGE_SLUG     = 'wp_lazy_spotify';
	const VERSION       = '1.0';

	function __construct() {
		self::$instance = $this;
		add_action( 'init',         	array( $this, 'init'         	)			);
		add_action( 'admin_init',   	array( $this, 'admin_init'   	)     		);
		add_action( 'do_meta_boxes', 	array( $this, 'do_meta_boxes'  	), 20, 2 	);
		add_action( 'save_post',     	array( $this, 'save_post'      	)        	);
		add_filter( 'the_content',  	array( $this, 'the_content'  	),  9 		);
		add_shortcode( 'wplazyspotify', array( $this, 'shortcode' 		)			);

	}

	public function init(){

		wp_enqueue_script( 'socialitejs', 		plugins_url( 'Socialite/socialite.min.js', __FILE__ ), 					array( 'jquery' ), 	self::VERSION );
		wp_enqueue_script( 'socialite-spotify', plugins_url( 'Socialite/extensions/socialite.spotify.js', __FILE__ ), 	array( 'jquery' ), 	self::VERSION );
		wp_enqueue_script( 'wplazyspotify', 	plugins_url( 'wplazyspotify.js', __FILE__ ), 							array( 'jquery' ), 	self::VERSION );

		wp_enqueue_style( 'wplazyspotify-css', 	plugins_url( 'wplazyspotify.css', __FILE__ ), '', 											self::VERSION );

	}

	public function admin_init() {

		$page = 'media';
		$section = 'wplazyspotify_section';

		add_settings_section(
			$id         = 'wplazyspotify_section',
			$title      = __('WP Lazy Spotify','wplazyspotify'),
			$callback   = array(&$this,'settings_section_callback'),
			$page       = $page
		);
		add_settings_field(
			$id         = 'wplazyspotify_mode',
			$title      = __('Mode','wplazyspotify'),
			$callback   = array( &$this, 'settings_field_select' ),
			$page       = $page,
			$section    = $section,
			$args       = array(
				'name'        => 'wplazyspotify_mode',
				'description' => _('Choose the event to which Socialite will activate'),
				'options'     => array(
						'hover'     => _('Hover'),
						'scroll'    => _('Scroll'),
					),
			)
		);
		register_setting( $option_group = 'media', $option_name = 'wplazyspotify_mode' );

		add_settings_field(
			$id         = 'wplazyspotify_position',
			$title      = __('Position','wplazyspotify'),
			$callback   = array( &$this, 'settings_field_select' ),
			$page       = $page,
			$section    = $section,
			$args       = array(
				'name'        => 'wplazyspotify_position',
				'description' => sprintf(__('Choose where you would like the social icons to appear, before or after the main content. If set to <strong>Manual</strong>, you can use this code to place your Social links anywhere you like in your templates files: %s','wplazyspotify'),'<pre>&lt;?php wp_lazy_spotify(); ?&gt;</pre>'),
				'options'     => array(
						'top'       => _('Top'),
						'bottom'    => _('Bottom'),
						'manual'    => _('Manual'),
					),
			)
		);
		register_setting( $option_group = 'media', $option_name = 'wplazyspotify_position' );

	}

	public function settings_section_callback() {
		_e('You can specify if you want your spotify tracks to load automatically or manually via a template tag or shortcode.');
	}

	public function settings_field_select($args){

		if ( empty( $args['name'] ) || ! is_array( $args['options'] ) )
			return false;

		$selected = ( isset( $args['name'] ) ) ? get_option($args['name']) : '';

		echo '<select name="' . esc_attr( $args['name'] ) . '">';

			foreach ( (array) $args['options'] as $value => $label ){
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $value, $selected, false ) . '>' . $label . '</option>';
			}
		echo '</select>';

		if ( ! empty( $args['description'] ) )
			echo ' <p class="description">' . $args['description'] . '</p>';

	}

	public function the_content($content){
		$position = get_option('wplazyspotify_position');
		$wplazyspotify = self::the_markup();
		switch( $position ) {
			case 'manual' :
				//User is implementing button via template tag, do not filter
			break;
			case 'top' :
				$content = $wplazyspotify . $content;
			break;
			case 'bottom' ;
				$content = $content . $wplazyspotify;
			break;
		}
		return $content;
	}

	public function the_markup($args = null) {

		// use the wp_parse_arg paradigm to permit easy addition of parameters in the future.
		$default_args = array(
			'size'	=>	'large',
			'url' 	=>	''
		);
		extract(wp_parse_args($args,$default_args),EXTR_SKIP);
		global $wp_query;
		$post = $wp_query->post; //get post content
		$songmeta = get_post_meta( $post->ID, '_wplazyspotify_song_url', true );
		$songurl = '';
		if($args['url']){
			$songurl = $args['url'];
		} elseif ($songmeta){
			$songurl = $songmeta;
		}

		$button = self::spotify_button($songurl, $args['size']);

		$return = '<div class="wplazyspotify '.$args['size'].'">';
			$return .= '<div class="spotify">'.$button['markup'].'</div>';
		$return .= '</div>';

		return $return;

	}

	public function spotify_button($song = null, $height = null) {
		$locale = get_locale();
		if($height === 'small'){
			$height = '80';
		}
		$buttons = array(
			'name' => 'Spotify',
			'slug' => 'spotify',
			'markup' => '<a href="'.$song.'" class="socialite spotify-play" title="'.apply_filters('wpsocialite_share_facebook_label',__('Listen To Song.','wpsocialite')).'" data-height="'.$height.'"></a>',
			'external_file' => false

		);

		return $buttons;
	}

	public function do_meta_boxes($page, $context) {
		global $post;

		$this->post_type = 'post';

		if ( $this->post_type === $page && 'normal' === $context )
			add_meta_box(
				'_wplazyspotify_meta',
				__( 'WP Lazy Spotify Meta', 'wplazyspotify' ),
				array( $this, 'meta_box' ),
				$page,
				'side',
				'high'
			);
	}

	public function meta_box() {
		global $post;
		echo '<p>' . __( 'Enter the URL of the Spotify track for this post.', 'wplazyspotify' ) . '</p>';
		?>

		<label for="wplazyspotify-url-field"><?php _e( 'Spotify Song URL:', 'wplazyspotify' ); ?></label>
		<input id="wplazyspotify-slug-field" class="input" name="wplazyspotify_song_url" type="text" value="<?php echo esc_attr( get_post_meta( $post->ID, '_wplazyspotify_song_url', true ) ); ?>" />
		<p class="description"><small><?php _e('Example:<br /> http://open.spotify.com/track/7LImTa7vwDT3dDFBzq5wfw'); ?></small></p>
		<?php wp_nonce_field( 'wplazyspotify_check', '_wplazyspotify_meta_nonce', false, true ); ?>

	<?php
	}

	public function save_post( $id ) {
		if ( isset( $_REQUEST['_wplazyspotify_meta_nonce'] ) && wp_verify_nonce( $_REQUEST['_wplazyspotify_meta_nonce'], 'wplazyspotify_check' ) ) {
			if ( strlen( $_REQUEST['wplazyspotify_song_url'] ) > 0 )
				update_post_meta( $id, '_wplazyspotify_song_url', stripslashes( $_REQUEST['wplazyspotify_song_url'] ) );
			else
				delete_post_meta( $id, '_wplazyspotify_song_url' );
		}
		return $id;
	}

	public function shortcode($atts){
		extract( shortcode_atts( array(
			'size' 	=> 'small',
			'url' 	=> ''
		), $atts ) );

		return self::the_markup($atts);
	}

} //end class WP_lazy_Spotify

new WP_lazy_Spotify;



// Template Tags
function wp_lazy_spotify($args = null){
	$output = get_wplazyspotify($args);
	echo $output;
}
function get_wplazyspotify($args){
	$return = WP_Lazy_Spotify::the_markup($args);
	return $return;
}