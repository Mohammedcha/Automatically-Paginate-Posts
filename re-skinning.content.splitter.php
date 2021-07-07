<?php

/*
Plugin Name: Automatically Paginate Posts
Plugin URI: https://re-skinning.com
Description: Automatically inserts the &lt;!--nextpage--&gt; Quicktag into WordPress posts, pages, or custom post type content.
Version: 0.1
Author: Mohammed Cha
Author URI: https://www.re-skinning.com/s
*/

class Automatically_Paginate_Posts {
	private $post_types;
	private $post_types_default = array( 'post' );
	private $num_pages;
	private $paging_type_default = 'pages';
	private $num_pages_default   = 2;
	private $num_words_default   = '';
	private $paging_types_allowed = array( 'pages', 'words' );
	private $option_name_post_types  = 'autopaging_post_types';
	private $option_name_paging_type = 'pages';
	private $option_name_num_pages   = 'autopaging_num_pages';
	private $option_name_num_words   = 'autopaging_num_words';
	private $meta_key_disable_autopaging = '_disable_autopaging';
	public function __construct() {
		add_action( 'init', array( $this, 'action_init' ) );
		register_uninstall_hook( __FILE__, array( 'Automatically_Paginate_Posts', 'uninstall' ) );
		add_filter( 'plugin_action_links', array( $this, 'filter_plugin_action_links' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'add_meta_boxes', array( $this, 'action_add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'action_save_post' ) );
		add_filter( 'the_posts', array( $this, 'filter_the_posts' ) );
	}
	public function action_init() {
		$this->post_types = apply_filters( 'autopaging_post_types', get_option( $this->option_name_post_types, $this->post_types_default ) );
		$this->num_pages = absint( apply_filters( 'autopaging_num_pages_default', get_option( $this->option_name_num_pages, $this->num_pages_default ) ) );
		if ( 0 == $this->num_pages )
			$this->num_pages = $this->num_pages_default;
		$this->num_words = absint( apply_filters( 'autopaging_num_words_default', get_option( $this->option_name_num_words, $this->num_words_default ) ) );
		if ( 0 == $this->num_words )
			$this->num_words = $this->num_words_default;
	}
	public function uninstall() {
		delete_option( 'autopaging_post_types' );
		delete_option( 'autopaging_paging_type' );
		delete_option( 'autopaging_num_pages' );
		delete_option( 'autopaging_num_words' );
	}
	public function filter_plugin_action_links( $actions, $file ) {
		if ( false !== strpos( $file, basename( __FILE__ ) ) )
			$actions[ 'settings' ] = '<a href="' . admin_url( 'options-reading.php' ) . '">Settings</a>';

		return $actions;
	}
	public function action_admin_init() {
		register_setting( 'reading', $this->option_name_post_types, array( $this, 'sanitize_supported_post_types' ) );
		register_setting( 'reading', $this->option_name_paging_type, array( $this, 'sanitize_paging_type' ) );
		register_setting( 'reading', $this->option_name_num_pages, array( $this, 'sanitize_num_pages' ) );
		register_setting( 'reading', $this->option_name_num_words, array( $this, 'sanitize_num_words' ) );
		add_settings_section( 'autopaging', __( 'Automatically Paginate Posts', 'autopaging' ), '__return_false', 'reading' );
		add_settings_field( 'autopaging-post-types', __( 'Supported post types:', 'autopaging' ), array( $this, 'settings_field_post_types' ), 'reading', 'autopaging' );
		add_settings_field( 'autopaging-paging-type', __( 'Split post by:', 'autopaging' ), array( $this, 'settings_field_paging_type' ), 'reading', 'autopaging' );
	}
	public function settings_field_post_types() {
		$post_types = get_post_types( array(
			'public' => true
		), 'objects' );
		unset( $post_types[ 'attachment' ] );
		$current_types = get_option( $this->option_name_post_types, $this->post_types_default );
		foreach ( $post_types as $post_type => $atts ) :
		?>
			<input type="checkbox" name="<?php echo esc_attr( $this->option_name_post_types ); ?>[]" id="post-type-<?php echo esc_attr( $post_type ); ?>" value="<?php echo esc_attr( $post_type ); ?>"<?php checked( in_array( $post_type, $current_types ) ); ?> /> <label for="post-type-<?php echo esc_attr( $post_type ); ?>"><?php echo esc_html( $atts->label ); ?></label><br />
		<?php
		endforeach;
	}
	public function sanitize_supported_post_types( $post_types_checked ) {
		$post_types_sanitized = array();
		if ( is_array( $post_types_checked ) && ! empty( $post_types_checked ) ) {
			$post_types = get_post_types( array(
				'public' => true
			) );
			unset( $post_types[ 'attachment' ] );
			foreach ( $post_types_checked as $post_type ) {
				if ( array_key_exists( $post_type, $post_types ) )
					$post_types_sanitized[] = $post_type;
			}
		}
		return $post_types_sanitized;
	}
	public function settings_field_paging_type() {
		$paging_type = get_option( $this->option_name_paging_type, $this->paging_type_default );
		if ( ! in_array( $paging_type, $this->paging_types_allowed ) ) {
			$paging_type = $this->paging_type_default;
		}
		$labels = array(
			'pages' => __( 'Total number of pages: ', 'autopaging' ),
			'words' => __( 'Approximate words per page: ', 'autopaging' ),
		);
		foreach ( $this->paging_types_allowed as $type ) :
			$type_escaped = esc_attr( $type );
			$func = 'settings_field_num_' . $type;
			?>
			<p><input type="radio" name="<?php echo esc_attr( $this->option_name_paging_type ); ?>" id="autopaging-type-<?php echo $type_escaped; ?>" value="<?php echo $type_escaped; ?>"<?php checked( $type, $paging_type ); ?> /> <label for="autopaging-type-<?php echo $type_escaped; ?>">
				<strong><?php echo $labels[ $type ]; ?></strong><?php $this->{$func}(); ?>
			</label></p>
		<?php endforeach;
	}
	public function sanitize_paging_type( $type ) {
		return in_array( $type, $this->paging_types_allowed ) ? $type : $this->paging_type_default;
	}
	public function settings_field_num_pages() {
		$num_pages = get_option( $this->option_name_num_pages, $this->num_pages_default );
		$max_pages = apply_filters( 'autopaging_max_num_pages', 10 );
		?>
			<select name="<?php echo esc_attr( $this->option_name_num_pages ); ?>">
				<?php for( $i = 2; $i <= $max_pages; $i++ ) : ?>
					<option value="<?php echo intval( $i ); ?>"<?php selected( (int) $i, (int) $num_pages ); ?>><?php echo intval( $i ); ?></option>
				<?php endfor; ?>
			</select>
		<?php
	}
	public function sanitize_num_pages( $num_pages ) {
		return max( 2, min( intval( $num_pages ), apply_filters( 'autopaging_max_num_pages', 10 ) ) );
	}
	public function settings_field_num_words() {
		$num_words = apply_filters( 'autopaging_num_words', get_option( $this->option_name_num_words ) )
		?>
			<input name="<?php echo esc_attr( $this->option_name_num_words ); ?>" value="<?php echo esc_attr( $num_words ); ?>" size="4" />
			<p class="description"><?php _e( 'If chosen, each page will contain approximately this many words, depending on paragraph lengths.', 'autopaging' ); ?></p>
		<?php
	}
	public function sanitize_num_words( $num_words ) {
		$num_words = absint( $num_words );
		if ( ! $num_words ) {
			return 0;
		}
		return max( $num_words, apply_filters( 'autopaging_min_num_words', 10 ) );
	}
	public function action_add_meta_boxes() {
		foreach ( $this->post_types as $post_type ) {
			add_meta_box( 'autopaging', __( 'Post Autopaging', 'autopaging' ), array( $this, 'meta_box_autopaging' ), $post_type, 'side' );
		}
	}
	public function meta_box_autopaging( $post ) {
	?>
		<p>
			<input type="checkbox" name="<?php echo esc_attr( $this->meta_key_disable_autopaging ); ?>" id="<?php echo esc_attr( $this->meta_key_disable_autopaging ); ?>_checkbox" value="1"<?php checked( (bool) get_post_meta( $post->ID, $this->meta_key_disable_autopaging, true ) ); ?> /> <label for="<?php echo esc_attr( $this->meta_key_disable_autopaging ); ?>_checkbox">Disable autopaging for this post?</label>
		</p>
		<p class="description"><?php _e( 'Check the box above to prevent this post from automatically being split over multiple pages.', 'autopaging' ); ?></p>
		<p class="description"><?php printf( __( 'Note that if the %1$s Quicktag is used to manually page this post, automatic paging won\'t be applied, regardless of the setting above.', 'autopaging' ), '<code>&lt;!--nextpage--&gt;</code>' ); ?></p>
	<?php
		wp_nonce_field( $this->meta_key_disable_autopaging, $this->meta_key_disable_autopaging . '_wpnonce' );
	}
	public function action_save_post( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;
		if ( isset( $_POST[ $this->meta_key_disable_autopaging . '_wpnonce' ] ) && wp_verify_nonce( $_POST[ $this->meta_key_disable_autopaging . '_wpnonce' ], $this->meta_key_disable_autopaging ) ) {
			$disable = isset( $_POST[ $this->meta_key_disable_autopaging ] ) ? true : false;
			if ( $disable )
				update_post_meta( $post_id, $this->meta_key_disable_autopaging, true );
			else
				delete_post_meta( $post_id, $this->meta_key_disable_autopaging );
		}
	}
	public function filter_the_posts( $posts ) {
		if ( ! is_admin() ) {
			foreach( $posts as $the_post ) {
				if ( in_array( $the_post->post_type, $this->post_types ) && ! preg_match( '#<!--nextpage-->#i', $the_post->post_content ) && ! (bool) get_post_meta( $the_post->ID, $this->meta_key_disable_autopaging, true ) ) {
					$num_pages = absint( apply_filters( 'autopaging_num_pages', absint( $this->num_pages ), $the_post ) );
					$num_words = absint( apply_filters( 'autopaging_num_words', absint( $this->num_words ), $the_post ) );
					if ( $num_pages < 2 && empty( $num_words ) )
						continue;
					$content = $the_post->post_content;
					$content = preg_replace( '#<p>(.+?)</p>#i', "$1\r\n\r\n", $content );
					$content = preg_replace( '#<br(\s*/)?>#i', "\r\n", $content );
					$count = preg_match_all( '#\r\n\r\n#', $content, $matches );
					if ( is_int( $count ) && 0 < $count ) {
						$content = explode( "\r\n\r\n", $content );
						switch ( get_option( $this->option_name_paging_type, $this->paging_type_default ) ) {
							case 'words' :
								$word_counter = 0;
								foreach ( $content as $index => $paragraph ) {
									$paragraph_words = count( preg_split( '/\s+/', strip_tags( $paragraph ) ) );
									$word_counter += $paragraph_words;
									if ( $word_counter >= $num_words ) {
										$content[ $index ] .= '<!--nextpage-->';
										$word_counter = 0;
									} else {
										continue;
									}
								}
								unset( $word_counter );
								unset( $index );
								unset( $paragraph );
								unset( $paragraph_words );
								break;
							case 'pages' :
							default :
								$count = count( $content );
								$insert_every = $count / $num_pages;
								$insert_every_rounded = round( $insert_every );
								if ( $num_pages > $count ) {
									$insert_every_rounded = 1;
								}
								$i = $count - 1 == $num_pages ? 2 : 1;
								foreach ( $content as $key => $value ) {
									if ( $key + 1 == $count ) {
										break;
									}

									if ( ( $key + 1 ) == ( $i * $insert_every_rounded ) ) {
										$content[ $key ] = $content[ $key ] . '<!--nextpage-->';
										$i++;
									}
								}
								unset( $count );
								unset( $insert_every );
								unset( $insert_every_rounded );
								unset( $key );
								unset( $value );
								break;
						}
						$content = implode( "\r\n\r\n", $content );
						$the_post->post_content = $content;
					}
					unset( $num_pages );
					unset( $num_words );
					unset( $content );
					unset( $count );
				}
			}
		}

		return $posts;
	}
}
new Automatically_Paginate_Posts;

