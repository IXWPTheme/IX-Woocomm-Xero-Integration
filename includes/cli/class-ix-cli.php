<?php
/**
 * IX Woo Xero Integration - CLI Class
 * 
 * Handles all WP-CLI commands for the plugin including:
 * - Manual synchronization
 * - Status checks
 * - Debugging tools
 */

namespace IX_Woo_Xero\CLI;

use IX_Woo_Xero\API\Xero_API;
use IX_Woo_Xero\Sync\Product_Sync;
use IX_Woo_Xero\Sync\Invoice_Sync;
use IX_Woo_Xero\Sync\Customer_Sync;
use IX_Woo_Xero\Sync\Subscription_Sync;
use IX_Woo_Xero\Utilities\Logger;

if (!defined('ABSPATH')) {
    exit;
}

// Only load if WP_CLI is available
if (defined('WP_CLI') && WP_CLI) {

    class IX_Woo_Xero_CLI {

        /**
         * Sync all products to Xero
         *
         * ## OPTIONS
         *
         * [--force]
         * : Force sync even if items are already synced
         *
         * ## EXAMPLES
         * wp ix-xero sync-products
         * wp ix-xero sync-products --force
         *
         * @param array $args
         * @param array $assoc_args
         */
        public function sync_products($args, $assoc_args) {
            $force = isset($assoc_args['force']) ? (bool) $assoc_args['force'] : false;
            
            if (!Xero_API::instance()->is_connected()) {
                \WP_CLI::error(__('Not connected to Xero. Please configure API credentials first.', 'ix-woo-xero'));
                return;
            }

            $product_sync = Product_Sync::instance();
            $products = $this->get_products_to_sync($force);

            if (empty($products)) {
                \WP_CLI::success(__('No products to sync.', 'ix-woo-xero'));
                return;
            }

            $progress = \WP_CLI\Utils\make_progress_bar(
                __('Syncing products to Xero', 'ix-woo-xero'),
                count($products)
            );

            $success_count = 0;
            $error_count = 0;

            foreach ($products as $product) {
                try {
                    $result = $product_sync->sync_product_to_xero($product->ID, $product, true);
                    
                    if ($result) {
                        $success_count++;
                    } else {
                        $error_count++;
                        Logger::error("Failed to sync product ID: {$product->ID}");
                    }
                } catch (\Exception $e) {
                    $error_count++;
                    Logger::error("Product sync error (ID: {$product->ID}): " . $e->getMessage());
                }

                $progress->tick();
            }

            $progress->finish();

            \WP_CLI::success(sprintf(
                __('Synced %d products (%d successful, %d failed)', 'ix-woo-xero'),
                count($products),
                $success_count,
                $error_count
            ));
        }

        /**
         * Sync all orders to Xero
         *
         * ## OPTIONS
         *
         * [--status=<status>]
         * : Only sync orders with specific status (comma-separated)
         * 
         * [--days=<days>]
         * : Only sync orders from the last X days
         *
         * [--force]
         * : Force sync even if orders are already synced
         *
         * ## EXAMPLES
         * wp ix-xero sync-orders
         * wp ix-xero sync-orders --status=processing,completed
         * wp ix-xero sync-orders --days=30
         *
         * @param array $args
         * @param array $assoc_args
         */
        public function sync_orders($args, $assoc_args) {
            $force = isset($assoc_args['force']) ? (bool) $assoc_args['force'] : false;
            $status = isset($assoc_args['status']) ? explode(',', $assoc_args['status']) : ['processing', 'completed'];
            $days = isset($assoc_args['days']) ? (int) $assoc_args['days'] : null;
            
            if (!Xero_API::instance()->is_connected()) {
                \WP_CLI::error(__('Not connected to Xero. Please configure API credentials first.', 'ix-woo-xero'));
                return;
            }

            $invoice_sync = Invoice_Sync::instance();
            $orders = $this->get_orders_to_sync($status, $days, $force);

            if (empty($orders)) {
                \WP_CLI::success(__('No orders to sync.', 'ix-woo-xero'));
                return;
            }

            $progress = \WP_CLI\Utils\make_progress_bar(
                __('Syncing orders to Xero', 'ix-woo-xero'),
                count($orders)
            );

            $success_count = 0;
            $error_count = 0;

            foreach ($orders as $order) {
                try {
                    $result = $invoice_sync->sync_order_to_xero($order->get_id(), '', $order->get_status(), $order);
                    
                    if ($result) {
                        $success_count++;
                    } else {
                        $error_count++;
                        Logger::error("Failed to sync order ID: {$order->get_id()}");
                    }
                } catch (\Exception $e) {
                    $error_count++;
                    Logger::error("Order sync error (ID: {$order->get_id()}): " . $e->getMessage());
                }

                $progress->tick();
            }

            $progress->finish();

            \WP_CLI::success(sprintf(
                __('Synced %d orders (%d successful, %d failed)', 'ix-woo-xero'),
                count($orders),
                $success_count,
                $error_count
            ));
        }

        /**
         * Sync all customers to Xero
         *
         * ## OPTIONS
         *
         * [--role=<role>]
         * : Only sync customers with specific role
         * 
         * [--days=<days>]
         * : Only sync customers from the last X days
         *
         * [--force]
         * : Force sync even if customers are already synced
         *
         * ## EXAMPLES
         * wp ix-xero sync-customers
         * wp ix-xero sync-customers --role=customer
         * wp ix-xero sync-customers --days=30
         *
         * @param array $args
         * @param array $assoc_args
         */
        public function sync_customers($args, $assoc_args) {
            $force = isset($assoc_args['force']) ? (bool) $assoc_args['force'] : false;
            $role = isset($assoc_args['role']) ? $assoc_args['role'] : 'customer';
            $days = isset($assoc_args['days']) ? (int) $assoc_args['days'] : null;
            
            if (!Xero_API::instance()->is_connected()) {
                \WP_CLI::error(__('Not connected to Xero. Please configure API credentials first.', 'ix-woo-xero'));
                return;
            }

            $customer_sync = Customer_Sync::instance();
            $customers = $this->get_customers_to_sync($role, $days, $force);

            if (empty($customers)) {
                \WP_CLI::success(__('No customers to sync.', 'ix-woo-xero'));
                return;
            }

            $progress = \WP_CLI\Utils\make_progress_bar(
                __('Syncing customers to Xero', 'ix-woo-xero'),
                count($customers)
            );

            $success_count = 0;
            $error_count = 0;

            foreach ($customers as $customer_id) {
                try {
                    $result = $customer_sync->sync_customer_to_xero($customer_id);
                    
                    if ($result) {
                        $success_count++;
                    } else {
                        $error_count++;
                        Logger::error("Failed to sync customer ID: {$customer_id}");
                    }
                } catch (\Exception $e) {
                    $error_count++;
                    Logger::error("Customer sync error (ID: {$customer_id}): " . $e->getMessage());
                }

                $progress->tick();
            }

            $progress->finish();

            \WP_CLI::success(sprintf(
                __('Synced %d customers (%d successful, %d failed)', 'ix-woo-xero'),
                count($customers),
                $success_count,
                $error_count
            ));
        }

        /**
         * Sync all subscriptions to Xero
         *
         * ## OPTIONS
         *
         * [--status=<status>]
         * : Only sync subscriptions with specific status
         * 
         * [--days=<days>]
         * : Only sync subscriptions from the last X days
         *
         * [--force]
         * : Force sync even if subscriptions are already synced
         *
         * ## EXAMPLES
         * wp ix-xero sync-subscriptions
         * wp ix-xero sync-subscriptions --status=active
         * wp ix-xero sync-subscriptions --days=30
         *
         * @param array $args
         * @param array $assoc_args
         */
        public function sync_subscriptions($args, $assoc_args) {
            if (!class_exists('WC_Subscriptions')) {
                \WP_CLI::error(__('WooCommerce Subscriptions is not active.', 'ix-woo-xero'));
                return;
            }

            $force = isset($assoc_args['force']) ? (bool) $assoc_args['force'] : false;
            $status = isset($assoc_args['status']) ? $assoc_args['status'] : 'any';
            $days = isset($assoc_args['days']) ? (int) $assoc_args['days'] : null;
            
            if (!Xero_API::instance()->is_connected()) {
                \WP_CLI::error(__('Not connected to Xero. Please configure API credentials first.', 'ix-woo-xero'));
                return;
            }

            $subscription_sync = Subscription_Sync::instance();
            $subscriptions = $this->get_subscriptions_to_sync($status, $days, $force);

            if (empty($subscriptions)) {
                \WP_CLI::success(__('No subscriptions to sync.', 'ix-woo-xero'));
                return;
            }

            $progress = \WP_CLI\Utils\make_progress_bar(
                __('Syncing subscriptions to Xero', 'ix-woo-xero'),
                count($subscriptions)
            );

            $success_count = 0;
            $error_count = 0;

            foreach ($subscriptions as $subscription) {
                try {
                    $result = $subscription_sync->sync_subscription_to_xero($subscription, $subscription->get_status(), '');
                    
                    if ($result) {
                        $success_count++;
                    } else {
                        $error_count++;
                        Logger::error("Failed to sync subscription ID: {$subscription->get_id()}");
                    }
                } catch (\Exception $e) {
                    $error_count++;
                    Logger::error("Subscription sync error (ID: {$subscription->get_id()}): " . $e->getMessage());
                }

                $progress->tick();
            }

            $progress->finish();

            \WP_CLI::success(sprintf(
                __('Synced %d subscriptions (%d successful, %d failed)', 'ix-woo-xero'),
                count($subscriptions),
                $success_count,
                $error_count
            ));
        }

        /**
         * Sync all data to Xero
         *
         * ## OPTIONS
         *
         * [--days=<days>]
         * : Only sync data from the last X days
         *
         * [--force]
         * : Force sync even if items are already synced
         *
         * ## EXAMPLES
         * wp ix-xero sync-all
         * wp ix-xero sync-all --days=30
         *
         * @param array $args
         * @param array $assoc_args
         */
        public function sync_all($args, $assoc_args) {
            $days = isset($assoc_args['days']) ? (int) $assoc_args['days'] : null;
            $force = isset($assoc_args['force']) ? (bool) $assoc_args['force'] : false;

            \WP_CLI::line(__('Starting full sync to Xero...', 'ix-woo-xero'));
            
            // Sync products
            \WP_CLI::line("\n" . __('Syncing products...', 'ix-woo-xero'));
            $this->sync_products([], ['force' => $force]);
            
            // Sync customers
            \WP_CLI::line("\n" . __('Syncing customers...', 'ix-woo-xero'));
            $this->sync_customers([], ['days' => $days, 'force' => $force]);
            
            // Sync orders
            \WP_CLI::line("\n" . __('Syncing orders...', 'ix-woo-xero'));
            $this->sync_orders([], ['days' => $days, 'force' => $force]);
            
            // Sync subscriptions if available
            if (class_exists('WC_Subscriptions')) {
                \WP_CLI::line("\n" . __('Syncing subscriptions...', 'ix-woo-xero'));
                $this->sync_subscriptions([], ['days' => $days, 'force' => $force]);
            }
            
            \WP_CLI::success(__('Full sync completed!', 'ix-woo-xero'));
        }

        /**
         * Get connection status with Xero
         *
         * ## EXAMPLES
         * wp ix-xero status
         *
         * @param array $args
         * @param array $assoc_args
         */
        public function status($args, $assoc_args) {
            $is_connected = Xero_API::instance()->is_connected();
            
            \WP_CLI::line(WP_CLI::colorize('%GXero Integration Status%n'));
            \WP_CLI::line('');
            
            // Connection status
            if ($is_connected) {
                $tenant_name = Xero_API::instance()->get_tenant_name();
                \WP_CLI::line(WP_CLI::colorize('%g✓ Connected to Xero as: ' . $tenant_name . '%n'));
            } else {
                \WP_CLI::line(WP_CLI::colorize('%r✗ Not connected to Xero%n'));
            }
            
            \WP_CLI::line('');
            \WP_CLI::line(WP_CLI::colorize('%GSync Status%n'));
            \WP_CLI::line('');
            
            // Products
            $product_count = $this->get_product_count();
            $synced_products = $this->get_synced_product_count();
            \WP_CLI::line(sprintf(
                __('Products: %d/%d synced (%.1f%%)', 'ix-woo-xero'),
                $synced_products,
                $product_count,
                ($product_count > 0) ? ($synced_products / $product_count) * 100 : 0
            ));
            
            // Orders
            $order_count = $this->get_order_count();
            $synced_orders = $this->get_synced_order_count();
            \WP_CLI::line(sprintf(
                __('Orders: %d/%d synced (%.1f%%)', 'ix-woo-xero'),
                $synced_orders,
                $order_count,
                ($order_count > 0) ? ($synced_orders / $order_count) * 100 : 0
            ));
            
            // Customers
            $customer_count = $this->get_customer_count();
            $synced_customers = $this->get_synced_customer_count();
            \WP_CLI::line(sprintf(
                __('Customers: %d/%d synced (%.1f%%)', 'ix-woo-xero'),
                $synced_customers,
                $customer_count,
                ($customer_count > 0) ? ($synced_customers / $customer_count) * 100 : 0
            ));
            
            // Subscriptions
            if (class_exists('WC_Subscriptions')) {
                $subscription_count = $this->get_subscription_count();
                $synced_subscriptions = $this->get_synced_subscription_count();
                \WP_CLI::line(sprintf(
                    __('Subscriptions: %d/%d synced (%.1f%%)', 'ix-woo-xero'),
                    $synced_subscriptions,
                    $subscription_count,
                    ($subscription_count > 0) ? ($synced_subscriptions / $subscription_count) * 100 : 0
                ));
            }
            
            // Last sync times
            \WP_CLI::line('');
            \WP_CLI::line(WP_CLI::colorize('%GLast Sync Times%n'));
            \WP_CLI::line(sprintf(
                __('Products: %s', 'ix-woo-xero'),
                get_option('ix_woo_xero_last_product_sync', __('Never', 'ix-woo-xero'))
            ));
            \WP_CLI::line(sprintf(
                __('Orders: %s', 'ix-woo-xero'),
                get_option('ix_woo_xero_last_order_sync', __('Never', 'ix-woo-xero'))
            ));
            \WP_CLI::line(sprintf(
                __('Customers: %s', 'ix-woo-xero'),
                get_option('ix_woo_xero_last_customer_sync', __('Never', 'ix-woo-xero'))
            ));
            
            if (class_exists('WC_Subscriptions')) {
                \WP_CLI::line(sprintf(
                    __('Subscriptions: %s', 'ix-woo-xero'),
                    get_option('ix_woo_xero_last_subscription_sync', __('Never', 'ix-woo-xero'))
                ));
            }
        }

        /**
         * Get products to sync
         */
        private function get_products_to_sync($force = false) {
            $args = [
                'post_type' => 'product',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            ];
            
            if (!$force) {
                $args['meta_query'] = [
                    [
                        'key' => '_xero_item_id',
                        'compare' => 'NOT EXISTS'
                    ]
                ];
            }
            
            return get_posts($args);
        }

        /**
         * Get orders to sync
         */
        private function get_orders_to_sync($status = ['processing', 'completed'], $days = null, $force = false) {
            $args = [
                'status' => $status,
                'limit' => -1,
                'return' => 'objects'
            ];
            
            if ($days) {
                $args['date_after'] = date('Y-m-d', strtotime("-{$days} days"));
            }
            
            if (!$force) {
                $args['meta_query'] = [
                    [
                        'key' => '_xero_invoice_id',
                        'compare' => 'NOT EXISTS'
                    ]
                ];
            }
            
            return wc_get_orders($args);
        }

        /**
         * Get customers to sync
         */
        private function get_customers_to_sync($role = 'customer', $days = null, $force = false) {
            $args = [
                'role' => $role,
                'fields' => 'ID'
            ];
            
            if ($days) {
                $args['date_query'] = [
                    [
                        'after' => date('Y-m-d', strtotime("-{$days} days"))
                    ]
                ];
            }
            
            if (!$force) {
                $args['meta_query'] = [
                    [
                        'key' => '_xero_contact_id',
                        'compare' => 'NOT EXISTS'
                    ]
                ];
            }
            
            return get_users($args);
        }

        /**
         * Get subscriptions to sync
         */
        private function get_subscriptions_to_sync($status = 'any', $days = null, $force = false) {
            $args = [
                'status' => $status,
                'limit' => -1,
                'return' => 'objects'
            ];
            
            if ($days) {
                $args['date_after'] = date('Y-m-d', strtotime("-{$days} days"));
            }
            
            if (!$force) {
                $args['meta_query'] = [
                    [
                        'key' => '_xero_subscription_id',
                        'compare' => 'NOT EXISTS'
                    ]
                ];
            }
            
            return wcs_get_subscriptions($args);
        }

        /**
         * Get product count
         */
        private function get_product_count() {
            global $wpdb;
            return $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} 
                 WHERE post_type = 'product' 
                 AND post_status = 'publish'"
            );
        }

        /**
         * Get synced product count
         */
        private function get_synced_product_count() {
            global $wpdb;
            return $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_xero_item_id' 
                 AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product')"
            );
        }

        /**
         * Get order count
         */
        private function get_order_count() {
            global $wpdb;
            return $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} 
                 WHERE post_type = 'shop_order' 
                 AND post_status IN ('wc-processing', 'wc-completed')"
            );
        }

        /**
         * Get synced order count
         */
        private function get_synced_order_count() {
            global $wpdb;
            return $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_xero_invoice_id' 
                 AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order')"
            );
        }

        /**
         * Get customer count
         */
        private function get_customer_count() {
            global $wpdb;
            return $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->users}
                 WHERE ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'wp_capabilities' AND meta_value LIKE '%customer%')"
            );
        }

        /**
         * Get synced customer count
         */
        private function get_synced_customer_count() {
            global $wpdb;
            return $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} 
                 WHERE meta_key = '_xero_contact_id'"
            );
        }

        /**
         * Get subscription count
         */
        private function get_subscription_count() {
            if (!class_exists('WC_Subscriptions')) {
                return 0;
            }

            global $wpdb;
            return $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} 
                 WHERE post_type = 'shop_subscription'"
            );
        }

        /**
         * Get synced subscription count
         */
        private function get_synced_subscription_count() {
            if (!class_exists('WC_Subscriptions')) {
                return 0;
            }

            global $wpdb;
            return $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_xero_subscription_id' 
                 AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_subscription')"
            );
        }
    }

    // Register CLI commands
    \WP_CLI::add_command('ix-xero', 'IX_Woo_Xero\CLI\IX_Woo_Xero_CLI');
}