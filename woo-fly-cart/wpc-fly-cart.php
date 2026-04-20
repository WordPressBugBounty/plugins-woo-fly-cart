<?php
/*
Plugin Name: WPC Fly Cart for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Fly Cart is an interactive mini cart for WooCommerce. It allows users to update product quantities or remove products without reloading the page.
Version: 6.1.0
Author: WPClever
Author URI: https://wpclever.net
Text Domain: woo-fly-cart
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Tested up to: 6.9
WC requires at least: 3.0
WC tested up to: 10.7
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WOOFC_VERSION' ) && define( 'WOOFC_VERSION', '6.1.0' );
! defined( 'WOOFC_LITE' ) && define( 'WOOFC_LITE', __FILE__ );
! defined( 'WOOFC_FILE' ) && define( 'WOOFC_FILE', __FILE__ );
! defined( 'WOOFC_URI' ) && define( 'WOOFC_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WOOFC_DIR' ) && define( 'WOOFC_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WOOFC_SUPPORT' ) && define( 'WOOFC_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=woofc&utm_campaign=wporg' );
! defined( 'WOOFC_REVIEWS' ) && define( 'WOOFC_REVIEWS', 'https://wordpress.org/support/plugin/woo-fly-cart/reviews/' );
! defined( 'WOOFC_CHANGELOG' ) && define( 'WOOFC_CHANGELOG', 'https://wordpress.org/plugins/woo-fly-cart/#developers' );
! defined( 'WOOFC_DISCUSSION' ) && define( 'WOOFC_DISCUSSION', 'https://wordpress.org/support/plugin/woo-fly-cart' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WOOFC_URI );

require_once 'includes/log/wpc-log.php';
require_once 'includes/dashboard/wpc-dashboard.php';
require_once 'includes/kit/wpc-kit.php';
require_once 'includes/hpos.php';

require_once 'includes/class-backend.php';

if ( ! function_exists( 'woofc_init' ) ) {
    add_action( 'plugins_loaded', 'woofc_init', 11 );

    function woofc_init() {
        if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
            add_action( 'admin_notices', 'woofc_notice_wc' );

            return null;
        }

        if ( ! class_exists( 'WPCleverWoofc' ) && class_exists( 'WC_Product' ) ) {
            class WPCleverWoofc {
                protected static $settings = [];
                protected static $localization = [];
                protected static $instance = null;

                public static function instance() {
                    if ( is_null( self::$instance ) ) {
                        self::$instance = new self();
                    }

                    return self::$instance;
                }

                function __construct() {
                    self::$settings     = (array) get_option( 'woofc_settings', [] );
                    self::$localization = (array) get_option( 'woofc_localization', [] );

                    add_action( 'init', [ $this, 'init' ] );
                    add_action( 'wp_footer', [ $this, 'footer' ] );
                    add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
                    add_filter( 'wp_nav_menu_items', [ $this, 'nav_menu_items' ], 99, 2 );
                    add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'cart_fragment' ] );
                    add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'cart_fragment' ] );

                    // shortcode
                    add_shortcode( 'woofc_link', [ $this, 'shortcode_cart_link' ] );
                    add_shortcode( 'woofc_cart_link', [ $this, 'shortcode_cart_link' ] );

                    // ajax
                    add_action( 'wc_ajax_woofc_update_qty', [ $this, 'ajax_update_qty' ] );
                    add_action( 'wc_ajax_woofc_remove_item', [ $this, 'ajax_remove_item' ] );
                    add_action( 'wc_ajax_woofc_undo_remove', [ $this, 'ajax_undo_remove' ] );
                    add_action( 'wc_ajax_woofc_empty_cart', [ $this, 'ajax_empty_cart' ] );

                    // nonce check
                    add_filter( 'woofc_disable_nonce_check', function ( $check, $context ) {
                        return apply_filters( 'woofc_disable_security_check', $check, $context );
                    }, 10, 2 );

                    // wpcsm integration
                    add_filter( 'wpcsm_locations', [ $this, 'wpcsm_locations' ] );

                    // boot backend
                    if ( is_admin() ) {
                        WPCleverWoofc_Backend::instance();
                    }
                }

                function init() {
                    // load text-domain
                    load_plugin_textdomain( 'woo-fly-cart', false, basename( WOOFC_DIR ) . '/languages/' );
                }

                public static function get_settings() {
                    return apply_filters( 'woofc_get_settings', self::$settings );
                }

                public static function get_setting( $name, $default = false ) {
                    if ( ! empty( self::$settings ) ) {
                        if ( isset( self::$settings[ $name ] ) ) {
                            $setting = self::$settings[ $name ];
                        } else {
                            $setting = $default;
                        }
                    } else {
                        $setting = get_option( 'woofc_' . $name, $default );
                    }

                    return apply_filters( 'woofc_get_setting', $setting, $name, $default );
                }

                public static function localization( $key = '', $default = '' ) {
                    $str = '';

                    if ( ! empty( $key ) && ! empty( self::$localization[ $key ] ) ) {
                        $str = self::$localization[ $key ];
                    } elseif ( ! empty( $default ) ) {
                        $str = $default;
                    }

                    return apply_filters( 'woofc_localization_' . $key, $str );
                }

                function enqueue_scripts() {
                    if ( self::disable() ) {
                        return null;
                    }

                    // hint css
                    wp_enqueue_style( 'hint', WOOFC_URI . 'assets/hint/hint.min.css' );

                    // perfect scrollbar
                    if ( apply_filters( 'woofc_perfect_scrollbar', self::get_setting( 'perfect_scrollbar', 'yes' ) ) === 'yes' ) {
                        wp_enqueue_style( 'perfect-scrollbar', WOOFC_URI . 'assets/perfect-scrollbar/css/perfect-scrollbar.min.css' );
                        wp_enqueue_style( 'perfect-scrollbar-wpc', WOOFC_URI . 'assets/perfect-scrollbar/css/custom-theme.css' );
                        wp_enqueue_script( 'perfect-scrollbar', WOOFC_URI . 'assets/perfect-scrollbar/js/perfect-scrollbar.jquery.min.js', [ 'jquery' ], WOOFC_VERSION, true );
                    }

                    // slick
                    if ( ( ( apply_filters( 'woofc_slick', self::get_setting( 'suggested_carousel', 'yes' ), 'suggested' ) === 'yes' ) && ! empty( self::get_setting( 'suggested', [] ) ) ) || ( ( self::get_setting( 'upsell_funnel', 'yes' ) === 'yes' ) && class_exists( 'Wpcuf' ) && ( self::get_setting( 'upsell_funnel_carousel', 'yes' ) === 'yes' ) ) ) {
                        wp_enqueue_style( 'slick', WOOFC_URI . 'assets/slick/slick.css' );
                        wp_enqueue_script( 'slick', WOOFC_URI . 'assets/slick/slick.min.js', [ 'jquery' ], WOOFC_VERSION, true );
                    }

                    // canvas-confetti
                    if ( apply_filters( 'woofc_confetti', self::get_setting( 'confetti', 'no' ) ) === 'yes' ) {
                        wp_enqueue_script( 'canvas-confetti', WOOFC_URI . 'assets/canvas-confetti/confetti.browser.min.js', [ 'jquery' ], WOOFC_VERSION, true );
                    }

                    // main
                    if ( ! apply_filters( 'woofc_disable_font_icon', false ) ) {
                        wp_enqueue_style( 'woofc-fonts', WOOFC_URI . 'assets/css/fonts.css' );
                    }

                    // css
                    wp_enqueue_style( 'woofc-frontend', WOOFC_URI . 'assets/css/frontend.css', [], WOOFC_VERSION );
                    $color      = sanitize_hex_color( self::get_setting( 'color', '#cc6055' ) ) ?: '#cc6055';
                    $bg_image   = self::get_setting( 'bg_image', '' ) !== '' ? esc_url( wp_get_attachment_url( self::get_setting( 'bg_image', '' ) ) ) : '';
                    $inline_css = ".woofc-area.woofc-style-01 .woofc-inner, .woofc-area.woofc-style-03 .woofc-inner, .woofc-area.woofc-style-02 .woofc-area-bot .woofc-action .woofc-action-inner > div a:hover, .woofc-area.woofc-style-04 .woofc-area-bot .woofc-action .woofc-action-inner > div a:hover {
                            background-color: {$color};
                        }

                        .woofc-area.woofc-style-01 .woofc-area-bot .woofc-action .woofc-action-inner > div a, .woofc-area.woofc-style-02 .woofc-area-bot .woofc-action .woofc-action-inner > div a, .woofc-area.woofc-style-03 .woofc-area-bot .woofc-action .woofc-action-inner > div a, .woofc-area.woofc-style-04 .woofc-area-bot .woofc-action .woofc-action-inner > div a {
                            outline: none;
                            color: {$color};
                        }

                        .woofc-area.woofc-style-02 .woofc-area-bot .woofc-action .woofc-action-inner > div a, .woofc-area.woofc-style-04 .woofc-area-bot .woofc-action .woofc-action-inner > div a {
                            border-color: {$color};
                        }

                        .woofc-area.woofc-style-05 .woofc-inner{
                            background-color: {$color};
                            background-image: url('{$bg_image}');
                            background-size: cover;
                            background-position: center;
                            background-repeat: no-repeat;
                        }
                        
                        .woofc-count span {
                            background-color: {$color};
                        }";
                    wp_add_inline_style( 'woofc-frontend', $inline_css );

                    $show_cart = 'no';
                    $requests  = apply_filters( 'woofc_auto_show_requests', [
                            'add-to-cart',
                            'product_added_to_cart',
                            'added_to_cart',
                            'set_cart',
                            'fill_cart'
                    ] );

                    if ( is_array( $requests ) && ! empty( $requests ) ) {
                        foreach ( $requests as $request ) {
                            if ( isset( $_REQUEST[ $request ] ) ) {
                                $show_cart = 'yes';
                                break;
                            }
                        }
                    }

                    $show_checkout     = 'no';
                    $checkout_requests = apply_filters( 'woofc_auto_show_checkout_requests', [] );

                    if ( is_array( $checkout_requests ) && ! empty( $checkout_requests ) ) {
                        foreach ( $checkout_requests as $checkout_request ) {
                            if ( isset( $_REQUEST[ $checkout_request ] ) ) {
                                $show_checkout = 'yes';
                                break;
                            }
                        }
                    }

                    // js
                    wp_enqueue_script( 'woofc-frontend', WOOFC_URI . 'assets/js/frontend.js', [
                            'jquery',
                            'wc-cart-fragments'
                    ], WOOFC_VERSION, true );
                    wp_localize_script( 'woofc-frontend', 'woofc_vars', apply_filters( 'woofc_vars', [
                                    'wc_ajax_url'             => WC_AJAX::get_endpoint( '%%endpoint%%' ),
                                    'nonce'                   => wp_create_nonce( 'woofc-security' ),
                                    'scrollbar'               => self::get_setting( 'perfect_scrollbar', 'yes' ),
                                    'auto_show'               => self::get_setting( 'auto_show_ajax', 'yes' ),
                                    'auto_show_normal'        => self::get_setting( 'auto_show_normal', 'yes' ),
                                    'confetti'                => self::get_setting( 'confetti', 'no' ) === 'yes',
                                    'show_cart'               => esc_attr( $show_cart ),
                                    'show_checkout'           => esc_attr( $show_checkout ),
                                    'delay'                   => (int) apply_filters( 'woofc_delay', 300 ),
                                    'undo_remove'             => self::get_setting( 'undo_remove', 'yes' ),
                                    'confirm_remove'          => self::get_setting( 'confirm_remove', 'no' ),
                                    'instant_checkout'        => self::get_setting( 'instant_checkout', 'no' ),
                                    'instant_checkout_open'   => self::get_setting( 'instant_checkout_open', 'no' ),
                                    'confirm_empty'           => self::get_setting( 'confirm_empty', 'no' ),
                                    'confirm_empty_text'      => self::localization( 'empty_confirm', esc_html__( 'Do you want to empty the cart?', 'woo-fly-cart' ) ),
                                    'confirm_remove_text'     => self::localization( 'remove_confirm', esc_html__( 'Do you want to remove this item?', 'woo-fly-cart' ) ),
                                    'undo_remove_text'        => self::localization( 'remove_undo', esc_html__( 'Undo?', 'woo-fly-cart' ) ),
                                    'removed_text'            => self::localization( 'removed', /* translators: product */ esc_html__( '%s was removed.', 'woo-fly-cart' ) ),
                                    'manual_show'             => self::get_setting( 'manual_show', '' ),
                                    'reload'                  => self::get_setting( 'reload', 'no' ),
                                    'suggested_carousel'      => apply_filters( 'woofc_slick', self::get_setting( 'suggested_carousel', 'yes' ), 'suggested' ) === 'yes',
                                    'save_for_later_carousel' => apply_filters( 'woofc_slick', self::get_setting( 'save_for_later_carousel', 'yes' ), 'save_for_later' ) === 'yes',
                                    'upsell_funnel_carousel'  => self::get_setting( 'upsell_funnel_carousel', 'yes' ) === 'yes',
                                    'slick_params'            => apply_filters( 'woofc_slick_params', json_encode( apply_filters( 'woofc_slick_params_arr', [
                                            'slidesToShow'   => 1,
                                            'slidesToScroll' => 1,
                                            'dots'           => true,
                                            'arrows'         => false,
                                            'autoplay'       => false,
                                            'autoplaySpeed'  => 3000,
                                            'rtl'            => is_rtl()
                                    ] ) ) ),
                                    'confetti_params'         => apply_filters( 'woofc_confetti_params', json_encode( apply_filters( 'woofc_confetti_params_arr', [
                                            'particleCount' => 100,
                                            'spread'        => 70,
                                            'origin'        => [
                                                    'y' => 0.6
                                            ]
                                    ] ) ) ),
                                    'is_cart'                 => is_cart(),
                                    'is_checkout'             => is_checkout(),
                                    'cart_url'                => self::disable() ? wc_get_cart_url() : '',
                                    'hide_count_empty'        => self::get_setting( 'count_hide_empty', 'no' ),
                                    'wc_checkout_js'          => defined( 'WC_PLUGIN_FILE' ) ? plugins_url( 'assets/js/frontend/checkout.js', WC_PLUGIN_FILE ) : '',
                            ] )
                    );
                }

                function ajax_update_qty() {
                    if ( ! apply_filters( 'woofc_disable_nonce_check', false, 'update_qty' ) ) {
                        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'woofc-security' ) ) {
                            wp_die( 'Permissions check failed!' );
                        }
                    }

                    if ( isset( $_POST['cart_item_qty'] ) && ! empty( $_POST['cart_item_key'] ) ) {
                        if ( $cart_item = WC()->cart->get_cart_item( sanitize_text_field( $_POST['cart_item_key'] ) ) ) {
                            $qty = (float) sanitize_text_field( $_POST['cart_item_qty'] );

                            if ( ( $max_purchase = $cart_item['data']->get_max_purchase_quantity() ) && ( $max_purchase > 0 ) && ( $qty > $max_purchase ) ) {
                                $qty = $max_purchase;
                            }

                            if ( $qty > 0 ) {
                                WC()->cart->set_quantity( sanitize_text_field( $_POST['cart_item_key'] ), $qty );
                            } else {
                                WC()->cart->remove_cart_item( sanitize_text_field( $_POST['cart_item_key'] ) );
                            }
                        }

                        wp_send_json( [ 'action' => 'update_qty' ] );
                    }

                    wp_die();
                }

                function ajax_remove_item() {
                    if ( ! apply_filters( 'woofc_disable_nonce_check', false, 'remove_item' ) ) {
                        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'woofc-security' ) ) {
                            wp_die( 'Permissions check failed!' );
                        }
                    }

                    if ( isset( $_POST['cart_item_key'] ) ) {
                        WC()->cart->remove_cart_item( sanitize_text_field( $_POST['cart_item_key'] ) );
                        WC_AJAX::get_refreshed_fragments();
                    }

                    wp_die();
                }

                function ajax_undo_remove() {
                    if ( ! apply_filters( 'woofc_disable_nonce_check', false, 'undo_remove' ) ) {
                        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'woofc-security' ) ) {
                            wp_die( 'Permissions check failed!' );
                        }
                    }

                    if ( isset( $_POST['item_key'] ) ) {
                        if ( WC()->cart->restore_cart_item( sanitize_text_field( $_POST['item_key'] ) ) ) {
                            echo 'true';
                        } else {
                            echo 'false';
                        }
                    }

                    wp_die();
                }

                function ajax_empty_cart() {
                    if ( ! apply_filters( 'woofc_disable_nonce_check', false, 'empty_cart' ) ) {
                        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'woofc-security' ) ) {
                            wp_die( 'Permissions check failed!' );
                        }
                    }

                    WC()->cart->empty_cart();
                    WC_AJAX::get_refreshed_fragments();

                    wp_die();
                }

                function get_cart_area() {
                    if ( ! isset( WC()->cart ) ) {
                        return '';
                    }

                    // settings
                    $link               = self::get_setting( 'link', 'yes' );
                    $plus_minus         = self::get_setting( 'plus_minus', 'yes' ) === 'yes';
                    $remove             = self::get_setting( 'remove', 'yes' ) === 'yes';
                    $suggested          = self::normalize_suggested( self::get_setting( 'suggested', [] ) );
                    $suggested_products = $cart_products = [];

                    if ( in_array( 'wishlist', $suggested ) && class_exists( 'WPCleverWoosw' ) ) {
                        $suggested_products = array_merge( $suggested_products, array_keys( WPCleverWoosw::get_products() ) );
                    }

                    if ( in_array( 'compare', $suggested ) && class_exists( 'WPCleverWoosc' ) ) {
                        if ( method_exists( 'WPCleverWoosc', 'get_products' ) ) {
                            // from woosc 6.1.4
                            $compare_products   = WPCleverWoosc::get_products();
                            $suggested_products = array_merge( $suggested_products, $compare_products );
                        } else {
                            $cookie = 'woosc_products_' . md5( 'woosc' . get_current_user_id() );

                            if ( ! empty( $_COOKIE[ $cookie ] ) ) {
                                $compare_products   = explode( ',', sanitize_text_field( $_COOKIE[ $cookie ] ) );
                                $suggested_products = array_merge( $suggested_products, $compare_products );
                            }
                        }
                    }

                    ob_start();

                    // global product
                    global $product;
                    $global_product = $product;

                    echo '<div class="woofc-inner woofc-cart-area" data-nonce="' . esc_attr( wp_create_nonce( 'woofc-security' ) ) . '">';

                    do_action( 'woofc_above_area' );
                    echo apply_filters( 'woofc_above_area_content', '' );

                    echo '<div class="woofc-area-top"><span class="woofc-area-heading">' . self::localization( 'heading', esc_html__( 'Shopping cart', 'woo-fly-cart' ) ) . '<span class="woofc-area-count">' . WC()->cart->get_cart_contents_count() . '</span></span>';

                    if ( self::get_setting( 'close', 'yes' ) === 'yes' ) {
                        echo '<div class="woofc-close hint--left" aria-label="' . esc_attr( self::localization( 'close', esc_html__( 'Close', 'woo-fly-cart' ) ) ) . '"><i class="woofc-icon-icon10"></i></div>';
                    }

                    echo '</div><!-- woofc-area-top -->';
                    echo '<div class="woofc-area-mid woofc-items">';

                    do_action( 'woofc_above_items' );
                    echo apply_filters( 'woofc_above_items_content', '' );

                    // notices
                    if ( apply_filters( 'woofc_show_notices', true ) ) {
                        $notices = wc_print_notices( true );

                        if ( ! empty( $notices ) ) {
                            echo '<div class="woofc-notices">' . $notices . '</div>';
                        }
                    }

                    $items = WC()->cart->get_cart();

                    if ( is_array( $items ) && ( count( $items ) > 0 ) ) {
                        if ( apply_filters( 'woofc_cart_items_reverse', self::get_setting( 'reverse_items', 'yes' ) === 'yes' ) ) {
                            $items = array_reverse( $items );
                        }

                        foreach ( $items as $cart_item_key => $cart_item ) {
                            if ( ! isset( $cart_item['bundled_by'] ) && apply_filters( 'woocommerce_widget_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
                                $product      = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                                $product_id   = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
                                $product_link = apply_filters( 'woocommerce_cart_item_permalink', $product->is_visible() ? $product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
                                $item_class   = $remove ? 'woofc-item woofc-item-has-remove' : 'woofc-item woofc-item-has-not-remove';

                                // add suggested products
                                if ( is_array( $suggested ) && ! empty( $suggested ) ) {
                                    $cart_products[] = $product_id;

                                    if ( in_array( 'related', $suggested ) ) {
                                        $suggested_products = array_merge( $suggested_products, wc_get_related_products( $product_id ) );
                                    }

                                    if ( in_array( 'cross_sells', $suggested ) ) {
                                        $suggested_products = array_merge( $suggested_products, $product->get_cross_sell_ids() );
                                    }

                                    if ( in_array( 'up_sells', $suggested ) ) {
                                        $suggested_products = array_merge( $suggested_products, $product->get_upsell_ids() );
                                    }
                                }

                                echo '<div class="' . esc_attr( apply_filters( 'woocommerce_cart_item_class', $item_class, $cart_item, $cart_item_key ) ) . '" data-key="' . esc_attr( $cart_item_key ) . '" data-name="' . esc_attr( $product->get_name() ) . '">';

                                do_action( 'woofc_above_item', $cart_item );
                                echo apply_filters( 'woofc_above_item_inner', '', $cart_item );

                                echo '<div class="woofc-item-inner">';
                                echo '<div class="woofc-item-thumb">';

                                if ( ( $link !== 'no' ) && ! empty( $product_link ) ) {
                                    $cart_item_thumbnail = sprintf( '<a ' . ( $link === 'yes_popup' ? 'class="woosq-link" data-id="' . esc_attr( $product_id ) . '" data-context="woofc"' : '' ) . ' href="%s" ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ) . '>%s</a>', esc_url( $product_link ), $product->get_image() );
                                } else {
                                    $cart_item_thumbnail = $product->get_image();
                                }

                                echo apply_filters( 'woocommerce_cart_item_thumbnail', $cart_item_thumbnail, $cart_item, $cart_item_key );
                                echo '</div><!-- /.woofc-item-thumb -->';

                                echo '<div class="woofc-item-info">';

                                do_action( 'woofc_above_item_info', $product, $cart_item );

                                do_action( 'woofc_above_item_name', $product, $cart_item );

                                echo '<span class="woofc-item-title">';

                                if ( ( $link !== 'no' ) && ! empty( $product_link ) ) {
                                    $cart_item_name = sprintf( '<a ' . ( $link === 'yes_popup' ? 'class="woosq-link" data-id="' . esc_attr( $product_id ) . '" data-context="woofc"' : '' ) . ' href="%s" ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ) . '>%s</a>', esc_url( $product_link ), $product->get_name() );
                                } else {
                                    $cart_item_name = $product->get_name();
                                }

                                echo apply_filters( 'woocommerce_cart_item_name', $cart_item_name, $cart_item, $cart_item_key );
                                echo '</span><!-- /.woofc-item-title -->';

                                do_action( 'woofc_below_item_name', $product, $cart_item );

                                if ( self::get_setting( 'data', 'no' ) === 'yes' ) {
                                    echo apply_filters( 'woofc_cart_item_data', '<span class="woofc-item-data">' . wc_get_formatted_cart_item_data( $cart_item, apply_filters( 'woofc_cart_item_data_flat', true ) ) . '</span>', $cart_item );
                                }

                                if ( self::get_setting( 'price', 'price' ) === 'price' ) {
                                    echo '<span class="woofc-item-price">' . apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $product ), $cart_item, $cart_item_key ) . '</span>';
                                } elseif ( self::get_setting( 'price', 'price' ) === 'subtotal' ) {
                                    echo '<span class="woofc-item-price">' . apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] ), $cart_item, $cart_item_key ) . '</span>';
                                }

                                if ( ( self::get_setting( 'estimated_delivery_date', 'no' ) === 'yes' ) && class_exists( 'WPCleverWpced' ) ) {
                                    echo apply_filters( 'woofc_cart_item_estimated_delivery_date', '<span class="woofc-item-estimated-delivery-date">' . do_shortcode( '[wpced]' ) . '</span>', $cart_item );
                                }

                                if ( ( self::get_setting( 'save_for_later', 'yes' ) === 'yes' ) && class_exists( 'WPCleverWoosl' ) ) {
                                    if ( $cart_item['data']->is_type( 'variation' ) && is_array( $cart_item['variation'] ) ) {
                                        $variation = htmlspecialchars( json_encode( $cart_item['variation'] ), ENT_QUOTES, 'UTF-8' );
                                    } else {
                                        $variation = '';
                                    }

                                    echo '<span class="woofc-item-save">' . do_shortcode( '[woosl_btn product_id="' . $cart_item['product_id'] . '" variation_id="' . $cart_item['variation_id'] . '" price="' . $cart_item['data']->get_price() . '" variation="' . $variation . '" cart_item_key="' . $cart_item_key . '" context="woofc"]' ) . '</span>';
                                }

                                do_action( 'woofc_below_item_info', $product, $cart_item );

                                echo '</div><!-- /.woofc-item-info -->';

                                $min_value = apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product );
                                $max_value = apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product );

                                if ( $product->is_sold_individually() || ( $max_value && $min_value === $max_value ) || ! empty( $cart_item['woosb_parent_id'] ) || ! empty( $cart_item['wooco_parent_id'] ) || ! empty( $cart_item['woofs_parent_id'] ) ) {
                                    $cart_item_quantity = $cart_item['quantity'];
                                } else {
                                    $cart_item_qty            = isset( $cart_item['quantity'] ) ? wc_stock_amount( wp_unslash( $cart_item['quantity'] ) ) : $product->get_min_purchase_quantity();
                                    $cart_item_quantity_input = woocommerce_quantity_input( [
                                            'classes'     => [ 'input-text', 'woofc-qty', 'qty', 'text' ],
                                            'input_name'  => 'woofc_qty_' . $cart_item_key,
                                            'input_value' => $cart_item_qty,
                                            'min_value'   => $min_value,
                                            'max_value'   => $max_value,
                                            'woofc_qty'   => [
                                                    'input_value' => $cart_item_qty,
                                                    'min_value'   => $min_value,
                                                    'max_value'   => $max_value
                                            ]
                                    ], $product, false );

                                    if ( $plus_minus ) {
                                        $cart_item_quantity = '<span class="woofc-item-qty-minus">-</span>' . $cart_item_quantity_input . '<span class="woofc-item-qty-plus">+</span>';
                                    } else {
                                        $cart_item_quantity = $cart_item_quantity_input;
                                    }
                                }

                                echo '<div class="woofc-item-qty ' . ( $plus_minus ? 'woofc-item-qty-plus-minus' : '' ) . '"><div class="woofc-item-qty-inner">' . apply_filters( 'woocommerce_cart_item_quantity', $cart_item_quantity, $cart_item_key, $cart_item ) . '</div></div><!-- /.woofc-item-qty -->';

                                // always keep .woofc-item-remove to compatible with themes -  can hide it by CSS
                                echo apply_filters( 'woocommerce_cart_item_remove_link', '<span class="woofc-item-remove" aria-label="' . esc_attr( sprintf( /* translators: product */ esc_html__( 'Remove %s from cart', 'woo-fly-cart' ), wp_strip_all_tags( $product->get_name() ) ) ) . '" data-product_id="' . esc_attr( $product_id ) . '" data-product_sku="' . esc_attr( $product->get_sku() ) . '"><span class="hint--left" aria-label="' . esc_attr( self::localization( 'remove', esc_html__( 'Remove', 'woo-fly-cart' ) ) ) . '"><i class="woofc-icon-icon10"></i></span></span>', $cart_item_key );

                                echo '</div><!-- /.woofc-item-inner -->';

                                do_action( 'woofc_below_item', $cart_item );
                                echo apply_filters( 'woofc_below_item_inner', '', $cart_item );

                                echo '</div><!-- /.woofc-item -->';
                            }
                        }
                    } else {
                        echo '<div class="woofc-no-item">' . wp_kses_post( apply_filters( 'woofc_empty_message', self::localization( 'no_products', esc_html__( 'There are no products in the cart!', 'woo-fly-cart' ) ) ) ) . '</div>';

                        if ( ( self::get_setting( 'save_for_later', 'yes' ) === 'yes' ) && class_exists( 'WPCleverWoosl' ) ) {
                            echo '<div class="woofc-save-for-later">' . do_shortcode( '[woosl_list context="woofc"]' ) . '</div>';
                        }

                        $suggested_empty = self::get_setting( 'suggested_empty', 'no' );

                        if ( $suggested_empty !== 'no' ) {
                            $suggested_empty_args = [
                                    'status' => 'publish',
                                    'limit'  => (int) self::get_setting( 'suggested_limit', 10 ),
                                    'return' => 'ids',
                            ];

                            switch ( $suggested_empty ) {
                                case 'recent':
                                    $suggested_empty_args['orderby'] = 'ID';
                                    $suggested_empty_args['order']   = 'DESC';
                                    break;
                                case 'onsale':
                                    $suggested_empty_args['include'] = wc_get_product_ids_on_sale();
                                    break;
                                case 'featured':
                                    $suggested_empty_args['include'] = wc_get_featured_product_ids();
                                    break;
                                case 'random':
                                    $suggested_empty_args['orderby'] = 'rand';
                                    break;
                            }

                            $suggested_empty_products = wc_get_products( apply_filters( 'woofc_suggested_empty_args', $suggested_empty_args ) );
                            $suggested_empty_products = apply_filters( 'woofc_suggested_empty_products', $suggested_empty_products );

                            if ( is_array( $suggested_empty_products ) && ! empty( $suggested_empty_products ) ) {
                                self::get_suggested_products( $suggested_empty_products, $link );
                            }
                        }
                    }

                    do_action( 'woofc_below_items' );
                    echo apply_filters( 'woofc_below_items_content', '' );

                    echo '</div><!-- woofc-area-mid -->';

                    echo '<div class="woofc-area-bot">';

                    do_action( 'woofc_above_bottom' );
                    echo apply_filters( 'woofc_above_bottom_content', '' );

                    if ( ! empty( $items ) ) {
                        if ( self::get_setting( 'empty', 'no' ) === 'yes' || self::get_setting( 'share', 'yes' ) === 'yes' ) {
                            // enable empty or share
                            echo '<div class="woofc-link">';

                            if ( self::get_setting( 'empty', 'no' ) === 'yes' ) {
                                echo '<div class="woofc-empty"><span class="woofc-empty-cart">' . self::localization( 'empty', esc_html__( 'Empty cart', 'woo-fly-cart' ) ) . '</span></div>';
                            }

                            if ( self::get_setting( 'share', 'yes' ) === 'yes' ) {
                                echo '<div class="woofc-share"><span class="woofc-share-cart wpcss-btn" data-hash="' . esc_attr( WC()->cart->get_cart_hash() ) . '">' . self::localization( 'share', esc_html__( 'Share cart', 'woo-fly-cart' ) ) . '</span></div>';
                            }

                            echo '</div>';
                        }

                        if ( self::get_setting( 'subtotal', 'yes' ) === 'yes' ) {
                            echo apply_filters( 'woofc_above_subtotal_content', '' );
                            echo '<div class="woofc-subtotal woofc-data"><div class="woofc-data-left">' . self::localization( 'subtotal', esc_html__( 'Subtotal', 'woo-fly-cart' ) ) . '</div><div id="woofc-subtotal" class="woofc-data-right">' . apply_filters( 'woofc_get_subtotal', WC()->cart->get_cart_subtotal() ) . '</div></div>';
                            echo apply_filters( 'woofc_below_subtotal_content', '' );
                        }

                        if ( class_exists( 'WPCleverWpcfb' ) && ( self::get_setting( 'free_shipping_bar', 'yes' ) === 'yes' ) ) {
                            echo '<div class="woofc-free-shipping-bar woofc-data">' . do_shortcode( '[wpcfb]' ) . '</div>';
                        }

                        if ( ( self::get_setting( 'tax', 'no' ) === 'yes' ) && wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) {
                            $taxable_address = WC()->customer->get_taxable_address();
                            $estimated_text  = '';

                            if ( WC()->customer->is_customer_outside_base() && ! WC()->customer->has_calculated_shipping() ) {
                                /* translators: %s location. */
                                $estimated_text = sprintf( ' <small>' . esc_html__( '(estimated for %s)', 'woo-fly-cart' ) . '</small>', WC()->countries->estimated_for_prefix( $taxable_address[0] ) . WC()->countries->countries[ $taxable_address[0] ] );
                            }

                            if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) {
                                foreach ( WC()->cart->get_tax_totals() as $code => $tax ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                                    ?>
                                    <div class="woofc-tax woofc-data woofc-tax-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
                                        <div class="woofc-data-left"><?php echo esc_html( $tax->label ) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                        <div class="woofc-data-right"><?php echo wp_kses_post( $tax->formatted_amount ); ?></div>
                                    </div>
                                    <?php
                                }
                            } else {
                                ?>
                                <div class="woofc-tax-total woofc-data">
                                    <div class="woofc-data-left"><?php echo esc_html( WC()->countries->tax_or_vat() ) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                    <div class="woofc-data-right"><?php wc_cart_totals_taxes_total_html(); ?></div>
                                </div>
                                <?php
                            }
                        }

                        if ( self::get_setting( 'total', 'yes' ) === 'yes' ) {
                            echo apply_filters( 'woofc_above_total_content', '' );
                            echo '<div class="woofc-total woofc-data"><div class="woofc-data-left">' . self::localization( 'total', esc_html__( 'Total', 'woo-fly-cart' ) ) . '</div><div id="woofc-total" class="woofc-data-right">' . apply_filters( 'woofc_get_total', WC()->cart->get_total() ) . '</div></div>';
                            echo apply_filters( 'woofc_below_total_content', '' );
                        }

                        do_action( 'woofc_above_buttons' );

                        if ( self::get_setting( 'buttons', '01' ) === '01' ) {
                            // both buttons
                            echo '<div class="woofc-action"><div class="woofc-action-inner"><div class="woofc-action-left"><a class="woofc-action-cart" href="' . wc_get_cart_url() . '">' . self::localization( 'cart', esc_html__( 'Cart', 'woo-fly-cart' ) ) . '</a></div><div class="woofc-action-right"><a class="woofc-action-checkout" href="' . wc_get_checkout_url() . '">' . self::localization( 'checkout', esc_html__( 'Checkout', 'woo-fly-cart' ) ) . '</a></div></div></div>';
                        } else {
                            if ( self::get_setting( 'buttons', '01' ) === '02' ) {
                                // cart
                                echo '<div class="woofc-action"><div class="woofc-action-inner"><div class="woofc-action-full"><a class="woofc-action-cart" href="' . wc_get_cart_url() . '">' . self::localization( 'cart', esc_html__( 'Cart', 'woo-fly-cart' ) ) . '</a></div></div></div>';
                            }

                            if ( self::get_setting( 'buttons', '01' ) === '03' ) {
                                // checkout
                                echo '<div class="woofc-action"><div class="woofc-action-inner"><div class="woofc-action-full"><a class="woofc-action-checkout" href="' . wc_get_checkout_url() . '">' . self::localization( 'checkout', esc_html__( 'Checkout', 'woo-fly-cart' ) ) . '</a></div></div></div>';
                            }
                        }

                        do_action( 'woofc_below_buttons' );

                        if ( ( self::get_setting( 'save_for_later', 'yes' ) === 'yes' ) && class_exists( 'WPCleverWoosl' ) ) {
                            echo '<div class="woofc-save-for-later">' . do_shortcode( '[woosl_list context="woofc"]' ) . '</div>';
                        }

                        if ( ! empty( $suggested ) ) {
                            $suggested_products = array_unique( $suggested_products );
                            $suggested_products = apply_filters( 'woofc_suggested_products_before_limit', array_diff( $suggested_products, $cart_products ), $suggested_products, $cart_products );

                            if ( $suggested_limit = (int) self::get_setting( 'suggested_limit', 10 ) ) {
                                $suggested_products = array_slice( $suggested_products, 0, $suggested_limit );
                            }

                            $suggested_products = apply_filters( 'woofc_suggested_products', $suggested_products, $cart_products );

                            if ( ! empty( $suggested_products ) ) {
                                self::get_suggested_products( $suggested_products, $link );
                            }
                        }

                        if ( self::get_setting( 'upsell_funnel', 'yes' ) === 'yes' && class_exists( 'Wpcuf' ) ) {
                            echo '<div class="woofc-upsell-funnel">' . do_shortcode( '[wpcuf_uf]' ) . '</div>';
                        }
                    }

                    if ( self::get_setting( 'continue', 'yes' ) === 'yes' ) {
                        echo '<div class="woofc-continue"><span class="woofc-continue-url" data-url="' . esc_url( self::get_setting( 'continue_url', '' ) ) . '">' . self::localization( 'continue', esc_html__( 'Continue shopping', 'woo-fly-cart' ) ) . '</span></div>';
                    }

                    do_action( 'woofc_below_bottom' );
                    echo apply_filters( 'woofc_below_bottom_content', '' );

                    echo '</div><!-- woofc-area-bot -->';

                    do_action( 'woofc_below_area' );
                    echo apply_filters( 'woofc_below_area_content', '' );

                    if ( self::get_setting( 'confetti', 'no' ) === 'yes' ) {
                        echo '<canvas id="woofc-canvas" class="woofc-canvas"></canvas>';
                    }

                    echo '</div>';

                    $product = $global_product;

                    return apply_filters( 'woofc_cart_area', ob_get_clean() );
                }

                function get_suggested_products( $suggested_products = [], $link = 'no' ) {
                    do_action( 'woofc_above_suggested', $suggested_products );
                    echo apply_filters( 'woofc_above_suggested_content', '' );
                    echo '<div class="woofc-suggested">';
                    echo '<div class="woofc-suggested-heading"><span>' . self::localization( 'suggested', esc_html__( 'You may be interested in&hellip;', 'woo-fly-cart' ) ) . '</span></div>';
                    echo '<div class="woofc-suggested-products ' . ( ( count( $suggested_products ) > 1 ) && ( apply_filters( 'woofc_slick', self::get_setting( 'suggested_carousel', 'yes' ), 'suggested' ) === 'yes' ) ? 'woofc-suggested-products-slick' : '' ) . '">';

                    foreach ( $suggested_products as $suggested_product_id ) {
                        $suggested_product = wc_get_product( $suggested_product_id );

                        if ( $suggested_product ) {
                            $suggested_product_link = $suggested_product->is_visible() ? $suggested_product->get_permalink() : '';

                            echo '<div class="woofc-suggested-product">';
                            echo '<div class="woofc-suggested-product-image">';

                            if ( ( $link !== 'no' ) && ! empty( $suggested_product_link ) ) {
                                echo sprintf( '<a ' . ( $link === 'yes_popup' ? 'class="woosq-link" data-id="' . esc_attr( $suggested_product_id ) . '" data-context="woofc"' : '' ) . ' href="%s" ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ) . '>%s</a>', esc_url( $suggested_product_link ), $suggested_product->get_image() );
                            } else {
                                echo $suggested_product->get_image();
                            }

                            echo '</div>';
                            echo '<div class="woofc-suggested-product-info">';
                            echo '<div class="woofc-suggested-product-name">';

                            if ( ( $link !== 'no' ) && ! empty( $suggested_product_link ) ) {
                                echo sprintf( '<a ' . ( $link === 'yes_popup' ? 'class="woosq-link" data-id="' . esc_attr( $suggested_product_id ) . '" data-context="woofc"' : '' ) . ' href="%s" ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ) . '>%s</a>', esc_url( $suggested_product_link ), $suggested_product->get_name() );
                            } else {
                                echo $suggested_product->get_name();
                            }

                            echo '</div>';
                            echo '<div class="woofc-suggested-product-price">' . $suggested_product->get_price_html() . '</div>';
                            echo '<div class="woofc-suggested-product-atc">' . do_shortcode( '[add_to_cart style="" show_price="false" id="' . esc_attr( $suggested_product->get_id() ) . '"]' ) . '</div>';
                            echo '</div>';
                            echo '</div>';
                        }
                    }

                    echo '</div></div>';
                    echo apply_filters( 'woofc_below_suggested_content', '' );
                    do_action( 'woofc_below_suggested', $suggested_products );
                }

                function get_checkout_area() {
                    if ( ! isset( WC()->cart ) ) {
                        return '';
                    }

                    ob_start();

                    echo '<div class="woofc-inner woofc-checkout-area woofc-hide">';
                    echo '<div class="woofc-area-top">';
                    echo '<div class="woofc-back hint--right" aria-label="' . esc_attr( self::localization( 'cart', esc_html__( 'Cart', 'woo-fly-cart' ) ) ) . '">←</div>';
                    echo '<span>' . self::localization( 'checkout', esc_html__( 'Checkout', 'woo-fly-cart' ) ) . '</span>';

                    if ( self::get_setting( 'close', 'yes' ) === 'yes' ) {
                        echo '<div class="woofc-close hint--left" aria-label="' . esc_attr( self::localization( 'close', esc_html__( 'Close', 'woo-fly-cart' ) ) ) . '"><i class="woofc-icon-icon10"></i></div>';
                    }

                    echo '</div>';
                    echo '<div class="woofc-area-mid"><div class="woofc-checkout-form">' . do_shortcode( '[woocommerce_checkout]' ) . '</div></div>';
                    echo '</div>';

                    return ob_get_clean();
                }

                function get_cart_count() {
                    if ( ! isset( WC()->cart ) ) {
                        return '';
                    }

                    $count       = WC()->cart->get_cart_contents_count();
                    $icon        = self::get_setting( 'count_icon', 'woofc-icon-cart7' );
                    $count_class = 'woofc-count woofc-count-' . $count . ' woofc-count-' . self::get_setting( 'count_position', 'bottom-left' );

                    if ( ( self::get_setting( 'count_hide_empty', 'no' ) === 'yes' ) && ( $count <= 0 ) ) {
                        $count_class .= ' woofc-count-hide-empty';
                    }

                    $cart_count = '<div id="woofc-count" class="' . esc_attr( apply_filters( 'woofc_cart_count_class', $count_class ) ) . '" data-count="' . esc_attr( $count ) . '">';
                    $cart_count .= '<i class="' . esc_attr( $icon ) . '"></i>';
                    $cart_count .= '<span id="woofc-count-number" class="woofc-count-number">' . esc_attr( $count ) . '</span>';
                    $cart_count .= '</div>';

                    return apply_filters( 'woofc_cart_count', $cart_count, $count, $icon );
                }

                function get_cart_menu() {
                    if ( ! isset( WC()->cart ) ) {
                        return '';
                    }

                    $count     = WC()->cart->get_cart_contents_count();
                    $subtotal  = WC()->cart->get_cart_subtotal();
                    $icon      = self::get_setting( 'count_icon', 'woofc-icon-cart7' );
                    $cart_menu = '<li class="' . esc_attr( apply_filters( 'woofc_cart_menu_class', 'menu-item woofc-menu-item menu-item-type-woofc' ) ) . '"><a href="' . esc_url( wc_get_cart_url() ) . '"><span class="woofc-menu-item-inner" data-count="' . esc_attr( $count ) . '"><i class="' . esc_attr( $icon ) . '"></i> <span class="woofc-menu-item-inner-subtotal">' . $subtotal . '</span></span></a></li>';

                    return apply_filters( 'woofc_cart_menu', $cart_menu, $count, $subtotal, $icon );
                }

                function nav_menu_items( $items, $args ) {
                    $selected    = false;
                    $saved_menus = self::get_setting( 'menus', [] );

                    if ( ! is_array( $saved_menus ) || empty( $saved_menus ) || ! property_exists( $args, 'menu' ) ) {
                        return $items;
                    }

                    if ( $args->menu instanceof WP_Term ) {
                        // menu object
                        if ( in_array( $args->menu->term_id, $saved_menus ) ) {
                            $selected = true;
                        }
                    } elseif ( is_numeric( $args->menu ) ) {
                        // menu id
                        if ( in_array( $args->menu, $saved_menus ) ) {
                            $selected = true;
                        }
                    } elseif ( is_string( $args->menu ) ) {
                        // menu slug or name
                        $menu = get_term_by( 'name', $args->menu, 'nav_menu' );

                        if ( ! $menu ) {
                            $menu = get_term_by( 'slug', $args->menu, 'nav_menu' );
                        }

                        if ( $menu && in_array( $menu->term_id, $saved_menus ) ) {
                            $selected = true;
                        }
                    }

                    if ( $selected ) {
                        $items .= self::get_cart_menu();
                    }

                    return $items;
                }

                function footer() {
                    if ( self::disable() ) {
                        return null;
                    }

                    // use 'woofc-position-' instead of 'woofc-effect-' from 5.3
                    $area_class = apply_filters( 'woofc_area_class', 'woofc-area woofc-position-' . esc_attr( self::get_setting( 'position', '05' ) ) . ' woofc-effect-' . esc_attr( self::get_setting( 'position', '05' ) ) . ' woofc-slide-' . esc_attr( self::get_setting( 'effect', 'yes' ) ) . ' woofc-rounded-' . esc_attr( self::get_setting( 'rounded', 'no' ) ) . ' woofc-style-' . esc_attr( self::get_setting( 'style', '01' ) ) );

                    echo '<div id="woofc-area" class="' . esc_attr( $area_class ) . '">';

                    echo self::get_cart_area();

                    echo '</div>';

                    if ( self::get_setting( 'count', 'yes' ) === 'yes' ) {
                        echo self::get_cart_count();
                    }

                    if ( self::get_setting( 'overlay_layer', 'yes' ) === 'yes' ) {
                        echo '<div class="woofc-overlay"></div>';
                    }
                }

                function disable() {
                    global $wp_query;
                    $disable = false;

                    if ( $current_page = $wp_query->get_queried_object_id() ) {
                        $hide_pages = self::get_setting( 'hide_pages', [] );

                        if ( isset( self::$settings['hide_cart_checkout'] ) && ( self::$settings['hide_cart_checkout'] === 'yes' ) ) {
                            $hide_pages[] = wc_get_page_id( 'cart' );
                            $hide_pages[] = wc_get_page_id( 'checkout' );
                        }

                        if ( ! empty( $hide_pages ) && in_array( $current_page, $hide_pages ) ) {
                            // hide on selected pages
                            $disable = true;
                        }
                    }

                    return apply_filters( 'woofc_disable', $disable );
                }

                function wpcsm_locations( $locations ) {
                    $locations['WPC Fly Cart'] = [
                            'woofc_above_area'      => esc_html__( 'Before cart', 'woo-fly-cart' ),
                            'woofc_below_area'      => esc_html__( 'After cart', 'woo-fly-cart' ),
                            'woofc_above_items'     => esc_html__( 'Before cart items', 'woo-fly-cart' ),
                            'woofc_below_items'     => esc_html__( 'After cart items', 'woo-fly-cart' ),
                            'woofc_above_item'      => esc_html__( 'Before cart item', 'woo-fly-cart' ),
                            'woofc_below_item'      => esc_html__( 'After cart item', 'woo-fly-cart' ),
                            'woofc_above_item_info' => esc_html__( 'Before cart item info', 'woo-fly-cart' ),
                            'woofc_below_item_info' => esc_html__( 'After cart item info', 'woo-fly-cart' ),
                            'woofc_above_item_name' => esc_html__( 'Before cart item name', 'woo-fly-cart' ),
                            'woofc_below_item_name' => esc_html__( 'After cart item name', 'woo-fly-cart' ),
                            'woofc_above_suggested' => esc_html__( 'Before suggested products', 'woo-fly-cart' ),
                            'woofc_below_suggested' => esc_html__( 'After suggested products', 'woo-fly-cart' ),
                            'woofc_above_buttons'   => esc_html__( 'Before buttons', 'woo-fly-cart' ),
                            'woofc_below_buttons'   => esc_html__( 'After buttons', 'woo-fly-cart' ),
                    ];

                    return $locations;
                }

                function cart_fragment( $fragments ) {

                    $fragments['.woofc-count']     = self::get_cart_count();
                    $fragments['.woofc-menu-item'] = self::get_cart_menu();
                    $fragments['.woofc-cart-link'] = self::get_cart_link();
                    $fragments['.woofc-cart-area'] = self::get_cart_area();

                    return $fragments;
                }

                function shortcode_cart_link() {
                    return apply_filters( 'woofc_shortcode_cart_link', self::get_cart_link() );
                }

                public static function get_cart_link( $echo = false ) {
                    if ( ! isset( WC()->cart ) ) {
                        return '';
                    }

                    $count     = WC()->cart->get_cart_contents_count();
                    $subtotal  = WC()->cart->get_cart_subtotal();
                    $icon      = self::get_setting( 'count_icon', 'woofc-icon-cart7' );
                    $cart_link = '<span class="woofc-cart-link"><a href="' . wc_get_cart_url() . '"><span class="woofc-cart-link-inner" data-count="' . esc_attr( $count ) . '"><i class="' . esc_attr( $icon ) . '"></i> <span class="woofc-cart-link-inner-subtotal">' . $subtotal . '</span></span></a></span>';
                    $cart_link = apply_filters( 'woofc_cart_link', $cart_link, $count, $subtotal, $icon );

                    if ( $echo ) {
                        echo $cart_link;
                    } else {
                        return $cart_link;
                    }

                    return null;
                }


                public static function sanitize_array( $arr ) {
                    foreach ( (array) $arr as $k => $v ) {
                        if ( is_array( $v ) ) {
                            $arr[ $k ] = self::sanitize_array( $v );
                        } else {
                            $arr[ $k ] = sanitize_post_field( 'post_content', $v, 0, 'db' );
                        }
                    }

                    return $arr;
                }

                /**
                 * Normalize the "suggested" setting for backward compatibility before v5.2.2.
                 * Previously stored as a string, now an array.
                 *
                 * @param mixed $suggested Raw setting value.
                 *
                 * @return array
                 */
                public static function normalize_suggested( $suggested ): array {
                    if ( is_array( $suggested ) ) {
                        return $suggested;
                    }

                    switch ( (string) $suggested ) {
                        case 'cross_sells':
                            return [ 'cross_sells' ];
                        case 'related':
                            return [ 'related' ];
                        case 'both':
                            return [ 'related', 'cross_sells' ];
                        default:
                            return [];
                    }
                }
            }

            return WPCleverWoofc::instance();
        }

        return null;
    }
}

if ( ! function_exists( 'woofc_notice_wc' ) ) {
    function woofc_notice_wc() {
        ?>
        <div class="error">
            <p><strong>WPC Fly Cart</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
        <?php
    }
}
