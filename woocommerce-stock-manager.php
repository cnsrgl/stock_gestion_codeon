<?php
/**
 * Plugin Name: WooCommerce Stock Editor 
 * Description: Advanced WooCommerce stock management plugin with instant updates, dark mode, customizable stock tracking options, and bulk editing.
 * Version: 3.0
 * Author: Codeon
 * Author URI: https://codeon.ch
 * Text Domain: woocommerce-stock-editor-enhanced
 * Domain Path: /languages/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Codeon\WCStockEditorEnhanced;

use Exception;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Check if the class already exists to prevent redeclaration.
if ( ! class_exists( 'Codeon\WCStockEditorEnhanced\WC_Stock_Editor_Enhanced' ) ) :

class WC_Stock_Editor_Enhanced {

    /**
     * Number of products per page.
     *
     * @var int
     */
    private $per_page = 100;

    /**
     * Constructor.
     */
    public function __construct() {
        // Define constants.
        $this->define_constants();

        // Load text domain.
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

        // Initialize hooks.
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );

        // Register settings.
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // AJAX actions.
        add_action( 'wp_ajax_wse_fetch_products', [ $this, 'ajax_fetch_products' ] );
        add_action( 'wp_ajax_wse_get_variations', [ $this, 'ajax_get_variations' ] );
        add_action( 'wp_ajax_wse_update_product', [ $this, 'ajax_update_product' ] ); // For instant updates.
        add_action( 'wp_ajax_wse_bulk_update_products', [ $this, 'ajax_bulk_update_products' ] ); // For bulk updates.

        // AJAX action for license validation.
        add_action( 'wp_ajax_wse_validate_license', [ $this, 'ajax_validate_license' ] );

        // Initialize change count.
        if ( get_option( 'wse_change_count' ) === false ) {
            add_option( 'wse_change_count', 0 );
        }

        // Add admin notices.
        add_action( 'admin_notices', [ $this, 'admin_notices' ] );
    }

    /**
     * Define plugin constants.
     */
    private function define_constants() {
        define( 'WSE_VERSION', '2.9' );
        define( 'WSE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'WSE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'woocommerce-stock-editor-enhanced', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * On activation, create the log table if not exists.
     */
    /**public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wse_change_log';
        $charset_collate = $wpdb->get_charset_collate();
    
        // 1) Var olan tabloyu *sil* (DİKKAT: Tüm kayıtlar da silinir!)
        $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
    
        // 2) Tekrar oluştur
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            field varchar(50) NOT NULL,
            old_value varchar(255),
            new_value varchar(255),
            user_id bigint(20) unsigned NOT NULL,
            change_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
    
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
    */

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_admin_assets( $hook ) {
        // Settings pages.
        if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'wse_settings' ) !== false ) {
            // Enqueue WordPress color picker.
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );

            // Enqueue Toastr CSS.
            wp_enqueue_style( 'toastr-css', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css', [], 'latest' );

            // Enqueue Tailwind CSS.
            wp_enqueue_style( 'tailwind-css', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css', [], '2.2.19' );

            // Enqueue Toastr JS.
            wp_enqueue_script( 'toastr-js', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js', [ 'jquery' ], 'latest', true );

            // Enqueue custom admin JS for settings.
            wp_enqueue_script(
                'wse-admin-settings-js',
                WSE_PLUGIN_URL . 'js/wse-admin-settings.js',
                [ 'jquery', 'wp-color-picker', 'toastr-js' ],
                WSE_VERSION,
                true
            );

            // Localize script for settings.
            wp_localize_script(
                'wse-admin-settings-js',
                'wse_admin_settings_object',
                [
                    'ajax_url'      => admin_url( 'admin-ajax.php' ),
                    'license_nonce' => wp_create_nonce( 'wse_license_nonce' ),
                    'messages'      => [
                        'valid'    => __( 'License is valid.', 'woocommerce-stock-editor-enhanced' ),
                        'invalid'  => __( 'License is invalid.', 'woocommerce-stock-editor-enhanced' ),
                        'error'    => __( 'An error occurred while validating the license.', 'woocommerce-stock-editor-enhanced' ),
                        'loading'  => __( 'Validating license...', 'woocommerce-stock-editor-enhanced' ),
                    ],
                ]
            );

            // Toastr Configuration
            wp_add_inline_script( 'toastr-js', '
                toastr.options = {
                    "closeButton": true,
                    "debug": false,
                    "newestOnTop": false,
                    "progressBar": true,
                    "positionClass": "toast-top-right",
                    "preventDuplicates": false,
                    "onclick": null,
                    "showDuration": "300",
                    "hideDuration": "1000",
                    "timeOut": "5000",
                    "extendedTimeOut": "1000",
                    "showEasing": "swing",
                    "hideEasing": "linear",
                    "showMethod": "fadeIn",
                    "hideMethod": "fadeOut"
                };
            ' );
        }

        // Main stock editor page.
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'woocommerce-stock-editor-enhanced' ) {
            // Enqueue Tailwind CSS.
            wp_enqueue_style( 'tailwind-css', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css', [], '2.2.19' );

            // Enqueue Toastr CSS.
            wp_enqueue_style( 'toastr-css', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css', [], 'latest' );

            // Enqueue custom admin CSS.
            wp_enqueue_style( 'wse-admin-css', WSE_PLUGIN_URL . 'css/wse-admin.css', [], WSE_VERSION );

            // Enqueue Toastr JS.
            wp_enqueue_script( 'toastr-js', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js', [ 'jquery' ], 'latest', true );

            // Enqueue custom admin JS for stock editor.
            wp_enqueue_script(
                'wse-admin-js',
                WSE_PLUGIN_URL . 'js/wse-admin.js',
                [ 'jquery', 'toastr-js' ],
                WSE_VERSION,
                true
            );

            // Localize script for stock editor.
            $settings                = get_option( 'wse_settings', [] );
            $low_stock_threshold     = isset( $settings['low_stock_threshold'] ) ? intval( $settings['low_stock_threshold'] ) : 3;
            $medium_stock_threshold  = isset( $settings['medium_stock_threshold'] ) ? intval( $settings['medium_stock_threshold'] ) : 7;
            $enable_dark_mode        = isset( $settings['enable_dark_mode'] ) && 'yes' === $settings['enable_dark_mode'] ? 'yes' : 'no';
            $low_stock_color         = ( isset( $settings['low_stock_color'] ) && preg_match( '/^#[a-f0-9]{6}$/i', $settings['low_stock_color'] ) ) ? $settings['low_stock_color'] : '#f56565';
            $medium_stock_color      = ( isset( $settings['medium_stock_color'] ) && preg_match( '/^#[a-f0-9]{6}$/i', $settings['medium_stock_color'] ) ) ? $settings['medium_stock_color'] : '#ed8936';
            $high_stock_color        = ( isset( $settings['high_stock_color'] ) && preg_match( '/^#[a-f0-9]{6}$/i', $settings['high_stock_color'] ) ) ? $settings['high_stock_color'] : '#48bb78';

            wp_localize_script(
                'wse-admin-js',
                'wse_ajax_object',
                [
                    'ajax_url'               => admin_url( 'admin-ajax.php' ),
                    'wse_nonce'              => wp_create_nonce( 'wse_nonce_action' ),
                    'wse_variation_nonce'    => wp_create_nonce( 'wse_variation_nonce_action' ),
                    'low_stock_threshold'    => $low_stock_threshold,
                    'medium_stock_threshold' => $medium_stock_threshold,
                    'enable_dark_mode'       => $enable_dark_mode,
                    'low_stock_color'        => $low_stock_color,
                    'medium_stock_color'     => $medium_stock_color,
                    'high_stock_color'       => $high_stock_color,
                    'messages'               => [
                        'update_success'           => __( 'Product updated successfully.', 'woocommerce-stock-editor-enhanced' ),
                        'update_error'             => __( 'Error updating product.', 'woocommerce-stock-editor-enhanced' ),
                        'ajax_error'               => __( 'An error occurred: ', 'woocommerce-stock-editor-enhanced' ),
                        'fetch_error'              => __( 'An error occurred while fetching products.', 'woocommerce-stock-editor-enhanced' ),
                        'no_products'              => __( 'No products found.', 'woocommerce-stock-editor-enhanced' ),
                        'loading'                  => __( 'Loading...', 'woocommerce-stock-editor-enhanced' ),
                        'error_occurred'           => __( 'An error occurred.', 'woocommerce-stock-editor-enhanced' ),
                        'loading_variations'       => __( 'Loading variations...', 'woocommerce-stock-editor-enhanced' ),
                        'error_loading_variations' => __( 'Error loading variations.', 'woocommerce-stock-editor-enhanced' ),
                        'no_products_selected'     => __( 'No products selected.', 'woocommerce-stock-editor-enhanced' ),
                        'please_specify'           => __( 'Please specify a field and value for bulk update.', 'woocommerce-stock-editor-enhanced' ),
                        'bulk_update_failed'       => __( 'Bulk update failed.', 'woocommerce-stock-editor-enhanced' ),
                        'bulk_update_error'        => __( 'An error occurred during bulk update.', 'woocommerce-stock-editor-enhanced' ),
                    ],
                    'initial_orderby'        => 'title',
                    'initial_order'          => 'asc',
                ]
            );
        }
    }

    /**
     * Add admin menu items.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Stock Management', 'woocommerce-stock-editor-enhanced' ),
            __( 'Stock Management', 'woocommerce-stock-editor-enhanced' ),
            'manage_woocommerce',
            'woocommerce-stock-editor-enhanced',
            [ $this, 'stock_editor_page' ],
            'dashicons-archive',
            25
        );

        add_submenu_page(
            'woocommerce-stock-editor-enhanced',
            __( 'Stock Editor Settings', 'woocommerce-stock-editor-enhanced' ),
            __( 'Settings', 'woocommerce-stock-editor-enhanced' ),
            'manage_woocommerce',
            'wse_settings',
            [ $this, 'settings_page' ]
        );

        add_submenu_page(
            'woocommerce-stock-editor-enhanced',
            __( 'Features', 'woocommerce-stock-editor-enhanced' ),
            __( 'Features', 'woocommerce-stock-editor-enhanced' ),
            'manage_woocommerce',
            'wse_features',
            [ $this, 'features_page' ]
        );

        // Purchase submenu if license is required.
        if ( $this->is_license_required() ) {
            add_submenu_page(
                'woocommerce-stock-editor-enhanced',
                __( 'Purchase License', 'woocommerce-stock-editor-enhanced' ),
                __( 'Purchase', 'woocommerce-stock-editor-enhanced' ),
                'manage_woocommerce',
                'wse_purchase',
                [ $this, 'purchase_page' ]
            );
        }
    }

    /**
     * Render the stock editor interface.
     */
    public function stock_editor_page() {
        $this->display_stock_editor();
    }

    /**
     * Purchase License page content.
     */
    public function purchase_page() {
        ?>
        <div class="wrap">
            <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-gray-100"><?php esc_html_e( 'Purchase Your License', 'woocommerce-stock-editor-enhanced' ); ?></h1>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <!-- Introduction Section -->
                <section class="mb-8">
                    <h2 class="text-2xl font-semibold mb-2 text-gray-700 dark:text-gray-200"><?php esc_html_e( 'Unlock Premium Features', 'woocommerce-stock-editor-enhanced' ); ?></h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        <?php esc_html_e( 'Upgrade to the premium version of WooCommerce Stock Editor to access advanced features that streamline your stock management process.', 'woocommerce-stock-editor-enhanced' ); ?>
                    </p>
                </section>

                <!-- Features Section -->
                <section class="mb-8">
                    <h3 class="text-xl font-semibold mb-4 text-gray-700 dark:text-gray-200"><?php esc_html_e( 'Key Features', 'woocommerce-stock-editor-enhanced' ); ?></h3>
                    <ul class="list-disc list-inside space-y-2 text-gray-600 dark:text-gray-400">
                        <li><?php esc_html_e( 'Bulk Update of Products', 'woocommerce-stock-editor-enhanced' ); ?></li>
                        <li><?php esc_html_e( 'Advanced Stock Thresholds', 'woocommerce-stock-editor-enhanced' ); ?></li>
                        <li><?php esc_html_e( 'Instant Price and Stock Updates', 'woocommerce-stock-editor-enhanced' ); ?></li>
                        <li><?php esc_html_e( 'Detailed Stock Turnover Reports', 'woocommerce-stock-editor-enhanced' ); ?></li>
                        <li><?php esc_html_e( 'Priority Support and Updates', 'woocommerce-stock-editor-enhanced' ); ?></li>
                    </ul>
                </section>

                <!-- Differences Section -->
                <section class="mb-8">
                    <h3 class="text-xl font-semibold mb-4 text-gray-700 dark:text-gray-200"><?php esc_html_e( 'Why Choose Our Plugin?', 'woocommerce-stock-editor-enhanced' ); ?></h3>
                    <ul class="list-disc list-inside space-y-2 text-gray-600 dark:text-gray-400">
                        <li><?php esc_html_e( 'Comprehensive stock management tailored for WooCommerce.', 'woocommerce-stock-editor-enhanced' ); ?></li>
                        <li><?php esc_html_e( 'User-friendly interface with modern design.', 'woocommerce-stock-editor-enhanced' ); ?></li>
                        <li><?php esc_html_e( 'Seamless integration with other WooCommerce extensions.', 'woocommerce-stock-editor-enhanced' ); ?></li>
                        <li><?php esc_html_e( 'Regular updates with new features and improvements.', 'woocommerce-stock-editor-enhanced' ); ?></li>
                        <li><?php esc_html_e( 'Dedicated support team to assist you.', 'woocommerce-stock-editor-enhanced' ); ?></li>
                    </ul>
                </section>

                <!-- Purchase Button -->
                <div class="flex justify-center">
                    <a href="https://codeon.ch/purchase" target="_blank" rel="noopener noreferrer" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md transition duration-300">
                        <?php esc_html_e( 'Purchase Now', 'woocommerce-stock-editor-enhanced' ); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Features page content.
     */
    public function features_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'woocommerce-stock-editor-enhanced' ) );
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Plugin Features', 'woocommerce-stock-editor-enhanced' ); ?></h1>

            <p>
                Welcome to the Enhanced WooCommerce Stock Editor. This plugin offers advanced stock management directly from your WordPress dashboard.
            </p>

            <h2><?php esc_html_e( 'Key Features:', 'woocommerce-stock-editor-enhanced' ); ?></h2>
            <ul style="list-style: disc; padding-left: 20px;">
                <li><?php esc_html_e( 'Instant updates of stock and price data.', 'woocommerce-stock-editor-enhanced' ); ?></li>
                <li><?php esc_html_e( 'Toggleable dark mode.', 'woocommerce-stock-editor-enhanced' ); ?></li>
                <li><?php esc_html_e( 'Customizable stock threshold options and color codes.', 'woocommerce-stock-editor-enhanced' ); ?></li>
                <li><?php esc_html_e( 'Display total sales per product.', 'woocommerce-stock-editor-enhanced' ); ?></li>
                <li><?php esc_html_e( 'Manage simple, variable, and other product types.', 'woocommerce-stock-editor-enhanced' ); ?></li>
                <li><strong><?php esc_html_e( 'New:', 'woocommerce-stock-editor-enhanced' ); ?></strong> <?php esc_html_e( 'Bulk Update of selected products.', 'woocommerce-stock-editor-enhanced' ); ?></li>
            </ul>

            <p>
                Developed by <strong>Codeon</strong>. For more information, visit 
                <a href="https://codeon.ch" target="_blank" rel="noopener noreferrer">https://codeon.ch</a>.
            </p>
        </div>
        <?php
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting( 'wse_settings_group', 'wse_settings', [ $this, 'sanitize_settings' ] );

        // General Settings Section.
        add_settings_section(
            'wse_main_settings',
            __( 'Main Settings', 'woocommerce-stock-editor-enhanced' ),
            null,
            'wse_settings_general'
        );

        // Display Settings Section.
        add_settings_section(
            'wse_display_settings',
            __( 'Display Settings', 'woocommerce-stock-editor-enhanced' ),
            null,
            'wse_settings_display'
        );

        // License Settings Section.
        add_settings_section(
            'wse_license_settings',
            __( 'License Settings', 'woocommerce-stock-editor-enhanced' ),
            null,
            'wse_settings_license'
        );

        // General Settings Fields.
        $this->add_settings_fields();
    }

    /**
     * Add settings fields to sections.
     */
    private function add_settings_fields() {
        // General Settings Fields.
        add_settings_field(
            'default_order',
            __( 'Default Order By', 'woocommerce-stock-editor-enhanced' ),
            [ $this, 'render_select_field' ],
            'wse_settings_general',
            'wse_main_settings',
            [
                'label_for'       => 'default_order',
                'wse_option_name' => 'default_order',
                'options'         => [
                    'title'          => __( 'Title', 'woocommerce-stock-editor-enhanced' ),
                    'price'          => __( 'Price', 'woocommerce-stock-editor-enhanced' ),
                    'stock_quantity' => __( 'Stock Quantity', 'woocommerce-stock-editor-enhanced' ),
                    'total_sales'    => __( 'Total Sales', 'woocommerce-stock-editor-enhanced' ),
                ],
                'description'     => __( 'Select the default ordering for products.', 'woocommerce-stock-editor-enhanced' ),
                'default'         => 'title',
            ]
        );

        add_settings_field(
            'default_order_dir',
            __( 'Default Order Direction', 'woocommerce-stock-editor-enhanced' ),
            [ $this, 'render_select_field' ],
            'wse_settings_general',
            'wse_main_settings',
            [
                'label_for'       => 'default_order_dir',
                'wse_option_name' => 'default_order_dir',
                'options'         => [
                    'asc'  => __( 'Ascending', 'woocommerce-stock-editor-enhanced' ),
                    'desc' => __( 'Descending', 'woocommerce-stock-editor-enhanced' ),
                ],
                'description'     => __( 'Select the default order direction for products.', 'woocommerce-stock-editor-enhanced' ),
                'default'         => 'asc',
            ]
        );

        // Display Settings Fields.
        add_settings_field(
            'low_stock_threshold',
            __( 'Low Stock Threshold', 'woocommerce-stock-editor-enhanced' ),
            [ $this, 'render_number_field' ],
            'wse_settings_display',
            'wse_display_settings',
            [
                'label_for'       => 'low_stock_threshold',
                'wse_option_name' => 'low_stock_threshold',
                'description'     => __( 'Set the threshold below which stock is considered low.', 'woocommerce-stock-editor-enhanced' ),
                'default'         => 3,
            ]
        );

        add_settings_field(
            'medium_stock_threshold',
            __( 'Medium Stock Threshold', 'woocommerce-stock-editor-enhanced' ),
            [ $this, 'render_number_field' ],
            'wse_settings_display',
            'wse_display_settings',
            [
                'label_for'       => 'medium_stock_threshold',
                'wse_option_name' => 'medium_stock_threshold',
                'description'     => __( 'Set the threshold below which stock is considered medium.', 'woocommerce-stock-editor-enhanced' ),
                'default'         => 7,
            ]
        );

        add_settings_field(
            'enable_dark_mode',
            __( 'Enable Dark Mode', 'woocommerce-stock-editor-enhanced' ),
            [ $this, 'render_checkbox_field' ],
            'wse_settings_display',
            'wse_display_settings',
            [
                'label_for'       => 'enable_dark_mode',
                'wse_option_name' => 'enable_dark_mode',
                'description'     => __( 'Enable dark mode for the stock editor interface.', 'woocommerce-stock-editor-enhanced' ),
                'default'         => 'no',
            ]
        );

        add_settings_field(
            'low_stock_color',
            __( 'Low Stock Color', 'woocommerce-stock-editor-enhanced' ),
            [ $this, 'render_color_picker_field' ],
            'wse_settings_display',
            'wse_display_settings',
            [
                'label_for'       => 'low_stock_color',
                'wse_option_name' => 'low_stock_color',
                'description'     => __( 'Select the color representing low stock.', 'woocommerce-stock-editor-enhanced' ),
                'default'         => '#f56565',
            ]
        );

        add_settings_field(
            'medium_stock_color',
            __( 'Medium Stock Color', 'woocommerce-stock-editor-enhanced' ),
            [ $this, 'render_color_picker_field' ],
            'wse_settings_display',
            'wse_display_settings',
            [
                'label_for'       => 'medium_stock_color',
                'wse_option_name' => 'medium_stock_color',
                'description'     => __( 'Select the color representing medium stock.', 'woocommerce-stock-editor-enhanced' ),
                'default'         => '#ed8936',
            ]
        );

        add_settings_field(
            'high_stock_color',
            __( 'High Stock Color', 'woocommerce-stock-editor-enhanced' ),
            [ $this, 'render_color_picker_field' ],
            'wse_settings_display',
            'wse_display_settings',
            [
                'label_for'       => 'high_stock_color',
                'wse_option_name' => 'high_stock_color',
                'description'     => __( 'Select the color representing high stock.', 'woocommerce-stock-editor-enhanced' ),
                'default'         => '#48bb78',
            ]
        );

        // License Settings Fields.
        add_settings_field(
            'license_key',
            __( 'License Key', 'woocommerce-stock-editor-enhanced' ),
            [ $this, 'render_text_field' ],
            'wse_settings_license',
            'wse_license_settings',
            [
                'label_for'       => 'license_key',
                'class'           => 'wse_row',
                'wse_option_name' => 'license_key',
                'description'     => __( 'Enter your license key to unlock full features after 20 changes.', 'woocommerce-stock-editor-enhanced' ),
                'default'         => '',
            ]
        );

        // Additional License Information Field (Read-only).
        add_settings_field(
            'license_status',
            __( 'License Status', 'woocommerce-stock-editor-enhanced' ),
            [ $this, 'render_license_status_field' ],
            'wse_settings_license',
            'wse_license_settings',
            [
                'label_for'       => 'license_status',
                'wse_option_name' => 'license_status',
                'description'     => __( 'Current status of your license.', 'woocommerce-stock-editor-enhanced' ),
                'default'         => 'Unknown',
            ]
        );
    }

    /**
     * Sanitize settings input.
     *
     * @param array $input Input data.
     * @return array Sanitized data.
     */
    public function sanitize_settings( $input ) {
        // Get old options to preserve other tabs' values.
        $options = get_option( 'wse_settings', [] );

        // Start with old settings
        $sanitized = $options;

        // General Settings Sanitization.
        if ( isset( $input['default_order'] ) ) {
            $sanitized['default_order'] = in_array( $input['default_order'], [ 'title', 'price', 'stock_quantity', 'total_sales' ], true ) ? sanitize_key( $input['default_order'] ) : 'title';
        }

        if ( isset( $input['default_order_dir'] ) ) {
            $sanitized['default_order_dir'] = in_array( $input['default_order_dir'], [ 'asc', 'desc' ], true ) ? sanitize_key( $input['default_order_dir'] ) : 'asc';
        }

        // Display Settings Sanitization.
        if ( isset( $input['low_stock_threshold'] ) ) {
            $sanitized['low_stock_threshold'] = absint( $input['low_stock_threshold'] );
        }

        if ( isset( $input['medium_stock_threshold'] ) ) {
            $sanitized['medium_stock_threshold'] = absint( $input['medium_stock_threshold'] );
        }

        if ( isset( $input['enable_dark_mode'] ) ) {
            $sanitized['enable_dark_mode'] = 'yes' === $input['enable_dark_mode'] ? 'yes' : 'no';
        }

        if ( isset( $input['low_stock_color'] ) && preg_match( '/^#[a-f0-9]{6}$/i', $input['low_stock_color'] ) ) {
            $sanitized['low_stock_color'] = sanitize_hex_color( $input['low_stock_color'] );
        }

        if ( isset( $input['medium_stock_color'] ) && preg_match( '/^#[a-f0-9]{6}$/i', $input['medium_stock_color'] ) ) {
            $sanitized['medium_stock_color'] = sanitize_hex_color( $input['medium_stock_color'] );
        }

        if ( isset( $input['high_stock_color'] ) && preg_match( '/^#[a-f0-9]{6}$/i', $input['high_stock_color'] ) ) {
            $sanitized['high_stock_color'] = sanitize_hex_color( $input['high_stock_color'] );
        }

        // License Settings Sanitization.
        if ( isset( $input['license_key'] ) ) {
            $sanitized['license_key'] = sanitize_text_field( $input['license_key'] );
        }

        return $sanitized;
    }

    /**
     * Render a select field.
     *
     * @param array $args Field arguments.
     */
    public function render_select_field( $args ) {
        $options = get_option( 'wse_settings' );
        $value   = isset( $options[ $args['wse_option_name'] ] ) ? esc_attr( $options[ $args['wse_option_name'] ] ) : esc_attr( $args['default'] );
        ?>
        <select id="<?php echo esc_attr( $args['label_for'] ); ?>" name="wse_settings[<?php echo esc_attr( $args['wse_option_name'] ); ?>]" class="block w-full rounded-lg border-gray-300 p-2">
            <?php foreach ( $args['options'] as $key => $label ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php
    }

    /**
     * Render a number field.
     *
     * @param array $args Field arguments.
     */
    public function render_number_field( $args ) {
        $options = get_option( 'wse_settings' );
        $value   = isset( $options[ $args['wse_option_name'] ] ) ? intval( $options[ $args['wse_option_name'] ] ) : intval( $args['default'] );
        ?>
        <input type="number" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="wse_settings[<?php echo esc_attr( $args['wse_option_name'] ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="block w-full rounded-lg border-gray-300 p-2">
        <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php
    }

    /**
     * Render a checkbox field.
     *
     * @param array $args Field arguments.
     */
    public function render_checkbox_field( $args ) {
        $options = get_option( 'wse_settings' );
        $checked = isset( $options[ $args['wse_option_name'] ] ) && 'yes' === $options[ $args['wse_option_name'] ] ? 'checked' : '';
        ?>
        <label class="inline-flex items-center">
            <input type="checkbox" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="wse_settings[<?php echo esc_attr( $args['wse_option_name'] ); ?>]" value="yes" <?php echo $checked; ?> class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            <span class="ml-2"><?php echo esc_html( $args['description'] ); ?></span>
        </label>
        <?php
    }

    /**
     * Render a color picker field.
     *
     * @param array $args Field arguments.
     */
    public function render_color_picker_field( $args ) {
        $options = get_option( 'wse_settings' );
        $value   = isset( $options[ $args['wse_option_name'] ] ) ? esc_attr( $options[ $args['wse_option_name'] ] ) : esc_attr( $args['default'] );
        ?>
        <input type="text" class="wp-color-picker-field block w-full rounded-lg border-gray-300 p-2" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="wse_settings[<?php echo esc_attr( $args['wse_option_name'] ); ?>]" value="<?php echo esc_attr( $value ); ?>" data-default-color="<?php echo esc_attr( $args['default'] ); ?>" />
        <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php
    }

    /**
     * Render a text field.
     *
     * @param array $args Field arguments.
     */
    public function render_text_field( $args ) {
        $options = get_option( 'wse_settings' );
        $value   = isset( $options[ $args['wse_option_name'] ] ) ? esc_attr( $options[ $args['wse_option_name'] ] ) : esc_attr( $args['default'] );
        ?>
        <input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="wse_settings[<?php echo esc_attr( $args['wse_option_name'] ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="block w-full rounded-lg border-gray-300 p-2">
        <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php
    }

    /**
     * Render the license status field (read-only).
     *
     * @param array $args Field arguments.
     */
    public function render_license_status_field( $args ) {
        $settings        = get_option( 'wse_settings' );
        $license_key     = isset( $settings['license_key'] ) ? $settings['license_key'] : '';
        $status          = 'Unknown';

        if ( ! empty( $license_key ) ) {
            $is_valid = $this->is_license_valid( $license_key );
            $status   = $is_valid ? __( 'Valid', 'woocommerce-stock-editor-enhanced' ) : __( 'Invalid', 'woocommerce-stock-editor-enhanced' );
        } else {
            $status = __( 'Not Provided', 'woocommerce-stock-editor-enhanced' );
        }

        ?>
        <input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" value="<?php echo esc_attr( $status ); ?>" class="block w-full rounded-lg border-gray-300 p-2 bg-gray-100 dark:bg-gray-700" readonly>
        <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php
    }

    /**
     * Render the settings page.
     */
    public function settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'woocommerce-stock-editor-enhanced' ) );
        }

        $active_tab = $this->get_active_tab();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Stock Editor Settings', 'woocommerce-stock-editor-enhanced' ); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=wse_settings&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'woocommerce-stock-editor-enhanced' ); ?></a>
                <a href="?page=wse_settings&tab=display" class="nav-tab <?php echo 'display' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Display', 'woocommerce-stock-editor-enhanced' ); ?></a>
                <a href="?page=wse_settings&tab=license" class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'License', 'woocommerce-stock-editor-enhanced' ); ?></a>
            </h2>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'wse_settings_group' );
                do_settings_sections( 'wse_settings_' . $active_tab );
                submit_button();

                if ( 'license' === $active_tab ) {
                    ?>
                    <button type="button" class="mt-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300" id="validate-license-button">
                        <?php esc_html_e( 'Validate License', 'woocommerce-stock-editor-enhanced' ); ?>
                    </button>
                    <div id="license-validation-message" class="mt-2"></div>
                    <?php
                }
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get the active tab.
     *
     * @return string
     */
    private function get_active_tab() {
        return isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
    }

    /**
     * Display the stock editor interface.
     */
    private function display_stock_editor() {
        // Get hierarchical categories.
        $categories = get_terms(
            [
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'orderby'    => 'name',
                'parent'     => 0, // Get top-level categories.
            ]
        );

        $product_types = [ 'simple', 'variable', 'grouped', 'external' ];
        $settings      = get_option( 'wse_settings' );
        $enable_dark_mode = isset( $settings['enable_dark_mode'] ) && 'yes' === $settings['enable_dark_mode'];

        $low_stock_color      = isset( $settings['low_stock_color'] ) ? esc_attr( $settings['low_stock_color'] ) : '#f56565';
        $medium_stock_color   = isset( $settings['medium_stock_color'] ) ? esc_attr( $settings['medium_stock_color'] ) : '#ed8936';
        $high_stock_color     = isset( $settings['high_stock_color'] ) ? esc_attr( $settings['high_stock_color'] ) : '#48bb78';

        $license_required = $this->is_license_required();
        ?>
        <div class="wrap">
            <h1 class="mb-4 text-2xl font-bold text-gray-800 dark:text-gray-100"><?php esc_html_e( 'WooCommerce Stock Editor', 'woocommerce-stock-editor-enhanced' ); ?></h1>

            <div class="stock-info flex items-center justify-between bg-white dark:bg-gray-800 p-4 rounded-lg shadow mb-4">
                <!-- Stock Levels Guide -->
                <div class="flex items-center space-x-6">
                    <!-- Low Stock -->
                    <div class="flex items-center space-x-2">
                        <span class="inline-block w-4 h-4 rounded-full" style="background-color: <?php echo esc_attr( $low_stock_color ); ?>;"></span>
                        <span class="text-sm text-gray-600 dark:text-gray-300"><?php esc_html_e( 'Low Stock', 'woocommerce-stock-editor-enhanced' ); ?></span>
                    </div>
                    <!-- Medium Stock -->
                    <div class="flex items-center space-x-2">
                        <span class="inline-block w-4 h-4 rounded-full" style="background-color: <?php echo esc_attr( $medium_stock_color ); ?>;"></span>
                        <span class="text-sm text-gray-600 dark:text-gray-300"><?php esc_html_e( 'Medium Stock', 'woocommerce-stock-editor-enhanced' ); ?></span>
                    </div>
                    <!-- High Stock -->
                    <div class="flex items-center space-x-2">
                        <span class="inline-block w-4 h-4 rounded-full" style="background-color: <?php echo esc_attr( $high_stock_color ); ?>;"></span>
                        <span class="text-sm text-gray-600 dark:text-gray-300"><?php esc_html_e( 'High Stock', 'woocommerce-stock-editor-enhanced' ); ?></span>
                    </div>
                </div>
                <div class="flex-1 mb-2 md:mb-0 p-4">
                    <input type="text" name="search" id="product-filter" class="block w-full rounded-lg border-gray-300 p-2" placeholder="<?php esc_attr_e( 'Search Products...', 'woocommerce-stock-editor-enhanced' ); ?>">
                </div>
                <!-- Dark Mode Toggle -->
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600 dark:text-gray-300"><?php esc_html_e( 'Dark Mode', 'woocommerce-stock-editor-enhanced' ); ?></span>
                    <input type="checkbox" id="dark-mode-toggle" class="w-7 h-7 text-blue-500 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-300 dark:bg-gray-700 dark:border-gray-600" <?php checked( $enable_dark_mode, true ); ?> <?php echo $license_required ? 'disabled' : ''; ?>>
                </div>
            </div>

            <!-- Filters and Bulk Update BEFORE Pagination -->
            <form method="get" id="wse-filter-form" class="mb-4 flex flex-col md:flex-row md:space-x-4 rounded-lg shadow mb-4">
                <input type="hidden" name="page" value="woocommerce-stock-editor-enhanced">
                <div class="flex-1 mb-2 md:mb-0">
                    <label for="category-filter" class="block font-medium mb-1"><?php esc_html_e( 'Category', 'woocommerce-stock-editor-enhanced' ); ?></label>
                    <select name="category" id="category-filter" class="block w-full rounded-lg border-gray-300 p-2">
                        <option value=""><?php esc_html_e( 'Select Category', 'woocommerce-stock-editor-enhanced' ); ?></option>
                        <?php $this->render_category_options( $categories ); ?>
                    </select>
                </div>
                <div class="flex-1 mb-2 md:mb-0">
                    <label for="type-filter" class="block font-medium mb-1"><?php esc_html_e( 'Product Type', 'woocommerce-stock-editor-enhanced' ); ?></label>
                    <select name="type" id="type-filter" class="block w-full rounded-lg border-gray-300 p-2">
                        <option value=""><?php esc_html_e( 'Select Product Type', 'woocommerce-stock-editor-enhanced' ); ?></option>
                        <?php foreach ( $product_types as $type ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( ucfirst( $type ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1 mb-2 md:mb-0">
                    <label for="min-price-filter" class="block font-medium mb-1"><?php esc_html_e( 'Min Price', 'woocommerce-stock-editor-enhanced' ); ?></label>
                    <input type="number" step="0.01" name="min_price" id="min-price-filter" class="block w-full rounded-lg border-gray-300 p-2" placeholder="<?php esc_attr_e( 'Min Price', 'woocommerce-stock-editor-enhanced' ); ?>">
                </div>
                <div class="flex-1 mb-2 md:mb-0">
                    <label for="max-price-filter" class="block font-medium mb-1"><?php esc_html_e( 'Max Price', 'woocommerce-stock-editor-enhanced' ); ?></label>
                    <input type="number" step="0.01" name="max_price" id="max-price-filter" class="block w-full rounded-lg border-gray-300 p-2" placeholder="<?php esc_attr_e( 'Max Price', 'woocommerce-stock-editor-enhanced' ); ?>">
                </div>

            </form>

            <!-- Bulk Update Section Before Pagination & Table -->
            <div class="mt-4 flex items-center space-x-4 stock-info flex items-center bg-white dark:bg-gray-800 p-4 rounded-lg shadow mb-4" id="bulk-edit-container">
                <select id="bulk-field" class="rounded-lg border-gray-300 p-2" <?php echo $license_required ? 'disabled' : ''; ?>>
                    <option value="stock_quantity"><?php esc_html_e( 'Stock Quantity', 'woocommerce-stock-editor-enhanced' ); ?></option>
                    <option value="regular_price"><?php esc_html_e( 'Regular Price', 'woocommerce-stock-editor-enhanced' ); ?></option>
                    <option value="sale_price"><?php esc_html_e( 'Sale Price', 'woocommerce-stock-editor-enhanced' ); ?></option>
                </select>

                <!-- New: bulk operation type -->
                <select id="bulk-operation" class="rounded-lg border-gray-300 p-2" <?php echo $license_required ? 'disabled' : ''; ?>>
                    <option value="set"><?php esc_html_e( 'Set to Value', 'woocommerce-stock-editor-enhanced' ); ?></option>
                    <option value="increase"><?php esc_html_e( 'Increase by Value', 'woocommerce-stock-editor-enhanced' ); ?></option>
                    <option value="decrease"><?php esc_html_e( 'Decrease by Value', 'woocommerce-stock-editor-enhanced' ); ?></option>
                    <option value="increase_percent"><?php esc_html_e( 'Increase by %', 'woocommerce-stock-editor-enhanced' ); ?></option>
                    <option value="decrease_percent"><?php esc_html_e( 'Decrease by %', 'woocommerce-stock-editor-enhanced' ); ?></option>
                </select>

                <input type="number" step="0.01" id="bulk-value" class="rounded-lg border-gray-300 p-2" placeholder="<?php esc_attr_e( 'Value', 'woocommerce-stock-editor-enhanced' ); ?>" <?php echo $license_required ? 'disabled' : ''; ?>>

                <button type="button" id="bulk-update-button" class="bg-green-900 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300" <?php echo $license_required ? 'disabled' : ''; ?>>
                    <?php esc_html_e( 'Bulk Update', 'woocommerce-stock-editor-enhanced' ); ?>
                </button>
            </div>

            <!-- Product Table -->
            <table id="stock-editor-table" class="min-w-full bg-white dark:bg-gray-800 text-sm table-striped mt-4 rounded-lg">
                <thead>
                    <tr>
                        <th class="p-2">
                            <input type="checkbox" id="select-all-products">
                        </th>
                        <th class="p-2 text-left font-semibold sortable" data-orderby="title"><?php esc_html_e( 'Product Name', 'woocommerce-stock-editor-enhanced' ); ?></th>
                        <th class="p-2 text-left font-semibold"><?php esc_html_e( 'Type', 'woocommerce-stock-editor-enhanced' ); ?></th>
                        <th class="p-2 text-left font-semibold"><?php esc_html_e( 'Category', 'woocommerce-stock-editor-enhanced' ); ?></th>
                        <th class="p-2 text-left font-semibold sortable" data-orderby="price"><?php esc_html_e( 'Price', 'woocommerce-stock-editor-enhanced' ); ?></th>
                        <th class="p-2 text-left font-semibold"><?php esc_html_e( 'Sale Price', 'woocommerce-stock-editor-enhanced' ); ?></th>
                        <th class="p-2 text-left font-semibold sortable" data-orderby="total_sales"><?php esc_html_e( 'Sales', 'woocommerce-stock-editor-enhanced' ); ?></th>
                        <th class="p-2 text-left font-semibold sortable" data-orderby="stock_quantity"><?php esc_html_e( 'Stock Quantity', 'woocommerce-stock-editor-enhanced' ); ?></th>
                        <th class="p-2 text-left font-semibold"><?php esc_html_e( 'Stock Status', 'woocommerce-stock-editor-enhanced' ); ?></th>
                        <th class="p-2 text-left font-semibold"><?php esc_html_e( 'Manage Stock', 'woocommerce-stock-editor-enhanced' ); ?></th>
                        <th class="p-2 text-left font-semibold"><?php esc_html_e( '', 'woocommerce-stock-editor-enhanced' ); ?></th>
                    </tr>
                </thead>
                <tbody id="product-table-body">
                    <!-- Product rows injected by AJAX -->
                </tbody>
            </table>

            <div id="pagination" class="mt-4"></div>

            <div id="variation-modal" class="modal">
                <div class="modal-content" id="variation-modal-content">
                    <span class="close-modal">&times;</span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render category options recursively.
     *
     * @param array  $categories Categories to render.
     * @param string $prefix     Prefix for nested categories.
     * @param int    $level      Current depth level.
     */
    private function render_category_options( $categories, $prefix = '', $level = 0 ) {
        foreach ( $categories as $category ) {
            $count    = $category->count; // Product count.
            $padding  = str_repeat( '&nbsp;', $level * 4 ); // Indentation for subcategories.
            echo '<option value="' . esc_attr( $category->slug ) . '">' . $prefix . $padding . esc_html( $category->name ) . ' (' . esc_html( $count ) . ')</option>';

            // Get child categories.
            $child_categories = get_terms(
                [
                    'taxonomy'   => 'product_cat',
                    'hide_empty' => false,
                    'orderby'    => 'name',
                    'parent'     => $category->term_id,
                ]
            );

            if ( ! empty( $child_categories ) ) {
                $this->render_category_options( $child_categories, $prefix, $level + 1 );
            }
        }
    }

    /**
     * AJAX handler to fetch products.
     */
    public function ajax_fetch_products() {
        check_ajax_referer( 'wse_nonce_action', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'You do not have sufficient permissions to access this page.', 'woocommerce-stock-editor-enhanced' ) );
        }

        $category_filter  = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $type_filter      = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
        $search_filter    = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $min_price        = isset( $_POST['min_price'] ) ? floatval( $_POST['min_price'] ) : '';
        $max_price        = isset( $_POST['max_price'] ) ? floatval( $_POST['max_price'] ) : '';
        $paged            = isset( $_POST['paged'] ) ? max( 1, intval( $_POST['paged'] ) ) : 1;

        $orderby = isset( $_POST['orderby'] ) ? sanitize_text_field( wp_unslash( $_POST['orderby'] ) ) : 'title';
        $order   = isset( $_POST['order'] ) ? sanitize_text_field( wp_unslash( $_POST['order'] ) ) : 'asc';

        $allowed_orderby = [ 'title', 'price', 'stock_quantity', 'total_sales' ];
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'title';
        }

        $wc_orderby_mapping = [
            'title'          => 'name',
            'price'          => 'meta_value_num',
            'stock_quantity' => 'meta_value_num',
            'total_sales'    => 'meta_value_num',
        ];

        $meta_key = '';
        if ( 'price' === $orderby ) {
            $meta_key = '_price';
        } elseif ( 'stock_quantity' === $orderby ) {
            $meta_key = '_stock';
        } elseif ( 'total_sales' === $orderby ) {
            $meta_key = 'total_sales';
        }

        $args = [
            'limit'    => $this->per_page,
            'page'     => $paged,
            'status'   => 'publish',
            'return'   => 'objects',
            'orderby'  => isset( $wc_orderby_mapping[ $orderby ] ) ? $wc_orderby_mapping[ $orderby ] : 'name',
            'order'    => $order,
        ];

        $args['meta_query'] = [ 'relation' => 'AND' ];
        $args['tax_query']  = [ 'relation' => 'AND' ];

        if ( $meta_key ) {
            $args['meta_key'] = $meta_key;
        }

        if ( $search_filter ) {
            $args['s'] = $search_filter;
        }

        if ( $category_filter ) {
            $args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $category_filter,
            ];
        }

        if ( $type_filter ) {
            $args['type'] = $type_filter;
        }

        if ( $min_price !== '' ) {
            $args['meta_query'][] = [
                'key'     => '_price',
                'value'   => $min_price,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            ];
        }

        if ( $max_price !== '' ) {
            $args['meta_query'][] = [
                'key'     => '_price',
                'value'   => $max_price,
                'compare' => '<=',
                'type'    => 'NUMERIC',
            ];
        }

        if ( 'total_sales' === $orderby && empty( $meta_key ) ) {
            $args['meta_key'] = 'total_sales';
        }

        $products = wc_get_products( $args );

        if ( empty( $products ) ) {
            wp_send_json_error( __( 'No products found.', 'woocommerce-stock-editor-enhanced' ) );
        }

        ob_start();
        $this->render_product_rows( $products );
        $products_html = ob_get_clean();

        $total_products_query_args = $args;
        $total_products_query_args['limit']  = -1;
        $total_products_query_args['return'] = 'ids';
        unset( $total_products_query_args['page'] );
        $total_products              = wc_get_products( $total_products_query_args );
        $total_pages                 = ceil( count( $total_products ) / $this->per_page );

        ob_start();
        $this->render_pagination( $total_pages, $paged );
        $pagination_html = ob_get_clean();

        wp_send_json_success(
            [
                'products_html'    => $products_html,
                'pagination_html'  => $pagination_html,
            ]
        );
    }

    /**
     * AJAX handler to get product variations.
     */
    public function ajax_get_variations() {
        check_ajax_referer( 'wse_variation_nonce_action', '_wpnonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'You do not have permission to perform this action.', 'woocommerce-stock-editor-enhanced' ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        if ( ! $product_id ) {
            wp_send_json_error( __( 'Invalid product ID.', 'woocommerce-stock-editor-enhanced' ) );
        }

        $product = wc_get_product( $product_id );

        if ( ! $product || 'variable' !== $product->get_type() ) {
            wp_send_json_error( __( 'Product not found or not a variable product.', 'woocommerce-stock-editor-enhanced' ) );
        }

        $variations = $product->get_children();

        $settings              = get_option( 'wse_settings', [] );
        $low_stock_threshold   = isset( $settings['low_stock_threshold'] ) ? intval( $settings['low_stock_threshold'] ) : 3;
        $medium_stock_threshold = isset( $settings['medium_stock_threshold'] ) ? intval( $settings['medium_stock_threshold'] ) : 7;
        $low_stock_color       = isset( $settings['low_stock_color'] ) ? esc_attr( $settings['low_stock_color'] ) : '#f56565';
        $medium_stock_color    = isset( $settings['medium_stock_color'] ) ? esc_attr( $settings['medium_stock_color'] ) : '#ed8936';
        $high_stock_color      = isset( $settings['high_stock_color'] ) ? esc_attr( $settings['high_stock_color'] ) : '#48bb78';

        ob_start();
        ?>
        <h2 class="text-xl font-bold mb-4"><?php echo esc_html( $product->get_name() ); ?></h2>
        <table class="min-w-full bg-white dark:bg-gray-800 text-sm table-striped">
            <thead>
                <tr class="bg-gray-800 text-white">
                    <th class="p-2 text-left font-semibold"><?php esc_html_e( 'Variation Name', 'woocommerce-stock-editor-enhanced' ); ?></th>
                    <th class="p-2 text-left font-semibold"><?php esc_html_e( 'Price', 'woocommerce-stock-editor-enhanced' ); ?></th>
                    <th class="p-2 text-left font-semibold"><?php esc_html_e( 'Sale Price', 'woocommerce-stock-editor-enhanced' ); ?></th>
                    <th class="p-2 text-left font-semibold"><?php esc_html_e( 'Stock Quantity', 'woocommerce-stock-editor-enhanced' ); ?></th>
                    <th class="p-2 text-left font-semibold"><?php esc_html_e( 'Stock Status', 'woocommerce-stock-editor-enhanced' ); ?></th>
                    <th class="p-2 text-left font-semibold"><?php esc_html_e( 'Manage Stock', 'woocommerce-stock-editor-enhanced' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ( $variations as $variation_id ) {
                    $variation = wc_get_product( $variation_id );
                    if ( ! $variation ) {
                        continue;
                    }
                    $variation_name         = wc_get_formatted_variation( $variation, true );
                    $variation_stock_qty    = $variation->get_stock_quantity();
                    $variation_stock_status = $variation->get_stock_status();
                    $variation_managing_stock = $variation->get_manage_stock() ? 'yes' : 'no';
                    $regular_price          = $variation->get_regular_price();
                    $sale_price             = $variation->get_sale_price();

                    if ( $variation_stock_qty < $low_stock_threshold ) {
                        $stock_indicator_color = $low_stock_color;
                    } elseif ( $variation_stock_qty < $medium_stock_threshold ) {
                        $stock_indicator_color = $medium_stock_color;
                    } else {
                        $stock_indicator_color = $high_stock_color;
                    }
                    ?>
                    <tr data-product-id="<?php echo esc_attr( $variation_id ); ?>">
                        <td class="p-2"><?php echo esc_html( $variation_name ); ?></td>
                        <td class="p-2">
                            <input type="number" step="0.01" data-field="regular_price" value="<?php echo esc_attr( $regular_price ); ?>" class="instant-update block w-full rounded-lg border-gray-300 dark:border-gray-700 p-1">
                        </td>
                        <td class="p-2">
                            <input type="number" step="0.01" data-field="sale_price" value="<?php echo esc_attr( $sale_price ); ?>" class="instant-update block w-full rounded-lg border-gray-300 dark:border-gray-700 p-1">
                        </td>
                        <td class="p-2 flex items-center sales-cell">
                            <span class="stock-indicator inline-block w-3 h-3 rounded-full mr-2" style="background-color: <?php echo esc_attr( $stock_indicator_color ); ?>;"></span>
                            <input type="number" data-field="stock_quantity" value="<?php echo esc_attr( $variation_stock_qty ); ?>" class="instant-update block w-full rounded-lg border-gray-300 dark:border-gray-700 p-1">
                        </td>
                        <td class="p-2">
                            <select data-field="stock_status" class="instant-update block w-full rounded-lg border-gray-300 dark:border-gray-700 p-1">
                                <option value="instock" <?php selected( $variation_stock_status, 'instock' ); ?>><?php esc_html_e( 'In Stock', 'woocommerce-stock-editor-enhanced' ); ?></option>
                                <option value="outofstock" <?php selected( $variation_stock_status, 'outofstock' ); ?>><?php esc_html_e( 'Out of Stock', 'woocommerce-stock-editor-enhanced' ); ?></option>
                            </select>
                        </td>
                        <td class="p-2">
                            <input type="checkbox" data-field="manage_stock_checkbox" class="instant-update" <?php checked( $variation_managing_stock, 'yes' ); ?>>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
        <?php
        $html = ob_get_clean();

        wp_send_json_success( [ 'html' => $html ] );
    }

    private function log_change( $product_id, $field, $old_value, $new_value ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wse_change_log';
        $current_user_id = get_current_user_id();

        $wpdb->insert(
            $table_name,
            [
                'product_id' => $product_id,
                'field' => $field,
                'old_value' => (string)$old_value,
                'new_value' => (string)$new_value,
                'user_id' => $current_user_id,
                'change_time' => current_time('mysql')
            ],
            [ '%d', '%s', '%s', '%s', '%d', '%s' ]
        );
    }

    /**
     * AJAX handler to update a single product (instant update).
     */
    public function ajax_update_product() {
        check_ajax_referer( 'wse_nonce_action', 'nonce' );
    
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'You do not have permission to perform this action.', 'woocommerce-stock-editor-enhanced' ) );
        }
    
        // Lisans kontrolü
        if ( $this->is_license_required() ) {
            wp_send_json_error( __( 'License required after 20 changes. Please enter a valid license key.', 'woocommerce-stock-editor-enhanced' ) );
        }
    
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $field      = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : '';
        $value      = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
    
        if ( ! $product_id || ! $field ) {
            wp_send_json_error( __( 'Invalid data.', 'woocommerce-stock-editor-enhanced' ) );
        }
    
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( __( 'Product not found.', 'woocommerce-stock-editor-enhanced' ) );
        }
    
        try {
            // Gelen alana göre güncelle
            switch ( $field ) {
                case 'stock_quantity':
                    $product->set_stock_quantity( intval( $value ) );
                    break;
                case 'stock_status':
                    $valid_statuses = [ 'instock', 'outofstock' ];
                    if ( in_array( $value, $valid_statuses, true ) ) {
                        $product->set_stock_status( $value );
                    } else {
                        wp_send_json_error( __( 'Invalid stock status.', 'woocommerce-stock-editor-enhanced' ) );
                    }
                    break;
                case 'manage_stock':
                case 'manage_stock_checkbox':
                    $manage_stock = ( 'yes' === $value );
                    $product->set_manage_stock( $manage_stock );
                    break;
                case 'regular_price':
                    $product->set_regular_price( floatval( $value ) );
                    break;
                case 'sale_price':
                    $product->set_sale_price( floatval( $value ) );
                    break;
                case 'name':
                    $product->set_name( $value );
                    break;
                default:
                    wp_send_json_error( __( 'Invalid field.', 'woocommerce-stock-editor-enhanced' ) );
            }
    
            $product->save();

            // Güncelleme sayısı arttır
            $current_count = get_option( 'wse_change_count', 0 );
            update_option( 'wse_change_count', $current_count + 1 );
    
            // Yeni stok rengi hesapla
            $settings              = get_option( 'wse_settings', [] );
            $low_stock_threshold   = isset( $settings['low_stock_threshold'] ) ? (int) $settings['low_stock_threshold'] : 3;
            $medium_stock_threshold = isset( $settings['medium_stock_threshold'] ) ? (int) $settings['medium_stock_threshold'] : 7;
            $low_stock_color       = isset( $settings['low_stock_color'] ) ? $settings['low_stock_color'] : '#f56565';
            $medium_stock_color    = isset( $settings['medium_stock_color'] ) ? $settings['medium_stock_color'] : '#ed8936';
            $high_stock_color      = isset( $settings['high_stock_color'] ) ? $settings['high_stock_color'] : '#48bb78';
    
            $stock_qty = $product->get_stock_quantity();
            if ( $stock_qty < $low_stock_threshold ) {
                $stock_indicator_color = $low_stock_color;
            } elseif ( $stock_qty <= $medium_stock_threshold ) {
                $stock_indicator_color = $medium_stock_color;
            } else {
                $stock_indicator_color = $high_stock_color;
            }

            // Eğer güncellenen ürün varyasyon ise parent'ın toplam stokunu hesapla
            $parent_data = [];
            if ( $product->is_type('variation') ) {
                $parent_id = $product->get_parent_id();
                if ( $parent_id ) {
                    $parent_product = wc_get_product( $parent_id );
                    $variation_ids  = $parent_product->get_children();
                    $total_stock_quantity = 0;
                    foreach ( $variation_ids as $vid ) {
                        $var_obj = wc_get_product( $vid );
                        if ( $var_obj && $var_obj->get_stock_quantity() !== null ) {
                            $total_stock_quantity += $var_obj->get_stock_quantity();
                        }
                    }

                    // Parent ürün stok rengi
                    if ( $total_stock_quantity < $low_stock_threshold ) {
                        $parent_stock_indicator_color = $low_stock_color;
                    } elseif ( $total_stock_quantity <= $medium_stock_threshold ) {
                        $parent_stock_indicator_color = $medium_stock_color;
                    } else {
                        $parent_stock_indicator_color = $high_stock_color;
                    }

                    $parent_data = [
                        'parent_id'                  => $parent_id,
                        'parent_stock_total'         => $total_stock_quantity,
                        'parent_stock_indicator_color' => $parent_stock_indicator_color,
                    ];
                }
            }
    
            // Sunucu tarafından geri döneceğimiz veriler
            $response_data = [
                'product_id'            => $product_id,
                'field'                 => $field,
                'stock_quantity'        => $product->get_stock_quantity(),
                'regular_price'         => $product->get_regular_price(),
                'sale_price'            => $product->get_sale_price(),
                'product_name'          => $product->get_name(),
                'stock_status'          => $product->get_stock_status(),
                'stock_indicator_color' => $stock_indicator_color,
            ];

            if ( ! empty( $parent_data ) ) {
                $response_data = array_merge( $response_data, $parent_data );
            }

            wp_send_json_success( $response_data );
    
        } catch ( Exception $e ) {
            wp_send_json_error(
                __( 'An error occurred while updating the product: ', 'woocommerce-stock-editor-enhanced' ) . esc_html( $e->getMessage() )
            );
        }
    }

    /**
     * Render pagination links.
     *
     * @param int $total_pages Total number of pages.
     * @param int $current_page Current page number.
     */
    private function render_pagination( $total_pages, $current_page ) {
        if ( $total_pages > 1 ) {
            echo '<div class="wse-pagination mt-4 flex space-x-2">';
            for ( $i = 1; $i <= $total_pages; $i++ ) {
                $class = ( $i === $current_page ) ? 'current-page bg-blue-500 text-white px-3 py-1 rounded' : 'bg-gray-200 text-gray-700 px-3 py-1 rounded hover:bg-gray-300';
                echo '<a href="#" class="' . esc_attr( $class ) . '" data-page="' . esc_attr( $i ) . '">' . esc_html( $i ) . '</a>';
            }
            echo '</div>';
        }
    }

    /**
     * Render product rows in the table.
     *
     * @param array $products Array of WC_Product objects.
     */
    public function render_product_rows( $products ) {
        $settings              = get_option( 'wse_settings' );
        $low_stock_threshold   = isset( $settings['low_stock_threshold'] ) ? $settings['low_stock_threshold'] : 3;
        $medium_stock_threshold = isset( $settings['medium_stock_threshold'] ) ? $settings['medium_stock_threshold'] : 7;
        $low_stock_color       = isset( $settings['low_stock_color'] ) ? esc_attr( $settings['low_stock_color'] ) : '#f56565';
        $medium_stock_color    = isset( $settings['medium_stock_color'] ) ? esc_attr( $settings['medium_stock_color'] ) : '#ed8936';
        $high_stock_color      = isset( $settings['high_stock_color'] ) ? esc_attr( $settings['high_stock_color'] ) : '#48bb78';

        foreach ( $products as $product ) {
            $product_id      = $product->get_id();
            $product_type    = $product->get_type();
            $product_name    = $product->get_name();
            $product_categories = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'names' ] );
            $category_names  = implode( ', ', $product_categories );
            $total_sales     = $product->get_total_sales();

            // Product creation and update information.
            $product_post     = get_post( $product_id );
            $creation_date    = get_the_date( 'Y-m-d H:i:s', $product_id );
            $modified_date    = get_the_modified_date( 'Y-m-d H:i:s', $product_id );
            // Last editor user.
            $last_editor_id   = get_post_meta( $product_id, '_edit_last', true );
            if ( ! $last_editor_id ) {
                $last_editor_id = $product_post ? $product_post->post_author : 0;
            }
            $modified_user       = $last_editor_id ? get_user_by( 'id', $last_editor_id ) : null;
            $modified_username   = $modified_user ? $modified_user->display_name : __( 'Unknown', 'woocommerce-stock-editor-enhanced' );

            if ( 'variable' === $product_type ) {
                // For variable products, sum all variation stocks.
                $variations             = $product->get_children();
                $total_stock_quantity   = 0;
                $total_sales_variations = 0;
                foreach ( $variations as $variation_id ) {
                    $variation = wc_get_product( $variation_id );
                    if ( $variation && null !== $variation->get_stock_quantity() ) {
                        $total_stock_quantity += $variation->get_stock_quantity();
                    }
                    if ( $variation ) {
                        $total_sales_variations += $variation->get_total_sales();
                    }
                }
                $total_sales = $total_sales_variations;

                if ( $total_stock_quantity < $low_stock_threshold ) {
                    $stock_indicator_color = $low_stock_color;
                } elseif ( $total_stock_quantity <= $medium_stock_threshold ) {
                    $stock_indicator_color = $medium_stock_color;
                } else {
                    $stock_indicator_color = $high_stock_color;
                }
                ?>
                <tr class="product-row cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-product-type="<?php echo esc_attr( $product_type ); ?>">
                    <td class="p-2">
                        <input type="checkbox" class="product-select-checkbox" value="<?php echo esc_attr( $product_id ); ?>">
                    </td>
                    <td class="p-2 flex items-center relative">
                        <span class="mr-4" title="<?php esc_attr_e( 'Variable Product', 'woocommerce-stock-editor-enhanced' ); ?>">
                            <svg class="h-5 w-5 text-gray-600 dark:text-gray-300 product-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4-8-4m16 0v8m-16-8v8m8 4l8-4m-8 4l-8-4"></path>
                            </svg>
                        </span>
                        <input
                            type="text"
                            class="product-name-input block w-full border border-gray-300 dark:border-gray-700 rounded-lg p-1"
                            value="<?php echo esc_attr( $product_name ); ?>"
                            style="width:500px;"
                            data-product-id="<?php echo esc_attr( $product_id ); ?>"
                            title="<?php esc_attr_e( 'Click to edit product name', 'woocommerce-stock-editor-enhanced' ); ?>"
                        />
                    </td>
                    <td class="p-2"><?php echo esc_html( ucfirst( $product_type ) ); ?></td>
                    <td class="p-2"><?php echo esc_html( $category_names ); ?></td>
                    <td class="p-2">-</td>
                    <td class="p-2">-</td>
                    <td class="p-2">
                        <div class="sales-cell-2"><?php echo esc_html( $total_sales ); ?></div>
                    </td>
                    <td class="p-2 flex items-center sales-cell">
                        <span class="inline-block w-3 h-3 rounded-full mr-2" style="background-color: <?php echo esc_attr( $stock_indicator_color ); ?>;"></span>
                        <span class="parent-stock-qty"><?php echo esc_html( $total_stock_quantity ); ?></span>
                    </td>
                    <td class="p-2">Variable</td>
                    <td class="p-2">-</td>
                    <td class="p-2">
                        <div class="relative">
                            <span class="info-icon cursor-pointer text-gray-600 dark:text-gray-300" title="<?php esc_attr_e( 'More Info', 'woocommerce-stock-editor-enhanced' ); ?>">
                                <svg class="product-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm-.696-3.534c.63 0 1.332-.288 2.196-1.458l.911-1.22a.334.334 0 0 0-.074-.472.38.38 0 0 0-.505.06l-1.475 1.679a.241.241 0 0 1-.279.061.211.211 0 0 1-.12-.244l1.858-7.446a.499.499 0 0 0-.575-.613l-3.35.613a.35.35 0 0 0-.276.258l-.086.334a.25.25 0 0 0 .243.312h1.73l-1.476 5.922c-.054.234-.144.63-.144.918 0 .666.396 1.296 1.422 1.296zm1.83-10.536c.702 0 1.242-.414 1.386-1.044.036-.144.054-.306.054-.414 0-.504-.396-.972-1.134-.972-.702 0-1.242.414-1.386 1.044a1.868 1.868 0 0 0-.054.414c0 .504.396.972 1.134.972z" fill="#000000"/>
                                </svg>
                            </span>
                            <div class="absolute top-full right-0 mt-2 w-64 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg p-4 z-50 hidden info-hover-content">
                                <div class="text-xs text-gray-600 dark:text-gray-300">
                                    <p><?php printf( __( '<strong>Creation date:</strong><br /> %s', 'woocommerce-stock-editor-enhanced' ), esc_html( $creation_date ) ); ?></p>
                                    <p><?php printf( __( '<strong>Updated Date:</strong><br /> %s', 'woocommerce-stock-editor-enhanced' ), esc_html( $modified_date ) ); ?></p>
                                    <p><?php printf( __( '<strong>By:</strong><br /> %s', 'woocommerce-stock-editor-enhanced' ), esc_html( $modified_username ) ); ?></p>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php
            } else {
                // Simple product.
                $stock_quantity         = $product->get_stock_quantity();
                $stock_status           = $product->get_stock_status();
                $managing_stock         = $product->get_manage_stock() ? 'yes' : 'no';
                $regular_price          = $product->get_regular_price();
                $sale_price             = $product->get_sale_price();
                $total_sales            = $product->get_total_sales();

                if ( $stock_quantity < $low_stock_threshold ) {
                    $stock_indicator_color = $low_stock_color;
                } elseif ( $stock_quantity <= $medium_stock_threshold ) {
                    $stock_indicator_color = $medium_stock_color;
                } else {
                    $stock_indicator_color = $high_stock_color;
                }
                ?>
                <tr class="product-row cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-product-type="<?php echo esc_attr( $product_type ); ?>">
                    <td class="p-2">
                        <input type="checkbox" class="product-select-checkbox" value="<?php echo esc_attr( $product_id ); ?>">
                    </td>
                    <td class="p-2 flex items-center relative">
                        <span class="mr-4" title="<?php esc_attr_e( 'Simple Product', 'woocommerce-stock-editor-enhanced' ); ?>">
                            <svg class="h-5 w-5 text-gray-600 dark:text-gray-300 product-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M5 6h14l1 14H4L5 6z"></path>
                            </svg>
                        </span>
                        <input
                            type="text"
                            class="product-name-input block w-full border border-gray-300 dark:border-gray-700 rounded-lg p-1"
                            value="<?php echo esc_attr( $product_name ); ?>"
                            style="width:500px;"
                            data-product-id="<?php echo esc_attr( $product_id ); ?>"
                            title="<?php esc_attr_e( 'Click to edit product name', 'woocommerce-stock-editor-enhanced' ); ?>"
                        />
                    </td>
                    <td class="p-2"><?php echo esc_html( ucfirst( $product_type ) ); ?></td>
                    <td class="p-2"><?php echo esc_html( $category_names ); ?></td>
                    <td class="p-2">
                        <input type="number" step="0.01" data-field="regular_price" value="<?php echo esc_attr( $regular_price ); ?>" class="instant-update block w-full rounded-lg border-gray-300 dark:border-gray-700 p-1">
                    </td>
                    <td class="p-2">
                        <input type="number" step="0.01" data-field="sale_price" value="<?php echo esc_attr( $sale_price ); ?>" class="instant-update block w-full rounded-lg border-gray-300 dark:border-gray-700 p-1">
                    </td>
                    <td class="p-2">
                        <div class="sales-cell-2"><?php echo esc_html( $total_sales ); ?></div>
                    </td>
                    <td class="p-2 flex items-center sales-cell">
                        <span class="inline-block w-3 h-3 rounded-full mr-2" style="background-color: <?php echo esc_attr( $stock_indicator_color ); ?>;"></span>
                        <input type="number" data-field="stock_quantity" value="<?php echo esc_attr( $stock_quantity ); ?>" class="instant-update block w-full rounded-lg border-gray-300 dark:border-gray-700 p-1">
                    </td>
                    <td class="p-2">
                        <select data-field="stock_status" class="instant-update block w-full rounded-lg border-gray-300 dark:border-gray-700 p-1">
                            <option value="instock" <?php selected( $stock_status, 'instock' ); ?>><?php esc_html_e( 'In Stock', 'woocommerce-stock-editor-enhanced' ); ?></option>
                            <option value="outofstock" <?php selected( $stock_status, 'outofstock' ); ?>><?php esc_html_e( 'Out of Stock', 'woocommerce-stock-editor-enhanced' ); ?></option>
                        </select>
                    </td>
                    <td class="p-2">
                        <input type="checkbox" data-field="manage_stock_checkbox" class="instant-update" <?php checked( $managing_stock, 'yes' ); ?>>
                    </td>
                    <td class="p-2">
                        <div class="relative">
                            <span class="info-icon cursor-pointer text-gray-600 dark:text-gray-300" title="<?php esc_attr_e( 'More Info', 'woocommerce-stock-editor-enhanced' ); ?>">
                                <svg class="product-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm-.696-3.534c.63 0 1.332-.288 2.196-1.458l.911-1.22a.334.334 0 0 0-.074-.472.38.38 0 0 0-.505.06l-1.475 1.679a.241.241 0 0 1-.279.061.211.211 0 0 1-.12-.244l1.858-7.446a.499.499 0 0 0-.575-.613l-3.35.613a.35.35 0 0 0-.276.258l-.086.334a.25.25 0 0 0 .243.312h1.73l-1.476 5.922c-.054.234-.144.63-.144.918 0 .666.396 1.296 1.422 1.296zm1.83-10.536c.702 0 1.242-.414 1.386-1.044.036-.144.054-.306.054-.414 0-.504-.396-.972-1.134-.972-.702 0-1.242.414-1.386 1.044a1.868 1.868 0 0 0-.054.414c0 .504.396.972 1.134.972z" fill="#000000"/>
                                </svg>
                            </span>
                            <div class="absolute top-full right-0 mt-2 w-64 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg p-4 z-50 hidden info-hover-content">
                                <div class="text-xs text-gray-600 dark:text-gray-300">
                                    <p><?php printf( __( '<strong>Creation date:</strong><br /> %s', 'woocommerce-stock-editor-enhanced' ), esc_html( $creation_date ) ); ?></p>
                                    <p><?php printf( __( '<strong>Updated Date:</strong><br /> %s', 'woocommerce-stock-editor-enhanced' ), esc_html( $modified_date ) ); ?></p>
                                    <p><?php printf( __( '<strong>By:</strong><br /> %s', 'woocommerce-stock-editor-enhanced' ), esc_html( $modified_username ) ); ?></p>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php
            }
        }
    }

    /**
     * AJAX handler to validate license.
     */
    public function ajax_validate_license() {
        check_ajax_referer( 'wse_license_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have sufficient permissions.', 'woocommerce-stock-editor-enhanced' ) ] );
        }

        $settings    = get_option( 'wse_settings', [] );
        $license_key = isset( $settings['license_key'] ) ? sanitize_text_field( wp_unslash( $settings['license_key'] ) ) : '';

        if ( empty( $license_key ) ) {
            wp_send_json_error( [ 'message' => __( 'No license key provided.', 'woocommerce-stock-editor-enhanced' ) ] );
        }

        // Validate the license
        $response = $this->validate_license_key( $license_key );

        if ( isset( $response['valid'] ) && true === $response['valid'] ) {
            // Update license status in settings
            $settings['license_status'] = 'Valid';
            update_option( 'wse_settings', $settings );

            wp_send_json_success( [ 'message' => $response['message'] ] );
        } else {
            // Update license status in settings
            $settings['license_status'] = 'Invalid';
            update_option( 'wse_settings', $settings );

            wp_send_json_error( [ 'message' => $response['message'] ] );
        }
    }

    /**
     * Validate the license key via external API.
     *
     * @param string $license_key The license key.
     * @return array Validation result.
     */
    private function validate_license_key( $license_key ) {
        $product_id = '4092'; // Replace with your actual product ID.
        $response   = wp_remote_post(
            'https://codeon.ch/wp-json/codeon-license/v1/validate',
            [
                'body' => [
                    'license_key' => $license_key,
                    'product_id'  => $product_id,
                ],
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            // API connection failed.
            return [
                'valid'   => false,
                'message' => __( 'Could not connect to license server.', 'woocommerce-stock-editor-enhanced' ),
            ];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $data['valid'] ) && true === $data['valid'] ) {
            return [
                'valid'   => true,
                'message' => isset( $data['message'] ) ? $data['message'] : __( 'License validated successfully.', 'woocommerce-stock-editor-enhanced' ),
            ];
        } else {
            // Handle error messages from API.
            return [
                'valid'   => false,
                'message' => isset( $data['message'] ) ? $data['message'] : __( 'Invalid license key.', 'woocommerce-stock-editor-enhanced' ),
            ];
        }
    }

    /**
     * AJAX handler to perform bulk updates on products.
     */
    public function ajax_bulk_update_products() {
        check_ajax_referer( 'wse_nonce_action', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'You do not have permission to perform this action.', 'woocommerce-stock-editor-enhanced' ) );
        }

        // Check if license is required.
        if ( $this->is_license_required() ) {
            wp_send_json_error( __( 'License required after 20 changes. Please enter a valid license key.', 'woocommerce-stock-editor-enhanced' ) );
        }

        $product_ids = isset( $_POST['product_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['product_ids'] ) ) : [];
        $field       = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : '';
        $value       = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
        $operation   = isset( $_POST['operation'] ) ? sanitize_text_field( wp_unslash( $_POST['operation'] ) ) : 'set';

        if ( empty( $product_ids ) || empty( $field ) ) {
            wp_send_json_error( __( 'Invalid data.', 'woocommerce-stock-editor-enhanced' ) );
        }

        $skipped_products = [];
        $updated_count    = 0;

        foreach ( $product_ids as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                $skipped_products[] = $product_id;
                continue;
            }

            // Skip variable products in this loop
            if ( 'variable' === $product->get_type() ) {
                $skipped_products[] = $product_id;
                continue;
            }

            try {
                // Current value
                $current_value = null;
                if ( in_array( $field, [ 'regular_price', 'sale_price' ], true ) ) {
                    $current_value = (float) $product->{"get_{$field}"}();
                } elseif ( 'stock_quantity' === $field ) {
                    $current_value = (float) $product->get_stock_quantity();
                }

                $new_value = null;
                $value_float = (float) $value;

                switch ($operation) {
                    case 'set':
                        $new_value = $value_float;
                        break;
                    case 'increase':
                        if ( null === $current_value ) {
                            $skipped_products[] = $product_id;
                            continue 2;
                        }
                        $new_value = $current_value + $value_float;
                        break;
                    case 'decrease':
                        if ( null === $current_value ) {
                            $skipped_products[] = $product_id;
                            continue 2;
                        }
                        $new_value = $current_value - $value_float;
                        break;
                    case 'increase_percent':
                        if ( null === $current_value ) {
                            $skipped_products[] = $product_id;
                            continue 2;
                        }
                        $new_value = $current_value * (1 + ($value_float / 100));
                        break;
                    case 'decrease_percent':
                        if ( null === $current_value ) {
                            $skipped_products[] = $product_id;
                            continue 2;
                        }
                        $new_value = $current_value * (1 - ($value_float / 100));
                        break;
                    default:
                        $skipped_products[] = $product_id;
                        continue 2;
                }

                // Apply
                switch ( $field ) {
                    case 'stock_quantity':
                        $product->set_stock_quantity( (int)$new_value );
                        break;
                    case 'regular_price':
                        $product->set_regular_price( (float)$new_value );
                        break;
                    case 'sale_price':
                        $product->set_sale_price( (float)$new_value );
                        break;
                    default:
                        $skipped_products[] = $product_id;
                        continue 2;
                }

                $product->save();
                $updated_count++;
            } catch ( Exception $e ) {
                $skipped_products[] = $product_id;
                continue;
            }
        }

        if ( $updated_count > 0 ) {
            $current_count = get_option( 'wse_change_count', 0 );
            $new_count     = $current_count + $updated_count;
            update_option( 'wse_change_count', $new_count );

            // License status
            $settings    = get_option( 'wse_settings', [] );
            $license_key = isset( $settings['license_key'] ) ? $settings['license_key'] : '';
            $license_valid = ( ! empty( $license_key ) && $this->is_license_valid( $license_key ) );

            // If limit is reached and license not valid
            if ( $new_count >= 20 && ! $license_valid ) {
                $message = __( 'You have reached the maximum number of updates (20) or your license has expired. To continue accessing all features, please enter a valid license key. If you do not have a license key, please purchase one.', 'woocommerce-stock-editor-enhanced' );
                wp_send_json_error( $message );
            }

            if ( empty( $skipped_products ) ) {
                wp_send_json_success( __( 'Bulk update completed successfully.', 'woocommerce-stock-editor-enhanced' ) );
            } else {
                $message = sprintf(
                    __( 'Bulk update completed. %1$d products were updated. %2$d products were skipped.', 'woocommerce-stock-editor-enhanced' ),
                    $updated_count,
                    count( $skipped_products )
                );
                wp_send_json_success( $message );
            }
        } else {
            wp_send_json_error( __( 'Bulk update failed.', 'woocommerce-stock-editor-enhanced' ) );
        }
    }

    /**
     * Display admin notices.
     */
    public function admin_notices() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( $this->is_license_required() ) {
            // Pass the notice message to JavaScript
            add_action( 'admin_footer', [ $this, 'enqueue_admin_notice_script' ] );
        }
    }

    public function enqueue_admin_notice_script() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                toastr.warning('<?php echo esc_js( __( 'You have reached the maximum number of updates (20) or your license has expired. To continue accessing all features, please enter a valid license key. If you do not have a license key, please purchase one.', 'woocommerce-stock-editor-enhanced' ) ); ?>');
            });
        </script>
        <?php
    }

    /**
     * Check if license is valid.
     *
     * @param string $license_key The license key.
     * @return bool
     */
    private function is_license_valid( $license_key ) {
        $transient_key = 'wse_license_status_' . md5( $license_key );
        $cached_status = get_transient( $transient_key );

        if ( false !== $cached_status ) {
            return $cached_status;
        }

        $product_id = '4092'; // Ensure this matches your actual product ID.
        $response   = wp_remote_post(
            'https://codeon.ch/wp-json/codeon-license/v1/validate',
            [
                'body' => [
                    'license_key' => $license_key,
                    'product_id'  => $product_id,
                ],
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            error_log( 'License validation failed: ' . $response->get_error_message() );
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $data['valid'] ) && true === $data['valid'] ) {
            set_transient( $transient_key, true, HOUR_IN_SECONDS ); // Cache for 1 hour.
            return true;
        } else {
            set_transient( $transient_key, false, HOUR_IN_SECONDS ); // Cache for 1 hour.
            return false;
        }
    }

    /**
     * Determine if license is required.
     *
     * @return bool
     */
    private function is_license_required() {
        // Check if license is required based on change count.
        $settings      = get_option( 'wse_settings', [] );
        $license_key   = isset( $settings['license_key'] ) ? $settings['license_key'] : '';

        // If license is not provided or invalid, and change count >= 20.
        if ( ( empty( $license_key ) || ! $this->is_license_valid( $license_key ) ) ) {
            $change_count = get_option( 'wse_change_count', 0 );
            if ( $change_count >= 20 ) {
                return true;
            }
        }

        return false;
    }
}

// Initialize the plugin.
new WC_Stock_Editor_Enhanced();

endif;
