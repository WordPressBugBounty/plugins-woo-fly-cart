<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Walker_PageDropdown_Multiple' ) ) {
    class Walker_PageDropdown_Multiple extends Walker_PageDropdown {
        function start_el( &$output, $data_object, $depth = 0, $args = [], $current_object_id = 0 ) {
            $page = $data_object;
            $pad  = str_repeat( $args['pad'] ?? '--', $depth );

            $output .= "\t<option class=\"level-$depth\" value=\"$page->ID\"";

            if ( in_array( $page->ID, (array) $args['selected'] ) ) {
                $output .= ' selected="selected"';
            }

            $output .= '>';
            $title  = apply_filters( 'list_pages', $page->post_title, $page );
            $output .= $pad . ' ' . esc_html( $title );
            $output .= "</option>\n";
        }
    }
}

if ( ! class_exists( 'WPCleverWoofc_Backend' ) ) {
    class WPCleverWoofc_Backend {
        protected static $instance = null;

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        function __construct() {
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
            add_action( 'admin_init', [ $this, 'register_settings' ] );
            add_filter( 'pre_update_option', [ $this, 'last_saved' ], 10, 2 );
            add_action( 'admin_menu', [ $this, 'admin_menu' ] );
            add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
            add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );
        }

        function enqueue_scripts( $hook ) {
            if ( apply_filters( 'woofc_ignore_backend_scripts', false, $hook ) ) {
                return null;
            }

            // Only load assets on the plugin settings page
            if ( ! strpos( $hook, 'woofc' ) ) {
                return null;
            }

            add_thickbox();
            wp_enqueue_media();
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_style( 'woofc-backend', WOOFC_URI . 'assets/css/backend.css', [], WOOFC_VERSION );
            wp_enqueue_style( 'fonticonpicker', WOOFC_URI . 'assets/fonticonpicker/css/jquery.fonticonpicker.css' );
            wp_enqueue_script( 'fonticonpicker', WOOFC_URI . 'assets/fonticonpicker/js/jquery.fonticonpicker.min.js', [ 'jquery' ] );
            wp_enqueue_style( 'woofc-fonts', WOOFC_URI . 'assets/css/fonts.css' );
            wp_enqueue_script( 'woofc-backend', WOOFC_URI . 'assets/js/backend.js', [
                    'jquery',
                    'wp-color-picker'
            ] );
        }

        function action_links( $links, $file ) {
            static $plugin;

            if ( ! isset( $plugin ) ) {
                $plugin = plugin_basename( WOOFC_FILE );
            }

            if ( $plugin === $file ) {
                $settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-woofc&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'woo-fly-cart' ) . '</a>';
                $links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-woofc&tab=premium' ) ) . '">' . esc_html__( 'Premium Version', 'woo-fly-cart' ) . '</a>';
                array_unshift( $links, $settings );
            }

            return (array) $links;
        }

        function row_meta( $links, $file ) {
            static $plugin;

            if ( ! isset( $plugin ) ) {
                $plugin = plugin_basename( WOOFC_FILE );
            }

            if ( $plugin === $file ) {
                $row_meta = [
                        'support' => '<a href="' . esc_url( WOOFC_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'woo-fly-cart' ) . '</a>',
                ];

                return array_merge( $links, $row_meta );
            }

            return (array) $links;
        }

        function register_settings() {
            // settings
            register_setting( 'woofc_settings', 'woofc_settings', [
                    'type'              => 'array',
                    'sanitize_callback' => [ 'WPCleverWoofc', 'sanitize_array' ],
            ] );

            // localization
            register_setting( 'woofc_localization', 'woofc_localization', [
                    'type'              => 'array',
                    'sanitize_callback' => [ 'WPCleverWoofc', 'sanitize_array' ],
            ] );
        }

        function last_saved( $value, $option ) {
            if ( $option === 'woofc_settings' || $option === 'woofc_localization' ) {
                $value['_last_saved']    = current_time( 'timestamp' );
                $value['_last_saved_by'] = get_current_user_id();
            }

            return $value;
        }

        function admin_menu() {
            add_submenu_page( 'wpclever', esc_html__( 'WPC Fly Cart', 'woo-fly-cart' ), esc_html__( 'Fly Cart', 'woo-fly-cart' ), 'manage_options', 'wpclever-woofc', [
                    $this,
                    'admin_menu_content'
            ] );
        }

        function admin_menu_content() {
            $active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
            ?>
            <div class="wpclever_settings_page wrap">
                <div class="wpclever_settings_page_header">
                    <a class="wpclever_settings_page_header_logo" href="https://wpclever.net/"
                       target="_blank" title="Visit wpclever.net"></a>
                    <div class="wpclever_settings_page_header_text">
                        <div class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Fly Cart', 'woo-fly-cart' ) . ' ' . esc_html( WOOFC_VERSION ) . ' ' . ( defined( 'WOOFC_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'woo-fly-cart' ) . '</span>' : '' ); ?></div>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
                                <?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'woo-fly-cart' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WOOFC_REVIEWS ); ?>"
                                   target="_blank"><?php esc_html_e( 'Reviews', 'woo-fly-cart' ); ?></a> |
                                <a href="<?php echo esc_url( WOOFC_CHANGELOG ); ?>"
                                   target="_blank"><?php esc_html_e( 'Changelog', 'woo-fly-cart' ); ?></a> |
                                <a href="<?php echo esc_url( WOOFC_DISCUSSION ); ?>"
                                   target="_blank"><?php esc_html_e( 'Discussion', 'woo-fly-cart' ); ?></a>
                            </p>
                        </div>
                    </div>
                </div>
                <h2></h2>
                <?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( 'Settings updated.', 'woo-fly-cart' ); ?></p>
                    </div>
                <?php } ?>
                <div class="wpclever_settings_page_nav">
                    <h2 class="nav-tab-wrapper">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woofc&tab=settings' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
                            <?php esc_html_e( 'Settings', 'woo-fly-cart' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woofc&tab=localization' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'localization' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
                            <?php esc_html_e( 'Localization', 'woo-fly-cart' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woofc&tab=premium' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>"
                           style="color: #c9356e">
                            <?php esc_html_e( 'Premium Version', 'woo-fly-cart' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>"
                           class="nav-tab">
                            <?php esc_html_e( 'Essential Kit', 'woo-fly-cart' ); ?>
                        </a>
                    </h2>
                </div>
                <div class="wpclever_settings_page_content">
                    <?php if ( $active_tab === 'settings' ) {
                        $default_style           = apply_filters( 'woofc_default_style', '01' );
                        $auto_show_ajax          = WPCleverWoofc::get_setting( 'auto_show_ajax', 'yes' );
                        $auto_show_normal        = WPCleverWoofc::get_setting( 'auto_show_normal', 'yes' );
                        $confetti                = WPCleverWoofc::get_setting( 'confetti', 'no' );
                        $reverse_items           = WPCleverWoofc::get_setting( 'reverse_items', 'yes' );
                        $overlay_layer           = WPCleverWoofc::get_setting( 'overlay_layer', 'yes' );
                        $perfect_scrollbar       = WPCleverWoofc::get_setting( 'perfect_scrollbar', 'yes' );
                        $position                = WPCleverWoofc::get_setting( 'position', '05' );
                        $effect                  = WPCleverWoofc::get_setting( 'effect', 'yes' );
                        $rounded                 = WPCleverWoofc::get_setting( 'rounded', 'no' );
                        $style                   = WPCleverWoofc::get_setting( 'style', $default_style );
                        $close                   = WPCleverWoofc::get_setting( 'close', 'yes' );
                        $link                    = WPCleverWoofc::get_setting( 'link', 'yes' );
                        $price                   = WPCleverWoofc::get_setting( 'price', 'price' );
                        $data                    = WPCleverWoofc::get_setting( 'data', 'no' );
                        $estimated_delivery_date = WPCleverWoofc::get_setting( 'estimated_delivery_date', 'no' );
                        $plus_minus              = WPCleverWoofc::get_setting( 'plus_minus', 'yes' );
                        $remove                  = WPCleverWoofc::get_setting( 'remove', 'yes' );
                        $save_for_later          = WPCleverWoofc::get_setting( 'save_for_later', 'yes' );
                        $subtotal                = WPCleverWoofc::get_setting( 'subtotal', 'yes' );
                        $coupon                  = WPCleverWoofc::get_setting( 'coupon', 'no' );
                        $coupon_listing          = WPCleverWoofc::get_setting( 'coupon_listing', 'no' );
                        $shipping_cost           = WPCleverWoofc::get_setting( 'shipping_cost', 'no' );
                        $shipping_calculator     = WPCleverWoofc::get_setting( 'shipping_calculator', 'no' );
                        $free_shipping_bar       = WPCleverWoofc::get_setting( 'free_shipping_bar', 'yes' );
                        $tax                     = WPCleverWoofc::get_setting( 'tax', 'no' );
                        $total                   = WPCleverWoofc::get_setting( 'total', 'yes' );
                        $buttons                 = WPCleverWoofc::get_setting( 'buttons', '01' );
                        $instant_checkout        = WPCleverWoofc::get_setting( 'instant_checkout', 'no' );
                        $instant_checkout_open   = WPCleverWoofc::get_setting( 'instant_checkout_open', 'no' );
                        $suggested               = WPCleverWoofc::normalize_suggested( WPCleverWoofc::get_setting( 'suggested', [] ) );
                        $suggested_empty         = WPCleverWoofc::get_setting( 'suggested_empty', 'no' );
                        $suggested_carousel      = WPCleverWoofc::get_setting( 'suggested_carousel', 'yes' );
                        $upsell_funnel           = WPCleverWoofc::get_setting( 'upsell_funnel', 'yes' );
                        $upsell_funnel_carousel  = WPCleverWoofc::get_setting( 'upsell_funnel_carousel', 'yes' );
                        $empty                   = WPCleverWoofc::get_setting( 'empty', 'no' );
                        $confirm_empty           = WPCleverWoofc::get_setting( 'confirm_empty', 'no' );
                        $share                   = WPCleverWoofc::get_setting( 'share', 'yes' );
                        $continue                = WPCleverWoofc::get_setting( 'continue', 'yes' );
                        $confirm_remove          = WPCleverWoofc::get_setting( 'confirm_remove', 'no' );
                        $undo_remove             = WPCleverWoofc::get_setting( 'undo_remove', 'yes' );
                        $reload                  = WPCleverWoofc::get_setting( 'reload', 'no' );
                        $hide_pages              = WPCleverWoofc::get_setting( 'hide_pages', [] );
                        $count                   = WPCleverWoofc::get_setting( 'count', 'yes' );
                        $count_position          = WPCleverWoofc::get_setting( 'count_position', 'bottom-left' );
                        $count_hide_empty        = WPCleverWoofc::get_setting( 'count_hide_empty', 'no' );
                        ?>
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <tr class="heading">
                                    <th><?php esc_html_e( 'General', 'woo-fly-cart' ); ?></th>
                                    <td><?php esc_html_e( 'General settings for the fly cart.', 'woo-fly-cart' ); ?></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Open on AJAX add to cart', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[auto_show_ajax]">
                                                <option value="yes" <?php selected( $auto_show_ajax, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $auto_show_ajax, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php printf( /* translators: link */ esc_html__( 'The fly cart will be opened immediately after whenever click to AJAX Add to cart buttons? See %1$s "Add to cart behaviour" setting %2$s', 'woo-fly-cart' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=products&section=display' ) ) . '" target="_blank">', '</a>.' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Open on normal add to cart', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[auto_show_normal]">
                                                <option value="yes" <?php selected( $auto_show_normal, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $auto_show_normal, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'The fly cart will be opened immediately after whenever click to normal Add to cart buttons (AJAX is not enable) or Add to cart button in single product page?', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Confetti effect', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[confetti]">
                                                <option value="yes" <?php selected( $confetti, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $confetti, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Add a confetti effect each time a product is added to the shopping cart.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Reverse items', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[reverse_items]">
                                                <option value="yes" <?php selected( $reverse_items, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $reverse_items, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Overlay layer', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[overlay_layer]">
                                                <option value="yes" <?php selected( $overlay_layer, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $overlay_layer, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'If you hide the overlay layer, the buyer still can work on your site when the fly cart is opening.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Use perfect-scrollbar', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[perfect_scrollbar]">
                                                <option value="yes" <?php selected( $perfect_scrollbar, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $perfect_scrollbar, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php printf( /* translators: link */ esc_html__( 'Read more about %s', 'woo-fly-cart' ), '<a href="https://github.com/mdbootstrap/perfect-scrollbar" target="_blank">perfect-scrollbar</a>' ); ?>.</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Position', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[position]">
                                                <option value="01" <?php selected( $position, '01' ); ?>><?php esc_html_e( 'Right', 'woo-fly-cart' ); ?></option>
                                                <option value="02" <?php selected( $position, '02' ); ?>><?php esc_html_e( 'Left', 'woo-fly-cart' ); ?></option>
                                                <option value="03" <?php selected( $position, '03' ); ?>><?php esc_html_e( 'Top', 'woo-fly-cart' ); ?></option>
                                                <option value="04" <?php selected( $position, '04' ); ?>><?php esc_html_e( 'Bottom', 'woo-fly-cart' ); ?></option>
                                                <option value="05" <?php selected( $position, '05' ); ?>><?php esc_html_e( 'Center', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Effect', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[effect]">
                                                <option value="yes" <?php selected( $effect, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $effect, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Enable/disable slide effect.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Style', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <?php
                                        $styles = apply_filters( 'woofc_styles', [
                                                '01' => esc_html__( 'Color background', 'woo-fly-cart' ),
                                                '02' => esc_html__( 'White background', 'woo-fly-cart' ),
                                                '03' => esc_html__( 'Color background, no thumbnail', 'woo-fly-cart' ),
                                                '04' => esc_html__( 'White background, no thumbnail', 'woo-fly-cart' ),
                                                '05' => esc_html__( 'Background image', 'woo-fly-cart' ),
                                        ] );

                                        echo '<select name="woofc_settings[style]" class="woofc_style">';

                                        foreach ( $styles as $k => $s ) {
                                            echo '<option value="' . esc_attr( $k ) . '" ' . selected( $style, $k, false ) . '>' . esc_html( $s ) . '</option>';
                                        }

                                        echo '</select>';
                                        ?>
                                    </td>
                                </tr>
                                <tr class="woofc_hide_if_style woofc_show_if_style_01 woofc_show_if_style_02 woofc_show_if_style_03 woofc_show_if_style_04">
                                    <th><?php esc_html_e( 'Color', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label for="woofc_color"></label><input type="text"
                                                                                name="woofc_settings[color]"
                                                                                id="woofc_color"
                                                                                value="<?php echo WPCleverWoofc::get_setting( 'color', '#cc6055' ); ?>"
                                                                                class="woofc_color_picker"/>
                                        <span class="description"><?php printf( /* translators: color */ esc_html__( 'Background or text color of selected style, default %s', 'woo-fly-cart' ), '<code>#cc6055</code>' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="woofc_hide_if_style woofc_show_if_style_05">
                                    <th><?php esc_html_e( 'Background image', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <div class="woofc_image_preview" id="woofc_image_preview">
                                            <?php if ( WPCleverWoofc::get_setting( 'bg_image', '' ) !== '' ) {
                                                echo '<img src="' . wp_get_attachment_url( WPCleverWoofc::get_setting( 'bg_image', '' ) ) . '"/>';
                                            } ?>
                                        </div>
                                        <input id="woofc_upload_image_button" type="button" class="button"
                                               value="<?php esc_html_e( 'Upload image', 'woo-fly-cart' ); ?>"/>
                                        <input type="hidden" name="woofc_settings[bg_image]"
                                               id="woofc_image_attachment_url"
                                               value="<?php echo WPCleverWoofc::get_setting( 'bg_image', '' ); ?>"/>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Rounded', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[rounded]">
                                                <option value="yes" <?php selected( $rounded, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $rounded, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Enable/disable rounded style for elements.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Close button', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[close]">
                                                <option value="yes" <?php selected( $close, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $close, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Show/hide the close button.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Link to individual product', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[link]">
                                                <option value="yes" <?php selected( $link, 'yes' ); ?>><?php esc_html_e( 'Yes, open in the same tab', 'woo-fly-cart' ); ?></option>
                                                <option value="yes_blank" <?php selected( $link, 'yes_blank' ); ?>><?php esc_html_e( 'Yes, open in the new tab', 'woo-fly-cart' ); ?></option>
                                                <option value="yes_popup" <?php selected( $link, 'yes_popup' ); ?>><?php esc_html_e( 'Yes, open quick view popup', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $link, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label> <span class="description">If you choose "Open quick view popup", please install <a
                                                    href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                    class="thickbox"
                                                    title="WPC Smart Quick View">WPC Smart Quick View</a> to make it work.</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Item data', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[data]">
                                                <option value="yes" <?php selected( $data, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $data, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Show/hide the item data under title.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Item price', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[price]">
                                                <option value="no" <?php selected( $price, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                                <option value="price" <?php selected( $price, 'price' ); ?>><?php esc_html_e( 'Price', 'woo-fly-cart' ); ?></option>
                                                <option value="subtotal" <?php selected( $price, 'subtotal' ); ?>><?php esc_html_e( 'Subtotal', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Show/hide the item price or subtotal under title.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Item estimated delivery date', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[estimated_delivery_date]">
                                                <option value="yes" <?php selected( $estimated_delivery_date, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $estimated_delivery_date, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Show/hide the item estimated delivery date.', 'woo-fly-cart' ); ?> Please install <a
                                                    href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-estimated-delivery-date&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                    class="thickbox" title="WPC Estimated Delivery Date">WPC Estimated Delivery Date</a> to make it work.</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Plus/minus button', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[plus_minus]">
                                                <option value="yes" <?php selected( $plus_minus, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $plus_minus, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Show/hide the plus/minus button.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Item remove', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[remove]">
                                                <option value="yes" <?php selected( $remove, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $remove, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Show/hide the remove button for each item.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Save for later', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[save_for_later]">
                                                <option value="yes" <?php selected( $save_for_later, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $save_for_later, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label> <span class="description">Show/hide the save for later button for each product. If enable this option, please install and activate <a
                                                    href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wc-save-for-later&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                    class="thickbox" title="WPC Save For Later">WPC Save For Later</a> to make it work.</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Subtotal', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[subtotal]">
                                                <option value="yes" <?php selected( $subtotal, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $subtotal, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr style="opacity: .5; pointer-events: none">
                                    <th><?php esc_html_e( 'Coupon', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[coupon]">
                                                <option value="yes" <?php selected( $coupon, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $coupon, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr style="opacity: .5; pointer-events: none">
                                    <th><?php esc_html_e( 'Coupon listing', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[coupon_listing]">
                                                <option value="yes" <?php selected( $coupon_listing, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $coupon_listing, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label> <span class="description">If enable this option, please install and activate <a
                                                    href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-coupon-listing&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                    class="thickbox" title="WPC Coupon Listing">WPC Coupon Listing</a> to make it work.</span>
                                    </td>
                                </tr>
                                <tr style="opacity: .5; pointer-events: none">
                                    <th><?php esc_html_e( 'Shipping cost', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[shipping_cost]">
                                                <option value="yes" <?php selected( $shipping_cost, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $shipping_cost, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr style="opacity: .5; pointer-events: none">
                                    <th><?php esc_html_e( 'Shipping calculator', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[shipping_calculator]">
                                                <option value="yes" <?php selected( $shipping_calculator, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $shipping_calculator, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Free shipping bar', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[free_shipping_bar]">
                                                <option value="yes" <?php selected( $free_shipping_bar, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $free_shipping_bar, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label> <span class="description">If enable this option, please install and activate <a
                                                    href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-free-shipping-bar&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                    class="thickbox"
                                                    title="WPC Free Shipping Bar">WPC Free Shipping Bar</a> to make it work.</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Tax', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[tax]">
                                                <option value="yes" <?php selected( $tax, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $tax, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label> <span
                                                class="description"><?php esc_html_e( 'It requires enabling tax and excluding tax from display prices during cart and checkout.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Total', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[total]">
                                                <option value="yes" <?php selected( $total, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $total, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Action buttons', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[buttons]">
                                                <option value="01" <?php selected( $buttons, '01' ); ?>><?php esc_html_e( 'Cart & Checkout', 'woo-fly-cart' ); ?></option>
                                                <option value="02" <?php selected( $buttons, '02' ); ?>><?php esc_html_e( 'Cart only', 'woo-fly-cart' ); ?></option>
                                                <option value="03" <?php selected( $buttons, '03' ); ?>><?php esc_html_e( 'Checkout only', 'woo-fly-cart' ); ?></option>
                                                <option value="hide" <?php selected( $buttons, 'hide' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr style="opacity: .5; pointer-events: none">
                                    <th><?php esc_html_e( 'Instant checkout', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <select name="woofc_settings[instant_checkout]"
                                                    class="woofc_instant_checkout">
                                                <option value="yes" <?php selected( $instant_checkout, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $instant_checkout, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'If enable this option, buyer can checkout directly on the fly cart.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr style="opacity: .5; pointer-events: none" class="woofc_hide_if_instant_checkout woofc_show_if_instant_checkout_yes">
                                    <th><?php esc_html_e( 'Open instant checkout immediately', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[instant_checkout_open]">
                                                <option value="yes" <?php selected( $instant_checkout_open, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $instant_checkout_open, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Open instant checkout form immediately after adding a product to the cart.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Suggested products', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <?php
                                        $suggested = WPCleverWoofc::normalize_suggested( $suggested );
                                        ?>
                                        <ul>
                                            <li>
                                                <label><input type="checkbox" name="woofc_settings[suggested][]"
                                                              value="related" <?php echo esc_attr( in_array( 'related', $suggested ) ? 'checked' : '' ); ?>/> <?php esc_html_e( 'Related', 'woo-fly-cart' ); ?>
                                                </label></li>
                                            <li>
                                                <label><input type="checkbox" name="woofc_settings[suggested][]"
                                                              value="up_sells" <?php echo esc_attr( in_array( 'up_sells', $suggested ) ? 'checked' : '' ); ?>/> <?php esc_html_e( 'Upsells', 'woo-fly-cart' ); ?>
                                                </label></li>
                                            <li>
                                                <label><input type="checkbox" name="woofc_settings[suggested][]"
                                                              value="cross_sells" <?php echo esc_attr( in_array( 'cross_sells', $suggested ) ? 'checked' : '' ); ?>/> <?php esc_html_e( 'Cross-sells', 'woo-fly-cart' ); ?>
                                                </label></li>
                                            <li>
                                                <label><input type="checkbox" name="woofc_settings[suggested][]"
                                                              value="wishlist" <?php echo esc_attr( in_array( 'wishlist', $suggested ) ? 'checked' : '' ); ?>/> <?php esc_html_e( 'Wishlist', 'woo-fly-cart' ); ?>
                                                </label> <span class="description">(from
                                                    <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-wishlist&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                       class="thickbox"
                                                       title="WPC Smart Wishlist">WPC Smart Wishlist</a>)</span>
                                            </li>
                                            <li>
                                                <label><input type="checkbox" name="woofc_settings[suggested][]"
                                                              value="compare" <?php echo esc_attr( in_array( 'compare', $suggested ) ? 'checked' : '' ); ?>/> <?php esc_html_e( 'Compare', 'woo-fly-cart' ); ?>
                                                </label> <span class="description">(from
                                                <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-compare&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                   class="thickbox"
                                                   title="WPC Smart Compare">WPC Smart Compare</a>)</span>
                                            </li>
                                        </ul>
                                        <span class="description">You can use
									<a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-custom-related-products&TB_iframe=true&width=800&height=550' ) ); ?>"
                                       class="thickbox"
                                       title="WPC Custom Related Products">WPC Custom Related Products</a> or
									<a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-smart-linked-products&TB_iframe=true&width=800&height=550' ) ); ?>"
                                       class="thickbox" title="WPC Smart Linked Products">WPC Smart Linked Products</a> plugin to configure related/upsells/cross-sells in bulk with smart conditions.</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Suggested for empty cart', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[suggested_empty]">
                                                <option value="no" <?php selected( $suggested_empty, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                                <option value="recent" <?php selected( $suggested_empty, 'recent' ); ?>><?php esc_html_e( 'Recent products', 'woo-fly-cart' ); ?></option>
                                                <option value="onsale" <?php selected( $suggested_empty, 'onsale' ); ?>><?php esc_html_e( 'On-sale products', 'woo-fly-cart' ); ?></option>
                                                <option value="featured" <?php selected( $suggested_empty, 'featured' ); ?>><?php esc_html_e( 'Featured products', 'woo-fly-cart' ); ?></option>
                                                <option value="random" <?php selected( $suggested_empty, 'random' ); ?>><?php esc_html_e( 'Random products', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Suggested products limit', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="number" min="1" step="1"
                                                   name="woofc_settings[suggested_limit]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::get_setting( 'suggested_limit', 10 ) ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Suggested products carousel', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[suggested_carousel]">
                                                <option value="yes" <?php selected( $suggested_carousel, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $suggested_carousel, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Upsell funnel products', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[upsell_funnel]">
                                                <option value="yes" <?php selected( $upsell_funnel, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $upsell_funnel, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label> <span class="description">Show upsell funnel products from <a
                                                    href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-smart-upsell-funnel&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                    class="thickbox" title="WPC Smart Upsell Funnel">WPC Smart Upsell Funnel</a>.</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Upsell funnel products carousel', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[upsell_funnel_carousel]">
                                                <option value="yes" <?php selected( $upsell_funnel_carousel, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $upsell_funnel_carousel, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Empty cart', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[empty]">
                                                <option value="yes" <?php selected( $empty, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $empty, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Show/hide the empty cart button under the product list.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Confirm empty', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[confirm_empty]">
                                                <option value="yes" <?php selected( $confirm_empty, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $confirm_empty, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Enable/disable confirm before emptying the cart.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Share cart', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[share]">
                                                <option value="yes" <?php selected( $share, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $share, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label> <span class="description">If enable this option, please install and activate <a
                                                    href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-share-cart&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                    class="thickbox" title="WPC Share Cart">WPC Share Cart</a> to make it work.</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Continue shopping', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[continue]">
                                                <option value="yes" <?php selected( $continue, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $continue, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Show/hide the continue shopping button at the end of fly cart.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Continue shopping URL', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="url" class="regular-text code"
                                                   name="woofc_settings[continue_url]"
                                                   value="<?php echo WPCleverWoofc::get_setting( 'continue_url', '' ); ?>"/>
                                        </label>
                                        <span class="description"><?php esc_html_e( 'Custom URL for "continue shopping" button. By default, only close the fly cart when clicking on this button.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Confirm remove', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[confirm_remove]">
                                                <option value="yes" <?php selected( $confirm_remove, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $confirm_remove, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Enable/disable confirm before removing a product.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Undo remove', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[undo_remove]">
                                                <option value="yes" <?php selected( $undo_remove, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $undo_remove, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Enable/disable undo after removing a product.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Reload the cart', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[reload]">
                                                <option value="yes" <?php selected( $reload, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $reload, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'The cart will be reloaded when opening the page? If you use the cache for your site, please turn on this option.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Hide on pages', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <?php
                                        $settings_data = WPCleverWoofc::get_settings();
                                        if ( isset( $settings_data['hide_cart_checkout'] ) && ( $settings_data['hide_cart_checkout'] === 'yes' ) ) {
                                            $hide_pages[] = wc_get_page_id( 'cart' );
                                            $hide_pages[] = wc_get_page_id( 'checkout' );
                                        }

                                        $args = [
                                                'echo'     => 0,
                                                'name'     => 'woofc_settings[hide_pages][]',
                                                'walker'   => new Walker_PageDropdown_Multiple(),
                                                'selected' => $hide_pages
                                        ];
                                        echo str_replace( '<select', '<select multiple="multiple"', wp_dropdown_pages( $args ) );
                                        ?>
                                        <p class="description"><?php esc_html_e( 'Hide the fly cart on these pages.', 'woo-fly-cart' ); ?></p>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th><?php esc_html_e( 'Bubble', 'woo-fly-cart' ); ?></th>
                                    <td><?php esc_html_e( 'Settings for the bubble.', 'woo-fly-cart' ); ?></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Enable', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[count]">
                                                <option value="yes" <?php selected( $count, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $count, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Position', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[count_position]">
                                                <option value="top-left" <?php selected( $count_position, 'top-left' ); ?>><?php esc_html_e( 'Top Left', 'woo-fly-cart' ); ?></option>
                                                <option value="top-right" <?php selected( $count_position, 'top-right' ); ?>><?php esc_html_e( 'Top Right', 'woo-fly-cart' ); ?></option>
                                                <option value="bottom-left" <?php selected( $count_position, 'bottom-left' ); ?>><?php esc_html_e( 'Bottom Left', 'woo-fly-cart' ); ?></option>
                                                <option value="bottom-right" <?php selected( $count_position, 'bottom-right' ); ?>><?php esc_html_e( 'Bottom Right', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Icon', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label for="woofc_count_icon"></label><select id="woofc_count_icon"
                                                                                      name="woofc_settings[count_icon]">
                                            <?php
                                            for ( $i = 1; $i <= 16; $i ++ ) {
                                                if ( WPCleverWoofc::get_setting( 'count_icon', 'woofc-icon-cart7' ) === 'woofc-icon-cart' . $i ) {
                                                    echo '<option value="woofc-icon-cart' . $i . '" selected>woofc-icon-cart' . $i . '</option>';
                                                } else {
                                                    echo '<option value="woofc-icon-cart' . $i . '">woofc-icon-cart' . $i . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Hide if empty', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label> <select name="woofc_settings[count_hide_empty]">
                                                <option value="yes" <?php selected( $count_hide_empty, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
                                                <option value="no" <?php selected( $count_hide_empty, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Hide the bubble if the cart is empty?', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th><?php esc_html_e( 'Menu', 'woo-fly-cart' ); ?></th>
                                    <td><?php esc_html_e( 'Settings for cart menu item.', 'woo-fly-cart' ); ?></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Menu(s)', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <?php
                                        $nav_menus = get_terms( [
                                                'taxonomy'   => 'nav_menu',
                                                'hide_empty' => false,
                                                'fields'     => 'id=>name',
                                        ] );

                                        if ( $nav_menus ) {
                                            echo '<ul>';
                                            $saved_menus = WPCleverWoofc::get_setting( 'menus', [] );

                                            foreach ( $nav_menus as $nav_id => $nav_name ) {
                                                echo '<li><label><input type="checkbox" name="woofc_settings[menus][]" value="' . esc_attr( $nav_id ) . '" ' . ( is_array( $saved_menus ) && in_array( $nav_id, $saved_menus ) ? 'checked' : '' ) . '/> ' . esc_html( $nav_name ) . '</label></li>';
                                            }

                                            echo '</ul>';
                                        } else {
                                            echo '<p>' . esc_html__( 'Haven\'t any menu yet. Please go to Appearance > Menus to create one.', 'woo-fly-cart' ) . '</p>';
                                        }
                                        ?>
                                        <span class="description"><?php esc_html_e( 'Choose the menu(s) you want to add the cart at the end.', 'woo-fly-cart' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Custom menu', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_settings[manual_show]"
                                                   value="<?php echo WPCleverWoofc::get_setting( 'manual_show', '' ); ?>"
                                                   placeholder="<?php esc_attr_e( 'button class or id', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                        <span class="description"><?php printf( /* translators: selector */ esc_html__( 'The class or id of the custom menu. When clicking on it, the fly cart will show up. Example %1$s or %2$s', 'woo-fly-cart' ), '<code>.fly-cart-btn</code>', '<code>#fly-cart-btn</code>' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="submit">
                                    <th colspan="2">
                                        <div class="wpclever_submit">
                                            <?php
                                            settings_fields( 'woofc_settings' );
                                            submit_button( '', 'primary', 'submit', false );

                                            if ( function_exists( 'wpc_last_saved' ) ) {
                                                wpc_last_saved( WPCleverWoofc::get_settings() );
                                            }
                                            ?>
                                        </div>
                                        <a style="display: none;" class="wpclever_export"
                                           data-key="woofc_settings"
                                           data-name="settings"
                                           href="#"><?php esc_html_e( 'import / export', 'woo-fly-cart' ); ?></a>
                                    </th>
                                </tr>
                            </table>
                        </form>
                    <?php } elseif ( $active_tab === 'localization' ) { ?>
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <tr class="heading">
                                    <th scope="row"><?php esc_html_e( 'Localization', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'woo-fly-cart' ); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Cart heading', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[heading]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'heading' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Shopping cart', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Close', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[close]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'close' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Close', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Remove', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[remove]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'remove' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Remove', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Confirm remove', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[remove_confirm]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'remove_confirm' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Do you want to remove this item?', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Undo remove', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[remove_undo]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'remove_undo' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Undo?', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Removed', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[removed]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'removed' ) ); ?>"
                                                   placeholder="<?php /* translators: product */
                                                   esc_attr_e( '%s was removed.', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Empty cart', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[empty]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'empty' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Empty cart', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Confirm empty', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[empty_confirm]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'empty_confirm' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Do you want to empty the cart?', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Share cart', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[share]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'share' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Share cart', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Subtotal', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[subtotal]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'subtotal' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Subtotal', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Coupon code', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[coupon_code]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'coupon_code' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Coupon code', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Coupon apply', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[coupon_apply]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'coupon_apply' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Apply', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Shipping', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[shipping]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'shipping' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Shipping', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Total', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[total]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'total' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Total', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Cart', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[cart]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'cart' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Cart', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Checkout', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[checkout]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'checkout' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Checkout', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Continue shopping', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[continue]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'continue' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Continue shopping', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Suggested products', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[suggested]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'suggested' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'You may be interested in&hellip;', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'There are no products', 'woo-fly-cart' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woofc_localization[no_products]"
                                                   value="<?php echo esc_attr( WPCleverWoofc::localization( 'no_products' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'There are no products in the cart!', 'woo-fly-cart' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="submit">
                                    <th colspan="2">
                                        <?php settings_fields( 'woofc_localization' ); ?><?php submit_button(); ?>
                                        <a style="display: none;" class="wpclever_export"
                                           data-key="woofc_localization"
                                           data-name="settings"
                                           href="#"><?php esc_html_e( 'import / export', 'woo-fly-cart' ); ?></a>
                                    </th>
                                </tr>
                            </table>
                        </form>
                    <?php } elseif ( $active_tab === 'premium' ) { ?>
                        <div class="wpclever_settings_page_content_text">
                            <p>Get the Premium Version just $29!
                                <a href="https://wpclever.net/downloads/fly-cart?utm_source=pro&utm_medium=woofc&utm_campaign=wporg"
                                   target="_blank">https://wpclever.net/downloads/fly-cart</a>
                            </p>
                            <p><strong>Extra features for Premium Version:</strong></p>
                            <ul style="margin-bottom: 0">
                                <li>- Enable coupon form.</li>
                                <li>- Enable shipping cost and shipping calculator.</li>
                                <li>- Enable instant checkout.</li>
                                <li>- Get lifetime update & premium support.</li>
                            </ul>
                        </div>
                    <?php } ?>
                </div><!-- /.wpclever_settings_page_content -->
                <div class="wpclever_settings_page_suggestion">
                    <div class="wpclever_settings_page_suggestion_label">
                        <span class="dashicons dashicons-yes-alt"></span> Suggestion
                    </div>
                    <div class="wpclever_settings_page_suggestion_content">
                        <div>
                            To display custom engaging real-time messages on any wished positions, please
                            install
                            <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC
                                Smart Messages</a> plugin. It's free!
                        </div>
                        <div>
                            Wanna save your precious time working on variations? Try our brand-new free plugin
                            <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC
                                Variation Bulk Editor</a> and
                            <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC
                                Variation Duplicator</a>.
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

    }
}
