<?php
/**
 * Simple_Urls_Admin file.
 *
 * @package simple-urls
 */

/**
 * Simple_Urls_Admin class.
 */
class Simple_Urls_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
        /**
         * Commented to avoid create urls by self
         **/
		add_action( 'admin_menu', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'meta_box_save' ), 1, 2 );

		add_filter( 'post_updated_messages', array( $this, 'updated_message' ) );
        add_action( 'admin_menu', array( $this, 'add_settings'));
		add_action( 'manage_posts_custom_column', array( $this, 'columns_data' ) );
		add_filter( 'manage_edit-surl_columns', array( $this, 'columns_filter' ) );
	}

	/**
	 * Colum filter.
	 *
	 * @param  array $columns Columns.
	 *
	 * @return array          Filtered columns.
	 */
	public function columns_filter( $columns ) {

		$columns = array(
			'cb'        => '<input type="checkbox" />',
			'title'     => __( 'Title', 'simple-urls' ),
			'url'       => __( 'Redirect to', 'simple-urls' ),
			'permalink' => __( 'Permalink', 'simple-urls' ),
			'clicks'    => __( 'Clicks', 'simple-urls' ),
		);

		return $columns;

	}

	/**
	 * Columns data.
	 *
	 * @param  array $column Columns.
	 */
	public function columns_data( $column ) {

		global $post;

		$url   = get_post_meta( $post->ID, '_surl_redirect', true );
		$count = get_post_meta( $post->ID, '_surl_count', true );
        $token = get_post_meta( $post->ID, '_surl_token', true);

		$allowed_tags = array(
			'a' => array(
				'href' => array(),
				'rel'  => array(),
			),
		);

		if ( 'url' === $column ) {
			echo wp_kses( make_clickable( esc_url( $url ? $url : '' ) ), $allowed_tags );
		} elseif ( 'permalink' === $column ) {
            $permalink = get_permalink();
            if(isset($token) && $token != ''){
                echo wp_kses( make_clickable( substr($permalink, 0, strlen($permalink)-1).'?t='.$token), $allowed_tags );
            }else{
                echo wp_kses( make_clickable( get_permalink()), $allowed_tags );
            }
		} elseif ( 'clicks' === $column ) {
			echo esc_html( $count ? $count : 0 );
		}
	}

	/**
	 * Update message.
	 *
	 * @param  array $messages Messages.
	 *
	 * @return array           Messages.
	 */
	public function updated_message( $messages ) {

		$surl_object = get_post_type_object( 'surl' );

		$messages['surl'] = $surl_object->labels->messages;

		$permalink = get_permalink();

		if ( $permalink ) {
			foreach ( $messages['surl'] as $id => $message ) {
				$messages['surl'][ $id ] = sprintf( $message, $permalink );
			}
		}

		return $messages;

	}

	/**
	 * Add metabox.
	 */
	public function add_meta_box() {
		add_meta_box( 'surl', __( 'URL Information', 'simple-urls' ), array( $this, 'meta_box' ), 'surl', 'normal', 'high' );
	}
    
	/**
	 * Metabox.
	 */
	public function meta_box() {

		global $post;

		printf( '<input type="hidden" name="_surl_nonce" value="%s" />', esc_attr( wp_create_nonce( plugin_basename( __FILE__ ) ) ) );

		printf( '<p><label for="%s">%s</label></p>', '_surl_redirect', esc_html__( 'Redirect URI', 'simple-urls' ) );
		printf( '<p><input style="%s" type="text" name="%s" id="%s" value="%s" /></p>', 'width: 99%;', '_surl_redirect', '_surl_redirect', esc_attr( get_post_meta( $post->ID, '_surl_redirect', true ) ) );
		printf( '<p><span class="description">%s</span></p>', esc_html__( 'This is the URL that the Redirect Link you create on this page will redirect to when accessed in a web browser.', 'simple-urls' ) );

		$count = isset( $post->ID ) ? get_post_meta( $post->ID, '_surl_count', true ) : 0;
		/* translators: %d is the counter of clicks. */
		echo '<p>' . sprintf( esc_html__( 'This URL has been accessed %d times', 'simple-urls' ), esc_attr( $count ) ) . '</p>';

	}

	/**
	 * Metabox save function.
	 *
	 * @param  string  $post_id Post Id.
	 * @param  WP_Post $post   Post.
	 */
	public function meta_box_save( $post_id, $post ) {

		$key = '_surl_redirect';

		// Verify the nonce.
		// phpcs:ignore
		if ( ! isset( $_POST['_surl_nonce'] ) || ! wp_verify_nonce( $_POST['_surl_nonce'], plugin_basename( __FILE__ ) ) ) {
			return;
		}

		// Don't try to save the data under autosave, ajax, or future post.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		};

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		};

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		};

		// Is the user allowed to edit the URL?
		if ( ! current_user_can( 'edit_posts' ) || 'surl' !== $post->post_type ) {
			return;
		}

		// phpcs:ignore
		$value = isset( $_POST[ $key ] ) ? $_POST[ $key ] : '';

		if ( $value ) {
			// Save/update.
			update_post_meta( $post->ID, $key, $value );
		} else {
			// Delete if blank.
			delete_post_meta( $post->ID, $key );
		}

	}

    public function add_settings(){
        $hookname = add_submenu_page(
            'edit.php?post_type=surl',
            __( 'Misc', 'surl-simple'),
            __( 'Misc', 'surl-simple'),
            'manage_options',
            'surl-simple-misc',
            array( $this, 'setting_options_page_html')
        );

        add_action('load-'.$hookname, array($this, 'setting_options_page_html_submission'));

        remove_submenu_page('edit.php?post_type=surl', 'post-new.php?post_type=surl');
    }

    public function setting_options_page_html(){
        ?>
        <div class="wrap">
        
        <?
        if(isset($GLOBALS['_surl_post_id'])){
            ?>
            <div class="update notice">
            <p>
            Now can export the csv filling the fields with <b> From (include): <?= $GLOBALS['_surl_post_id'] ?></b> and <b>Amount: <?= $GLOBALS['_surl_amount'] ?></b>
            </p>
            </div>
            <?
        }
        ?>
        <h1>Miscellaneous</h1>
        <h2>New bunch</h2>
        <p>Create a bunch of urls to Simple Urls with random token</p>
        <form action="edit.php?post_type=surl&page=surl-simple-misc" method="post">
        <div>
          <div>
            <label><b>Amount</b></label>
          </div>
          <div>
            <input type="number" min="0" name="create_bunch[amount]" />
            <button class="button">Create bunch</button>
           </div>
        </div>
        </form>

        
        <h2>File</h2>
        <p>
        Export a csv from and the amount of urls used to redirect,
            <br/> it guarantee the quantity of links.<br/>
        <b>Note: </b>Strongly we recommend to export file after "Create bunch"
        </p>
        <form action="edit.php?post_type=surl&page=surl-simple-misc" method="post">
          <div>
        <label><b>From (include)</b></label>
            <input type="number" min="0" name="export[from]" />

            <label><b>Amount</b></label>
            <input type="number" min="0" name="export[amount]" />
            <button class="button">Export</button>
           </div>
        </div>
        </form>
        </div>
        <?
    }

    public function setting_options_page_html_submission(){
        global $wpdb;
		// Is the user allowed to edit the URL?
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

        if(isset($_POST['export'])){
            $from = isset($_POST['export']['from']) && $_POST['export']['from'] != ''? intval($_POST['export']['from']) : 0;
            $amount = isset($_POST['export']['amount']) && $_POST['export']['amount'] != '' ? intval($_POST['export']['amount']) : 0;
            $filename = $from.'-'.$amount.'-records.csv';
            header( 'Content-Type: application/csv' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '";' );
            $out = fopen('php://output', 'w');
            $sql  = $wpdb->prepare("SELECT id, meta_value FROM $wpdb->posts post INNER JOIN $wpdb->postmeta meta ON post.id = meta.post_id WHERE `post_type` = 'surl' AND meta.meta_key='_surl_token' AND `id` >= %d LIMIT %d",$from, $amount);
            $results = $wpdb->get_results($sql);
            foreach($results as $row ){
                $rows = array(get_site_url().'/go/'.$row->id.'?t='.$row->meta_value);
                fputcsv($out, $rows);
            }
            fclose($out);
            exit();
        }
        
        /**
         * CREATE urls by bunch
         **/
        else if(isset($_POST['create_bunch'])){
            $amount = isset($_POST['create_bunch']['amount']) ? $_POST['create_bunch']['amount'] : 0 ;
            $first_post_id = null;
            for($i = 0; $i < $amount; $i++){
                $my_post = array(
                    'post_status' => 'publish',
                    'post_author' => 1,
                    'post_type' => 'surl'
                );
                $post = wp_insert_post($my_post);
                wp_update_post(array(
                    'ID' => $post,
                    'post_title' => $post
                ));
                update_post_meta($post, '_surl_token', $this->rand_string(8));
                if(!isset($first_post_id)){
                    $first_post_id = $post;
                }
            }
            
            if(isset($first_post_id) && $first_post_id != 0){
                $GLOBALS['_surl_post_id'] = $first_post_id;
                $GLOBALS['_surl_amount'] = $amount;
            }
        }
    }

    /**
     * Random text from n characters
     * @param int amount of letters
     **/
    public function rand_string($n){
        $rand = '';
        for($i = 0; $i < $n; $i++){
            $v = rand(65, 90);
            if(rand(0,1) == 0){
                $v = rand(97,122);
            }
            $rand .= chr($v); 
        }
        return $rand;
    }
}
