<?php
/**
 * Elementor Cart Integration Common Class
 *
 * @package BWFAN_Elementor_Cart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'BWFAN_Elementor_Cart_Common' ) ) {

	final class BWFAN_Elementor_Cart_Common {

		private static $_instance = null;

		private function __construct() {
			$this->init_hooks();
		}

		public static function init() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		public static function get_instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		private function init_hooks() {
			// Check dependencies and show notices
			add_action( 'admin_notices', array( $this, 'check_dependencies' ) );

			// Check dependencies first
			$dependencies_met = $this->are_dependencies_met();

			// Only proceed if all dependencies are met
			if ( ! $dependencies_met ) {
				return;
			}

			// Hook into Elementor form submissions - use priority 5 to run before other handlers
			add_action( 'elementor_pro/forms/new_record', array( $this, 'handle_elementor_form_submission' ), 5, 2 );

			// Add JavaScript for client-side cookie setting (fallback)
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			// AJAX handler for JavaScript fallback
			add_action( 'wp_ajax_bwfan_elementor_set_contact_cookie', array( $this, 'ajax_set_contact_cookie' ) );
			add_action( 'wp_ajax_nopriv_bwfan_elementor_set_contact_cookie', array( $this, 'ajax_set_contact_cookie' ) );
		}

		/**
		 * Check if all required plugins are active
		 *
		 * @return bool
		 */
		private function are_dependencies_met() {
			// Elementor Pro check is optional - we'll try to hook anyway
			return bwfan_is_woocommerce_active() && bwfan_is_automations_active() && bwfan_is_automations_pro_active();
		}

		/**
		 * Check dependencies and show admin notices
		 */
		public function check_dependencies() {
			$missing = array();

			if ( ! bwfan_is_woocommerce_active() ) {
				$missing[] = 'WooCommerce';
			}

			if ( ! bwfan_is_automations_active() ) {
				$missing[] = 'FunnelKit Automations';
			}

			if ( ! bwfan_is_automations_pro_active() ) {
				$missing[] = 'FunnelKit Automations Pro';
			}

			if ( ! bwfan_is_elementor_pro_active() ) {
				$missing[] = 'Elementor Pro';
			}

			if ( ! empty( $missing ) ) {
				$this->show_dependency_notice( $missing );
			}
		}

		/**
		 * Show dependency notice
		 *
		 * @param array $missing Missing plugins
		 */
		private function show_dependency_notice( $missing ) {
			$plugins_list = implode( ', ', $missing );
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'FunnelKit Automations - Elementor Form Cart Integration', 'wp-marketing-automations-pro' ); ?></strong>: 
					<?php
					printf(
						/* translators: %s: List of missing plugins */
						esc_html__( 'The following required plugins are missing or inactive: %s. Please install and activate them.', 'wp-marketing-automations-pro' ),
						'<strong>' . esc_html( $plugins_list ) . '</strong>'
					);
					?>
				</p>
			</div>
			<?php
		}


		/**
		 * Handle Elementor form submission
		 *
		 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record Form record object
		 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler Ajax handler object
		 */
		public function handle_elementor_form_submission( $record, $ajax_handler ) {
			// Get form ID
			$form_id = 0;
			if ( method_exists( $record, 'get_form_settings' ) ) {
				$form_id = $record->get_form_settings( 'id' );
			}

			// Check if form ID is allowed via filter
			// If filter returns empty/null/false, allow all forms (backward compatibility)
			// If filter returns an array, only allow forms with IDs in that array
			$allowed_form_ids = apply_filters( 'bwfan_elementor_cart_allowed_form_ids', array() );
			
			if ( ! empty( $allowed_form_ids ) && is_array( $allowed_form_ids ) ) {
				// Convert all values to strings for comparison (form IDs can be strings or integers)
				$allowed_form_ids = array_map( 'strval', $allowed_form_ids );
				$form_id_str      = strval( $form_id );
				
				if ( ! in_array( $form_id_str, $allowed_form_ids, true ) ) {
					// Form ID not in allowed list, skip processing
					return;
				}
			}

			// Get form fields - try multiple methods (same as FunnelKit Pro)
			$fields = array();
			
			// Method 1: get('fields') - This is what FunnelKit Pro uses
			if ( method_exists( $record, 'get' ) ) {
				$fields = $record->get( 'fields' );
			}

			// Method 2: get_formatted_data() - Alternative method
			if ( empty( $fields ) && method_exists( $record, 'get_formatted_data' ) ) {
				$fields = $record->get_formatted_data();
			}

			// Method 3: Direct property access
			if ( empty( $fields ) && isset( $record->fields ) ) {
				$fields = $record->fields;
			}

			if ( empty( $fields ) || ! is_array( $fields ) ) {
				return;
			}

			// Extract email from form fields
			$email = $this->extract_email_from_fields( $fields );

			if ( empty( $email ) || ! is_email( $email ) ) {
				return;
			}

			// Create or update contact and set cookie
			$contact = $this->create_or_update_contact( $email, $fields );

			if ( ! $contact ) {
				return;
			}

			// Set the contact UID cookie
			$this->set_contact_uid_cookie( $contact );

			// Link to existing abandoned carts OR create one if cart has items
			$carts_linked = $this->link_to_abandoned_carts( $email, $contact );

			// If no cart found but WooCommerce cart has items, create abandoned cart
			if ( 0 === $carts_linked && class_exists( 'WooCommerce' ) && ! is_null( WC()->cart ) ) {
				$cart_items_count = WC()->cart->get_cart_contents_count();

				if ( $cart_items_count > 0 && ! WC()->cart->is_empty() ) {
					$this->create_abandoned_cart_from_wc_cart( $email, $contact );
				}
			}
		}

		/**
		 * Extract email from form fields
		 *
		 * @param array $fields Form fields
		 * @return string|false Email address or false if not found
		 */
		private function extract_email_from_fields( $fields ) {
			$email_patterns = apply_filters( 'bwfan_elementor_cart_email_patterns', array(
				'email',
				'your-email',
				'e-mail',
				'mail',
				'email_address',
				'emailaddress',
				'billing_email',
			) );

			foreach ( $fields as $key => $field ) {
				$field_data = $this->parse_field( $field, $key );

				if ( empty( $field_data ) ) {
					continue;
				}

				// Check by field type first (most reliable)
				if ( 'email' === $field_data['type'] && is_email( $field_data['value'] ) ) {
					return sanitize_email( $field_data['value'] );
				}

				// Check by field ID/name patterns
				foreach ( $email_patterns as $pattern ) {
					if ( false !== strpos( $field_data['id'], $pattern ) || false !== strpos( $field_data['name'], $pattern ) ) {
						if ( is_email( $field_data['value'] ) ) {
							return sanitize_email( $field_data['value'] );
						}
					}
				}

				// Direct email validation
				if ( is_email( $field_data['value'] ) ) {
					return sanitize_email( $field_data['value'] );
				}
			}

			return false;
		}

		/**
		 * Parse field data from different structures
		 *
		 * @param mixed  $field Field data
		 * @param string $key Field key
		 * @return array Parsed field data
		 */
		private function parse_field( $field, $key ) {
			$data = array(
				'id'    => '',
				'name'  => '',
				'type'  => '',
				'value' => '',
			);

			if ( is_array( $field ) ) {
				$data['id']    = isset( $field['id'] ) ? strtolower( $field['id'] ) : '';
				$data['name']  = isset( $field['title'] ) ? strtolower( $field['title'] ) : ( isset( $field['name'] ) ? strtolower( $field['name'] ) : '' );
				$data['type']  = isset( $field['type'] ) ? strtolower( $field['type'] ) : '';
				$data['value'] = isset( $field['value'] ) ? $field['value'] : '';
			} elseif ( is_string( $key ) && is_email( $field ) ) {
				$data['id']    = strtolower( $key );
				$data['value'] = $field;
			} elseif ( is_object( $field ) ) {
				$data['id']    = isset( $field->id ) ? strtolower( $field->id ) : '';
				$data['name']  = isset( $field->title ) ? strtolower( $field->title ) : ( isset( $field->name ) ? strtolower( $field->name ) : '' );
				$data['type']  = isset( $field->type ) ? strtolower( $field->type ) : '';
				$data['value'] = isset( $field->value ) ? $field->value : '';
			}

			return $data;
		}

		/**
		 * Create or update contact
		 *
		 * @param string $email Email address
		 * @param array  $fields Form fields
		 * @return WooFunnels_Contact|false Contact object or false on failure
		 */
		private function create_or_update_contact( $email, $fields = array() ) {
			if ( ! function_exists( 'bwf_get_contact' ) ) {
				return false;
			}

			$wp_id = get_current_user_id();

			// Find user by email if not logged in
			if ( 0 === $wp_id ) {
				$user = get_user_by( 'email', $email );
				if ( $user instanceof WP_User ) {
					$wp_id = $user->ID;
				}
			}

			$contact = bwf_get_contact( $wp_id, $email );

			if ( ! $contact instanceof WooFunnels_Contact ) {
				return false;
			}

			// Set email if missing
			if ( empty( $contact->get_email() ) ) {
				$contact->set_email( $email );
			}

			// Update contact fields from form
			$this->update_contact_fields( $contact, $fields );

			$contact->save();

			return $contact;
		}

		/**
		 * Update contact fields from form data
		 *
		 * @param WooFunnels_Contact $contact Contact object
		 * @param array              $fields Form fields
		 */
		private function update_contact_fields( $contact, $fields ) {
			$field_mappings = apply_filters( 'bwfan_elementor_cart_field_mappings', array(
				'first_name' => array( 'first_name', 'firstname', 'fname', 'name' ),
				'last_name'  => array( 'last_name', 'lastname', 'lname', 'surname' ),
				'phone'      => array( 'phone', 'telephone', 'mobile', 'phone_number' ),
			) );

			foreach ( $field_mappings as $method => $patterns ) {
				$value = $this->extract_field_value( $fields, $patterns );

				if ( empty( $value ) ) {
					continue;
				}

				$setter_method = 'set_' . $method;
				if ( method_exists( $contact, $setter_method ) ) {
					$contact->$setter_method( sanitize_text_field( $value ) );
				}
			}
		}

		/**
		 * Extract field value by matching patterns
		 *
		 * @param array $fields Form fields
		 * @param array $patterns Field ID/name patterns to match
		 * @return string|false Field value or false if not found
		 */
		private function extract_field_value( $fields, $patterns ) {
			foreach ( $fields as $field ) {
				$field_id   = isset( $field['id'] ) ? strtolower( $field['id'] ) : '';
				$field_name = isset( $field['title'] ) ? strtolower( $field['title'] ) : '';
				$field_value = isset( $field['value'] ) ? $field['value'] : '';

				foreach ( $patterns as $pattern ) {
					if ( false !== strpos( $field_id, $pattern ) || false !== strpos( $field_name, $pattern ) ) {
						if ( ! empty( $field_value ) ) {
							return $field_value;
						}
					}
				}
			}

			return false;
		}

		/**
		 * Set contact UID cookie
		 *
		 * @param WooFunnels_Contact $contact Contact object
		 * @return bool Whether cookie was set
		 */
		private function set_contact_uid_cookie( $contact ) {
			if ( ! $contact instanceof WooFunnels_Contact ) {
				return false;
			}

			$uid = $contact->get_uid();

			if ( empty( $uid ) ) {
				return false;
			}

			$expiry = time() + ( 10 * YEAR_IN_SECONDS );

			// Use BWFAN_Common::set_cookie if available
			if ( class_exists( 'BWFAN_Common' ) && method_exists( 'BWFAN_Common', 'set_cookie' ) ) {
				BWFAN_Common::set_cookie( '_fk_contact_uid', $uid, $expiry );
				return true;
			}

			// Fallback to native setcookie
			if ( ! headers_sent() ) {
				return setcookie( '_fk_contact_uid', $uid, $expiry, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
			}

			return false;
		}

		/**
		 * Link contact to existing abandoned carts
		 *
		 * @param string            $email Email address
		 * @param WooFunnels_Contact $contact Contact object
		 * @return int Number of carts linked
		 */
		private function link_to_abandoned_carts( $email, $contact ) {
			if ( ! class_exists( 'BWFAN_Abandoned_Cart' ) ) {
				return 0;
			}

			$abandoned_obj = BWFAN_Abandoned_Cart::get_instance();

			if ( ! $abandoned_obj ) {
				return 0;
			}

			$tracking_cookie = $this->get_tracking_cookie();

			global $wpdb;
			$table_name = $wpdb->prefix . 'bwfan_abandonedcarts';

			// Verify table exists
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
				return 0;
			}

			// First, try to find carts by tracking cookie (more reliable for guest carts)
			$carts = array();
			if ( ! empty( $tracking_cookie ) ) {
				$cookie_carts = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ID, email, cookie_key FROM {$table_name} WHERE cookie_key = %s AND status IN (0, 1) ORDER BY last_modified DESC",
						$tracking_cookie
					),
					ARRAY_A
				);

				if ( ! empty( $cookie_carts ) ) {
					$carts = $cookie_carts;
				}
			}

			// If no carts found by cookie, try by email
			if ( empty( $carts ) ) {
				$email_carts = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ID, email, cookie_key FROM {$table_name} WHERE email = %s AND status IN (0, 1) ORDER BY last_modified DESC",
						$email
					),
					ARRAY_A
				);

				if ( ! empty( $email_carts ) ) {
					$carts = $email_carts;
				}
			}

			if ( empty( $carts ) ) {
				return 0;
			}

			$carts_linked = 0;
			$uid          = $contact->get_uid();

			// Update carts with contact UID and tracking cookie
			foreach ( $carts as $cart ) {
				$update_data = array();

				// Update email if different
				if ( $cart['email'] !== $email ) {
					$update_data['email'] = $email;
				}

				// Update cookie_key if we have a tracking cookie
				if ( ! empty( $tracking_cookie ) && $cart['cookie_key'] !== $tracking_cookie ) {
					$update_data['cookie_key'] = $tracking_cookie;
				}

				// Update last_modified to refresh cart
				$update_data['last_modified'] = current_time( 'mysql', 1 );

				if ( ! empty( $update_data ) ) {
					$updated = $wpdb->update(
						$table_name,
						$update_data,
						array( 'ID' => $cart['ID'] ),
						array( '%s', '%s', '%s' ),
						array( '%d' )
					);

				if ( false !== $updated ) {
					$carts_linked++;
				}
			}
		}

		// Also check for carts by tracking cookie without email and update them
		if ( ! empty( $tracking_cookie ) && empty( $carts ) ) {
			$cookie_carts_no_email = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID FROM {$table_name} WHERE cookie_key = %s AND (email = '' OR email IS NULL) AND status IN (0, 1) ORDER BY last_modified DESC LIMIT 1",
					$tracking_cookie
				),
				ARRAY_A
			);

			if ( ! empty( $cookie_carts_no_email ) ) {
				$updated = $wpdb->update(
					$table_name,
					array(
						'email'         => $email,
						'last_modified' => current_time( 'mysql', 1 ),
					),
					array( 'ID' => $cookie_carts_no_email[0]['ID'] ),
					array( '%s', '%s' ),
					array( '%d' )
				);

				if ( false !== $updated ) {
					$carts_linked++;
				}
			}
		}

		return $carts_linked;
		}

		/**
		 * Create abandoned cart from current WooCommerce cart
		 *
		 * @param string            $email Email address
		 * @param WooFunnels_Contact $contact Contact object
		 * @return int|false Cart ID or false on failure
		 */
		private function create_abandoned_cart_from_wc_cart( $email, $contact ) {
			if ( ! class_exists( 'BWFAN_Abandoned_Cart' ) ) {
				return false;
			}

			if ( ! class_exists( 'WooCommerce' ) || is_null( WC()->cart ) || WC()->cart->is_empty() ) {
				return false;
			}

			$abandoned_obj = BWFAN_Abandoned_Cart::get_instance();

			if ( ! $abandoned_obj ) {
				return false;
			}

			$tracking_cookie = $this->get_tracking_cookie();

			// Generate if missing
			if ( empty( $tracking_cookie ) ) {
				$tracking_cookie = $this->generate_tracking_cookie();
			}

			// Set bwfan_session cookie (required for process_guest_cart_details)
			// This tells FunnelKit that this is a guest session eligible for cart tracking
			if ( class_exists( 'BWFAN_Common' ) && method_exists( 'BWFAN_Common', 'set_cookie' ) ) {
				BWFAN_Common::set_cookie( 'bwfan_session', '1', time() + ( 10 * YEAR_IN_SECONDS ) );
			} elseif ( ! headers_sent() ) {
				setcookie( 'bwfan_session', '1', time() + ( 10 * YEAR_IN_SECONDS ), COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
			}

			// Also set in session
			if ( ! is_null( WC()->session ) ) {
				WC()->session->set( 'bwfan_session', '1' );
			}

			// Prepare cart data
			$cart_data = array(
				'email'      => $email,
				'cookie_key' => $tracking_cookie,
				'user_id'    => get_current_user_id(),
			);

			// Use FunnelKit's process_guest_cart_details method to create cart
			// This ensures proper cart creation with all necessary data
			if ( method_exists( $abandoned_obj, 'process_guest_cart_details' ) ) {
				$cart_id = $abandoned_obj->process_guest_cart_details( $email, array() );

				if ( $cart_id > 0 ) {
					return $cart_id;
				}
			}

			// Fallback: Use create_abandoned_cart method directly
			if ( method_exists( $abandoned_obj, 'create_abandoned_cart' ) ) {
				$cart_id = $abandoned_obj->create_abandoned_cart( $cart_data );
				return $cart_id;
			}

			return false;
		}

		/**
		 * Generate tracking cookie if it doesn't exist
		 *
		 * @return string Tracking cookie value
		 */
		private function generate_tracking_cookie() {
			$cookie_value = wp_generate_password( 32, false );

			// Set cookie
			if ( class_exists( 'BWFAN_Common' ) && method_exists( 'BWFAN_Common', 'set_cookie' ) ) {
				BWFAN_Common::set_cookie( 'bwfan_visitor', $cookie_value, time() + ( 10 * YEAR_IN_SECONDS ) );
			} elseif ( ! headers_sent() ) {
				setcookie( 'bwfan_visitor', $cookie_value, time() + ( 10 * YEAR_IN_SECONDS ), COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
			}

			// Also set in session
			if ( ! is_null( WC()->session ) ) {
				WC()->session->set( 'bwfan_visitor', $cookie_value );
			}

			return $cookie_value;
		}

		/**
		 * Get tracking cookie from various sources
		 *
		 * @return string Tracking cookie value
		 */
		private function get_tracking_cookie() {
			if ( class_exists( 'BWFAN_Common' ) && method_exists( 'BWFAN_Common', 'get_cookie' ) ) {
				$cookie = BWFAN_Common::get_cookie( 'bwfan_visitor' );
				if ( ! empty( $cookie ) ) {
					return $cookie;
				}
			}

			if ( ! is_null( WC()->session ) ) {
				$cookie = WC()->session->get( 'bwfan_visitor' );
				if ( ! empty( $cookie ) ) {
					return $cookie;
				}
			}

			return '';
		}

		/**
		 * Enqueue scripts for client-side cookie setting (fallback)
		 */
		public function enqueue_scripts() {
			if ( is_admin() || ! bwfan_is_elementor_pro_active() ) {
				return;
			}

			wp_enqueue_script(
				'bwfan-elementor-cart-integration',
				BWFAN_ELEMENTOR_CART_PLUGIN_URL . '/assets/js/frontend.js',
				array( 'jquery' ),
				BWFAN_ELEMENTOR_CART_VERSION,
				true
			);

			wp_localize_script(
				'bwfan-elementor-cart-integration',
				'bwfanElementorCart',
				array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'bwfan_elementor_cart_nonce' ),
				)
			);
		}

		/**
		 * AJAX handler for setting contact cookie (JavaScript fallback)
		 */
		public function ajax_set_contact_cookie() {
			// Verify nonce
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'bwfan_elementor_cart_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'wp-marketing-automations-pro' ) ) );
			}

			// Get email
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

			if ( empty( $email ) || ! is_email( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'wp-marketing-automations-pro' ) ) );
			}

			// Create or update contact
			$contact = $this->create_or_update_contact( $email );

			if ( ! $contact ) {
				wp_send_json_error( array( 'message' => __( 'Failed to create contact.', 'wp-marketing-automations-pro' ) ) );
			}

			// Set cookie
			$this->set_contact_uid_cookie( $contact );

			// Link to carts
			$this->link_to_abandoned_carts( $email, $contact );

			wp_send_json_success(
				array(
					'message'    => __( 'Contact cookie set successfully.', 'wp-marketing-automations-pro' ),
					'contact_id' => $contact->get_id(),
					'uid'        => $contact->get_uid(),
				)
			);
		}
	}
}

