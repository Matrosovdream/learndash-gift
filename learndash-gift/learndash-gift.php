<?php
/*
Plugin Name: LearnDash Gift a Course by BogdanFix
Plugin URI: https://bogdanfix.com/learndash-gift-woocommerce/
Description: Enables a "gift" option at checkout when selling courses with LearnDash and WooCommerce.
Author: BogdanFix
Version: 1.1.0
Author URI: https://bogdanfix.com
Text Domain: learndash-gift
Domain Path: /lang

WC requires at least: 3.0.0
WC tested up to: 5.0.0

Copyright: (c) 2021-2024 BogdanFix, Inc (email: bogdan@bogdanfix.com)
*/

/*
WARNING: If you make changes to the actual code of this plugin,
your changes will be LOST when we send out an update. Please, save your
changes, if you feel they are necessary. We do not offer support for
custom coding to this plugin. If you’ve created a customization
you think we should add please let us know and we can look at
adding it to the core. Thank you!
*/

define( 'GB_LDG_NAME', 'LearnDash Gift a Course' );
define( 'GB_LDG_VER', '1.1.0' );
define( 'GB_LDG_ID', 'learndash_gift' );
define( 'GB_LDG_FILE', 'learndash-gift/learndash-gift.php' );


/*
	TODO:
	? multiple recepients +3
	+ scheduled delivery +2
	? check if product is a course +3
	+ optionally 2 name fields instead of 1
	? make field priority easily customizable in settings +1
	? disable gift purchase option per product, per product type
	? revoke access on refund
	? email text and email subject settings in one place (maybe mirror in both places)
	? optionally, force password to be put into new account email
	? link to the gift recepient user profile on the order page (if created)
	? check if user was really granted access to the course(s) with sfwd_lms_has_access
	? check if gift recipient already has an account, do not change his first+last names, when gift if purchased
	? grant access to courses & groups to gift recipient if silent enrollment is happening

	function bf_silent_enrollment()
	{
		return 10;
	}
	add_filter( 'learndash_woocommerce_products_count_for_silent_course_enrollment', 'bf_silent_enrollment', 99 );
*/

/**
 * Get the update info from the server
 *
 * @since 1.0.0
 */

function gb_ldg_check_update()
{
    // load textdomain

    load_plugin_textdomain( 'learndash-gift', FALSE, basename( dirname( __FILE__ ) ) . '/lang' );

    // check for updates & cache api response

    $update = get_site_transient( 'gb_ldg_update_data' );

    if( $update === FALSE )
    {
        $status = 'release';

        $options = get_option( 'gb_ldg_options' );

        if( !empty( $options['beta_subscription'] ) )
        {
            $status = 'beta';
        }

        if( mb_strpos( $_SERVER['SERVER_NAME'], 'www.' ) === 0 )
        {
            $_SERVER['SERVER_NAME'] = mb_substr( $_SERVER['SERVER_NAME'], 4 );
        }

        $update = wp_remote_get( 'https://bogdanfix.com/cp/api/index.php?r=update&k=' . urlencode( $options['key'] ) . '&p=' . urlencode( GB_LDG_ID ) . '&d=' . urlencode( $_SERVER['SERVER_NAME'] ) . '&s=' . urlencode( $status ) );

        if( is_array( $update ) )
        {
            $update = $update['body']; // use the content

            $update = json_decode( $update, TRUE );

            if( !empty( $update ) )
            {
                //
            }
            else
            {
                $update = array(
                    'status' => 'error',
                    'error' => 'json_parse_error',
                    'error_text' => __( 'JSON parse error.', 'learndash-gift' ),
                );
            }
        }

        // if WP_Error response received

        else
        {
            $update = array(
                'status' => 'error',
                'error' => 'request_error',
                'error_text' => $update->get_error_message(),
            );
        }

        update_option( 'gb_ldg_update_data', $update );

        set_site_transient( 'gb_ldg_update_data', $update, 21600 ); // 6
    }

    // declare WooCommerce HPOS compatibility

	add_action( 'before_woocommerce_init', function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	} );
}
add_action( 'init', 'gb_ldg_check_update' );

/**
 * Insert the plugin's update info into the WP update list
 *
 * @param StdClass $plugins update list
 * @return array modified update list
 * @since 1.0.0
 */

function gb_ldg_inject_update( $plugins )
{
    $update = get_option( 'gb_ldg_update_data' );

    if(
        is_array( $update ) &&
        !empty( $update['status'] ) &&
        $update['status'] == 'ok' &&
        !empty( $update['result'] ) &&
        !empty( $update['result']['update_version'] ) &&
        !empty( $update['result']['update_description'] )
    )
    {
        $current_version = intval( str_replace( '.', '', GB_LDG_VER ) );

        // compare versions

        $update_version = intval( str_replace( '.', '', $update['result']['update_version'] ) );
        $update_description = $update['result']['update_description'];
        $update_url = '';

        if( !empty( $update['result']['update_url'] ) )
        {
            $update_url = $update['result']['update_url'];
        }

        if( $current_version < $update_version )
        {
            $plugin = array(
                'id' => 999109,
                'slug' => GB_LDG_ID,
                'plugin' => GB_LDG_FILE,
                'new_version' => $update['result']['update_version'],
                'url' => 'https://bogdanfix.com/learndash-gift-woocommerce/',
                'package' => $update_url,
            );

            $plugin = (object) $plugin;

            $plugins->response[ GB_LDG_FILE ] = $plugin;
        }
    }

    return $plugins;
}
add_filter( 'site_transient_update_plugins', 'gb_ldg_inject_update' );

/**
 * Displays admin message
 *
 * @since 1.0.0
 */

function gb_ldg_admin_display_message()
{
    $screen = get_current_screen();

    if( !empty( $screen->id ) )
    {
        $screen = $screen->id;
    }

    $user_id = get_current_user_id();

    $options = get_option( 'gb_ldg_options' );

    // if plugin is not activated

    if( empty( $options['key'] ) && empty( $_POST['gb_key'] ) )
    {
        echo '<div class="notice notice-error"><p>';

        printf(
            __( '<b>%s is not activated.</b> Please, activate it <a href="%s">here</a> to receive regular updates, new features and support.', 'learndash-gift' ),
            GB_LDG_NAME,
            'admin.php?page=' . GB_LDG_ID . '&tab=settings'
        );

        echo '</p></div>';
    }

    // if update is available

    $update = get_option('gb_ldg_update_data');

    if( is_array( $update ) && !empty( $update['status'] ) )
    {
        if(
            $update['status'] == 'ok' &&
            !empty( $update['result'] )
        )
        {
            $current_version = intval( str_replace( '.', '', GB_LDG_VER ) );

            // compare versions

            $update_version = intval( str_replace( '.', '', $update['result']['update_version'] ) );

            if( $current_version < $update_version )
            {
                echo '<div class="notice notice-warning"><p>';

                printf(
                    '<b>
                        ' . GB_LDG_NAME . ' ' . $update['result']['update_version'] . ' ' .
                    __( 'update is available', 'learndash-gift' ) .
                    '.</b> <a href="%s" target="_blank">' . __( 'Check out what\'s new', 'learndash-gift' ) . '</a> ' .
                    __( 'or', 'learndash-gift' ) . ' <a href="%s">' . __( 'go to Plugins menu to update', 'learndash-gift' ) . '</a>.',

                    'https://bogdanfix.com/downloads/ldg/changelog.txt',
                    'admin.php?page=' . GB_LDG_ID . '&tab=settings'
                );

                echo '</p></div>';
            }
        }

        elseif(
            $update['status'] == 'error' &&
            !empty( $update['error'] ) &&
            $update['error'] == 'license_live'
        )
        {
            echo '<div class="notice notice-warning"><p>' . $update['error_text'] . '</p></div>';
        }
    }
}
add_action( 'admin_notices', 'gb_ldg_admin_display_message' );

/**
 * Register admin page
 *
 * @since 1.0.0
 */

function gb_ldg_add_page()
{
	add_submenu_page( 'woocommerce', GB_LDG_NAME, 'LearnDash Gift', 'manage_options', GB_LDG_ID, 'gb_ldg_do_page' );
}
add_action( 'admin_menu', 'gb_ldg_add_page' );

/**
 * Display admin page
 *
 * @since 1.0.0
 */

function gb_ldg_do_page()
{
    if( !current_user_can( 'manage_options' ) )
    {
        wp_die( __( 'Oops, you can\'t access this page.', 'learndash-gift' ) );
    }

    include_once 'learndash-gift-admin.php';
}

/**
 * Initialize options for the admin page
 *
 * @since 1.0.0
 */

function gb_ldg_init()
{
	register_setting( 'gb_ldg_options', 'gb_ldg_options' );
}
add_action( 'admin_init', 'gb_ldg_init' );

/**
 * Add gift fields (checkbox, name, email, note)
 *
 * @since 1.0.0
 */

function gb_lgd_add_gift_fields( $fields )
{
	$options = get_option( 'gb_ldg_options' );

	// hide gift fields if there are no courses in cart

	if( !empty( $options['hide_gift_fields'] ) )
	{
		$items = WC()->cart->get_cart();

		$related_course = $product = 0;

		$hide_fields = TRUE;

		foreach( $items as $key => $item )
		{
			if( !empty( $item['product_id'] ) )
			{
				$product = wc_get_product( intval( $item['product_id'] ) );

				$related_course = $product->get_meta( '_related_course', TRUE );

				if( !empty( $related_course ) )
				{
					$hide_fields = FALSE;

					break;
				}
			}
		}

		if( $hide_fields === TRUE )
		{
			return $fields;
		}
	}

	// display fields

	$fields['billing']['gift_purchase'] = array(
		'type'			=> 'checkbox',
		'label'     	=> __( 'Purchase as a gift?', 'learndash-gift' ),
		'required'  	=> FALSE,
		'class'     	=> array( 'form-row-wide' ),
		'clear'     	=> TRUE,
	);

	$fields['billing']['gift_name'] = array(
		'label'     	=> __( 'Recipient\'s first name', 'learndash-gift' ),
		'placeholder'   => _x( 'Who will receive your gift?', 'placeholder', 'learndash-gift' ),
		'required'  	=> FALSE,
		'class'     	=> array( 'form-row-wide form-row-gift' ),
		'clear'     	=> TRUE,
	);

	// optionally add extra last name field

	if( !empty( $options['enable_last_name'] ) )
	{
		$fields['billing']['gift_last_name'] = array(
			'label'     	=> __( 'Recipient\'s last name', 'learndash-gift' ),
			'placeholder'   => '',
			'required'  	=> FALSE,
			'class'     	=> array( 'form-row-wide form-row-gift' ),
			'clear'     	=> TRUE,
		);
	}

	$fields['billing']['gift_email'] = array(
		'label'     	=> __( 'Recipient\'s email', 'learndash-gift' ),
		'placeholder'   => _x( 'His or her email address', 'placeholder', 'learndash-gift' ),
		'required'  	=> FALSE,
		'class'     	=> array( 'form-row-wide form-row-gift' ),
		'clear'     	=> TRUE,
	);

	$fields['billing']['gift_note'] = array(
		// 'type'			=> 'textarea',
		'label'     	=> __( 'Gift message', 'learndash-gift' ),
		'placeholder'   => _x( 'Personalize your gift with a little note', 'placeholder', 'learndash-gift' ),
		'required'  	=> FALSE,
		'class'     	=> array( 'form-row-wide form-row-gift' ),
		'clear'     	=> TRUE,
	);

	// optionally allow to set date & time for gift delivery

	if( !empty( $options['enable_scheduled_delivery'] ) )
	{
		$fields['billing']['gift_delivery_datetime'] = array(
			'label'     	=> __( 'When to deliver a gift?', 'learndash-gift' ),
			'placeholder'   => '',
			'required'  	=> FALSE,
			'class'     	=> array( 'form-row-wide form-row-gift ldg-datetimepicker' ),
			'clear'     	=> TRUE,
		);
	}

	return $fields;
}
add_filter( 'woocommerce_checkout_fields' , 'gb_lgd_add_gift_fields', 10, 1 );

/**
 * Conditional logic for datepicker on WooCommerce checkout page
 *
 * @since 1.1.0
 */

function gb_ldg_enqueue_datepicker()
{
	if( is_checkout() )
	{
		$options = get_option( 'gb_ldg_options' );

		if( !empty( $options['enable_scheduled_delivery'] ) )
		{
			// wp_enqueue_script( 'jquery-ui-datepicker' );
			// wp_enqueue_style( 'jquery-style', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.13.2/themes/smoothness/jquery-ui.css' );

			wp_enqueue_style( 
				'jquery-datetimepicker-style', 
				'https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.min.css' 
			);

			wp_enqueue_script(
				'jquery-datetimepicker', 
				'https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.full.min.js',
				array( 'jquery' ),
				'2.5.20',
				array(
					'strategy'  => 'defer',
					'in_footer' => TRUE,
				)
			);
		}
	}
}
add_action( 'wp_head', 'gb_ldg_enqueue_datepicker' );

/**
 * Conditional logic for gift checkbox on checkout page
 *
 * @since 1.0.0
 */

function gb_ldg_gift_fields_conditional_logic()
{
	if( is_checkout() )
	{

?>
<style type="text/css">
.form-row-gift {
	display: none;
}
</style>
<script type="text/javascript">
jQuery(document).ready(function(){

	jQuery('input#gift_purchase').val('');

	jQuery('input#gift_purchase').on( 'change', function(){

		var $this = jQuery(this);

		if( $this.prop('checked') ) {

			$this.val('1');

			jQuery('.form-row-gift').slideDown();
		}
		else
		{
			$this.val('');

			jQuery('.form-row-gift').slideUp();
		}
	});

	if( typeof jQuery.fn.datetimepicker() !== 'undefined' ) {

		jQuery('#gift_delivery_datetime').datetimepicker({
			lang: '<?php echo get_locale(); ?>',
			mask: true,
			format: 'Y-m-d H:i T',
			step: 30,
			// showTimezone: true,
			// allowDateRe:'\d{4}-(03-31|06-30|09-30|12-31)',
			minDate: 0, // today
			// maxDate: '+1971/01/01', // 1 year forward
			value: '<?php echo date( 'Y-m-d H:i T', strtotime('+1 day') ); ?>',
			// defaultDate: '+1970/01/02', // tomorrow
			// defaultTime: '12:00',
			// onGenerate: function( current_time, $input ) {

			// 	$input.val(  )
			// }
		});
	}
});
</script>
<?php

	}
}
add_action( 'wp_footer', 'gb_ldg_gift_fields_conditional_logic' );

/**
 * Remove (optional) affix string from gift field names
 *
 * @since 1.0.0
 */

function gb_ldg_fields_remove_optional_text( $field, $key, $args, $value )
{
	if( is_checkout() && !is_wc_endpoint_url() )
	{
		$gift_fields = array( 
			'gift_purchase', 
			'gift_name', 
			'gift_last_name', 
			'gift_email', 
			'gift_note',
			'gift_delivery_datetime',
		);

		if( in_array( $key, $gift_fields ) )
		{
			$optional = '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'woocommerce' ) . ')</span>';

			$field = str_replace( $optional, '', $field );
		}
	}

	return $field;
}
add_filter( 'woocommerce_form_field', 'gb_ldg_fields_remove_optional_text', 10, 4 );

/**
 * Validate gift fields before checkout
 *
 * @since 1.0.0
 */

function gb_lgd_validate_gift_fields()
{
	// if gift checkbox is checked and any of gift fields is empty

	if(
		!empty( $_POST['gift_purchase'] ) && 
		(
			empty( $_POST['gift_name'] ) || 
			empty( $_POST['gift_email'] ) // || 
			// empty( $_POST['gift_note'] )
		)
	)
	{
		wc_add_notice( 
			__( 'Please, enter name and email of gift recipient, and also your message along with this gift.', 'learndash-gift' ), 
			'error' 
		);
	}

	// if email is not valid

	if(
		!empty( $_POST['gift_purchase'] ) &&
		!empty( $_POST['gift_email'] ) &&

		!is_email( trim( $_POST['gift_email'] ) )
	)
	{
		wc_add_notice( 
			__( 'Please, enter valid email of gift recipient.', 'learndash-gift' ), 
			'error' 
		);
	}

	// if delivery date & time are not valid

	if( !empty( $_POST['gift_delivery_datetime'] ) )
	{
		$dt = sanitize_text_field( $_POST['gift_delivery_datetime'] ); // ISO = 2000-10-31T01:30:00.000-05:00

		$now = time();
		$timestamp = strtotime( $dt );

		// date should be valid

		if( $timestamp === FALSE )
		{
			wc_add_notice( 
				__( 'Scheduled delivery time is not valid.', 'learndash-gift' ), 
				'error' 
			);
		}

		// date should be at least 1 hour into the future

		if( $now + 3600 > $timestamp )
		{
			wc_add_notice( 
				__( 'Scheduled delivery time should be set at least 1 hour into the future.', 'learndash-gift' ), 
				'error' 
			);
		}
	}
}
add_action( 'woocommerce_checkout_process', 'gb_lgd_validate_gift_fields', 9 );

/**
 * Save custom gift fields values to order meta
 *
 * @since 1.0.0
 */

function gb_ldg_checkout_update_order_meta( $order_id )
{
	if( !empty( $_POST['gift_purchase'] ) )
	{
		$fields = array( 
			'gift_name', 
			'gift_last_name', 
			'gift_email', 
			'gift_note', 
			'gift_delivery_datetime' 
		);

		if( Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() )
		{
			$order = wc_get_order( $order_id );

			$order->update_meta_data( '_gift_purchase', sanitize_text_field( trim( $_POST['gift_purchase'] ) ) );

			foreach( $fields as $f )
			{
				if( !empty( $_POST[ $f ] ) )
				{
					$order->update_meta_data( '_' . $f, sanitize_text_field( trim( $_POST[ $f ] ) ) );
				}
			}

			$order->save();
		}
		else
		{
			update_post_meta( $order_id, '_gift_purchase', sanitize_text_field( $_POST['gift_purchase'] ) );

			foreach( $fields as $f )
			{
				if( !empty( $_POST[ $f ] ) )
				{
					update_post_meta( $order_id, '_' . $f, sanitize_text_field( trim( $_POST[ $f ] ) ) );
				}
			}
		}
	}
}
add_action( 'woocommerce_checkout_update_order_meta', 'gb_ldg_checkout_update_order_meta' );

/**
 * Prevent account registration for gift buyer 
 * if he's not logged in and he's not buying a 
 * subscription product, otherwise account is 
 * required
 *
 * @since 1.0.0
 */

function gb_ldg_prevent_buyer_registration()
{
	if( !is_user_logged_in() )
	{
		if(
			class_exists( 'WC_Subscriptions_Cart' ) && 
			WC_Subscriptions_Cart::cart_contains_subscription()
		)
		{
			return;
		}

		if( !empty( $_POST['gift_purchase'] ) && !empty( trim( $_POST['gift_email'] ) ) )
		{
			if( isset( $_POST['createaccount'] ) )
			{
				// gb_ldg_log( array( 'createaccount' => $_POST['createaccount'] ) );

				unset( $_POST['createaccount'] );
			}
		}
	}
}
add_action( 'woocommerce_checkout_process', 'gb_ldg_prevent_buyer_registration', 10 );

/**
 * Process gift purchase
 *
 * @since 1.0.0
 */

function gb_ldg_process_purchase( $order_id )
{
	// get order by id

	$order = wc_get_order( $order_id );

	if( empty( $order ) )
	{
		gb_ldg_log( array( 'error' => 'Gift order can not be retrieved.', 'order_id' => $order_id ) );

		return;
	}

	$custom_tables_enabled = Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

	if( $custom_tables_enabled )
	{
		$gift_purchase = $order->get_meta( '_gift_purchase', TRUE );

		$gift_delivery_datetime = $order->get_meta( '_gift_delivery_datetime', TRUE );

		$gift_delivery_status = $order->get_meta( '_gift_delivery_status', TRUE );
	}
	else
	{
		$gift_purchase = get_post_meta( $order_id, '_gift_purchase', TRUE );

		$gift_delivery_datetime = get_post_meta( $order_id, '_gift_delivery_datetime', TRUE );

		$gift_delivery_status = get_post_meta( $order_id, '_gift_delivery_status', TRUE );
	}

	$current_action = current_action();

	gb_ldg_log( array(
		'info' => 'Current action', 
		'current_action' => $current_action,
	));

	$order_actions = array(
		'woocommerce_order_status_processing',
		'woocommerce_order_status_completed',
		'woocommerce_payment_complete'
	);

	if( !empty( $gift_purchase ) )
	{
		// if delivery should be scheduled and status is empty

		if( 
			!empty( $gift_delivery_datetime ) && 
			empty( $gift_delivery_status )
		)
		{
			$timestamp = strtotime( $gift_delivery_datetime );

			// setup a scheduled task

			$scheduled = wp_schedule_single_event( 
				$timestamp, 
				'gb_ldg_scheduled_delivery', 
				array( $order_id )
			);

			// set scheduled status

			$scheduled_status = 'pending';

			// log delivery status update

			if( is_wp_error( $scheduled ) )
			{
				$scheduled_status = 'error';

				gb_ldg_log( array(
					'error' => 'Gift delivery schedule error', 
					'order_id' => $order_id,
                	'error_text' => $scheduled->get_error_message(),
				));
			}
			else
			{
				gb_ldg_log( array(
					'info' => 'Gift delivery scheduled', 
					'order_id' => $order_id,
					'scheduled' => $scheduled,
					'scheduled_status' => $scheduled_status,
				));

				// add order note

				$order->add_order_note(
					'<b>' . $note_label . ' Gift a Course:</b> ' . 
					__( 'Gift delivery scheduled.', 'learndash-gift' )
				);
			}

			// record scheduled status

			if( $custom_tables_enabled )
			{
				$order->update_meta_data( '_gift_delivery_status', $scheduled_status );

				$order->save();
			}
			else
			{
				update_post_meta( $order_id, '_gift_delivery_status', $scheduled_status );
			}
		}

		// if delivery is instant OR
		// if delivery is scheduled and pending

		elseif(

			empty( $gift_delivery_datetime ) ||

			(
				!empty( $gift_delivery_datetime ) &&
				!empty( $gift_delivery_status ) &&
				$gift_delivery_status === 'pending' &&
				!in_array( $current_action, $order_actions )
			)
		)
		{
			// get gift related data

			if( $custom_tables_enabled )
			{
				$gift_name = $order->get_meta( '_gift_name', TRUE );
				$gift_last_name = $order->get_meta( '_gift_last_name', TRUE );
				$gift_email = $order->get_meta( '_gift_email', TRUE );
				$gift_note = $order->get_meta( '_gift_note', TRUE );
			}
			else
			{
				$gift_name = get_post_meta( $order_id, '_gift_name', TRUE );
				$gift_last_name = get_post_meta( $order_id, '_gift_last_name', TRUE );
				$gift_email = get_post_meta( $order_id, '_gift_email', TRUE );
				$gift_note = get_post_meta( $order_id, '_gift_note', TRUE );
			}

			if( !empty( $gift_name ) && !empty( $gift_email ) ) // && !empty( $gift_note ) )
			{
				// check meta

				if( $custom_tables_enabled )
				{
					$check = $order->get_meta( '_ldg_gift_processed', TRUE );
				}
				else
				{
					$check = get_post_meta( $order_id, '_ldg_gift_processed', TRUE );
				}

				if( !empty( $check ) )
				{
					gb_ldg_log( array( 
						'error' => 'Gift order has been already processed.', 
						'order_id' => $order_id 
					));

					return;
				}

				// initialize mailer to hook emails

				$mailer = WC()->mailer();

				// get sender user id

				$sender_user_id = $order->get_customer_id();

				do_action( 'ldg_after_get_sender_user_id', $sender_user_id );

				// check if order contains subscriptions

				$items = $order->get_items();

				$contains_subscriptions = FALSE;

				foreach( $items as $item )
				{
					$product = $item->get_product();

					// if( is_a( $product, 'WC_Product' ) )
					// {
					// 	return $product->get_type != 'subscription';
					// }

					if(
						class_exists( 'WC_Subscriptions_Product' ) && 
						WC_Subscriptions_Product::is_subscription( $product ) 
					)
					{
						$contains_subscriptions = TRUE;

						break;
					}
				}

				gb_ldg_log( array( 
					'info' => 'Check if order contains_subscriptions or not', 
					'contains_subscriptions' => $contains_subscriptions, 
					'order_id' => $order_id 
				) );

				// send gift note

				do_action( 'ldg_gift_message_notification', $order_id );

				// $gift_email = new WC_Gift_Order_Email();
				// $gift_email->trigger( $order_id );

				// prepare recipient's user names

				$args = array(
					'first_name' 	=> '',
					'last_name'		=> '',
				);

				if( !empty( $gift_last_name ) )
				{
					$args['first_name'] = $gift_name;
					$args['last_name'] = $gift_last_name;
				}
				else
				{
					if( mb_strpos( $gift_name, ' ' ) !== FALSE )
					{
						$gift_name = explode( ' ', $gift_name );

						$args['first_name'] = $gift_name[0];
						$args['last_name'] = $gift_name[1];
					}
					else
					{
						$args['first_name'] = $gift_name;
					}
				}

				// check if recipient's user account exists by email, if not - create

				$recipient_user_id = gb_ldg_maybe_create_new_customer( $gift_email, $args );

				gb_ldg_log( array( 
					'info' => 'Get recipient user id', 
					'recipient_user_id' => $recipient_user_id, 
					'order_id' => $order_id 
				));

				if( !empty( $recipient_user_id ) )
				{
					// set first & last name one more time (overwrite)

					update_user_meta( $recipient_user_id, 'first_name', $args['first_name'] );
					update_user_meta( $recipient_user_id, 'last_name', $args['last_name'] );

					// switch order user id to recipient's user id, grant access and switch back

					$order->set_customer_id( $recipient_user_id );
					$order->save();

					// grant course access
					// TODO: handle scenario when order contains subscriptions + simple products

					$note_label = '';

					// check if LearnDash + WooCommerce add-on is installed

					if( class_exists( 'Learndash_WooCommerce' ) )
					{
						$note_label = 'LearnDash';

						if( !$contains_subscriptions )
						{
							// add course access to recipients account

							Learndash_WooCommerce::add_course_access( $order_id, $recipient_user_id );

							gb_ldg_log( array( 
								'info' => 'Course access granted.', 
								'recipient_user_id' => $recipient_user_id, 
								'order_id' => $order_id 
							));

							// remove course access from buyers account, only if account exists

							if( !empty( $sender_user_id ) )
							{
								Learndash_WooCommerce::remove_course_access( $order_id, $sender_user_id );

								gb_ldg_log( array( 
									'info' => 'Course access removed.', 
									'sender_user_id' => $sender_user_id, 
									'order_id' => $order_id 
								));
							}
						}
						else
						{
							$options = get_option( 'gb_ldg_options' );

							$subscriptions = wcs_get_subscriptions_for_order( $order );

							if( !empty( $subscriptions ) )
							{
								foreach( $subscriptions as $subscription_id => $subscription )
								{
									// add course access to recipients account

									Learndash_WooCommerce::add_subscription_course_access( 
										$subscription,
										array(),
										$recipient_user_id
									);

									gb_ldg_log( array( 
										'info' => 'Subscription course access granted.', 
										'recipient_user_id' => $recipient_user_id, 
										'subscription_id' => $subscription_id 
									));

									// remove course access from buyers account, only if account exists

									if( !empty( $sender_user_id ) )
									{
										Learndash_WooCommerce::remove_subscription_course_access( 
											$subscription,
											array(),
											$sender_user_id
										);

										gb_ldg_log( array( 
											'info' => 'Subscription course access removed.', 
											'sender_user_id' => $sender_user_id, 
											'subscription_id' => $subscription_id 
										));
									}

									// force attribute subscription to gift recipient

									if( !empty( $options['attribute_subscription_to_recipient'] ) )
									{
										$subscription->set_customer_id( $recipient_user_id );

										$subscription->save();
									}
								}
							}
						}

						// unhook default LearnDash WooCommerce purchase events

						remove_action(
							'woocommerce_order_status_processing', 
							array( 'Learndash_WooCommerce', 'add_course_access' ), 
							10
						);

						remove_action(
							'woocommerce_order_status_completed', 
							array( 'Learndash_WooCommerce', 'add_course_access' ), 
							10
						);

						remove_action(
							'woocommerce_payment_complete', 
							array( 'Learndash_WooCommerce', 'add_course_access' ), 
							10
						);

						gb_ldg_log( array( 
							'info' => 'Unhooked default add_course_access actions.', 
							'order_id' => $order_id 
						));
					}
					else
					{
						gb_ldg_log( array( 
							'error' => 'LearnDash WooCommerce add-on is not installed.', 
							'order_id' => $order_id 
						));
					}

					// hook for 3rd party events

					do_action( 'ldg_gift_purchase', $order_id, $order );

					// revert order user

					$order->set_customer_id( $sender_user_id );

					// add order note

					$order->add_order_note(
						'<b>' . $note_label . ' Gift a Course:</b> ' . 
						sprintf(
							__( 'Access for %s was generated.', 'learndash-gift' ),
							'"' . $gift_name . '" (' . $gift_email . ', #' . $recipient_user_id . ')'
						)
					);

					// add post meta

					if( $custom_tables_enabled )
					{
						$order->update_meta_data( '_ldg_gift_processed', 1 );

						// $order->save();
					}
					else
					{
						update_post_meta( $order_id, '_ldg_gift_processed', 1 );
					}

					// update scheduled delivery status

					if(
						!empty( $gift_delivery_datetime ) &&
						!empty( $gift_delivery_status ) &&
						$gift_delivery_status === 'pending'
					)
					{
						if( $custom_tables_enabled )
						{
							$order->update_meta_data( '_gift_delivery_status', 'delivered' );

							// $order->save();
						}
						else
						{
							update_post_meta( $order_id, '_gift_delivery_status', 'delivered' );
						}

						gb_ldg_log( array( 
							'info' => 'Scheduled gift delivery completed.',
							'order_id' => $order_id
						));
					}

					// save order meta

					$order->save();

					// log

					gb_ldg_log( array( 
						'info' => 'Gift process locked for current order.', 
						'order_id' => $order_id 
					));
				}
				else
				{
					gb_ldg_log( array( 
						'error' => 'Recipient user account can not be retrieved.', 
						'gift_email' => $gift_email 
					));
				}
			}
			else
			{
				gb_ldg_log( array( 
					'error' => 'Missing gift name and/or gift email.', 
					'gift_name' => $gift_name, 
					'gift_email' => $gift_email 
				) );
			}
		}
	}
}
add_action( 'woocommerce_order_status_processing', 'gb_ldg_process_purchase', 5, 1 );
add_action( 'woocommerce_order_status_completed', 'gb_ldg_process_purchase', 5, 1 );
add_action( 'woocommerce_payment_complete', 'gb_ldg_process_purchase', 5, 1 );

/**
 * The action that will execute the delayed gift 
 * delivery aka process purchase
 *
 * @since 1.1.0
 */

add_action( 'gb_ldg_scheduled_delivery', 'gb_ldg_process_purchase' );

/**
 * Display gift details on the edit order page
 *
 * @since 1.0.0
 */
 
function gb_ldg_display_admin_order_meta( $order )
{
	$order_id = $order->get_id();

	$gift_purchase = get_post_meta( $order_id, '_gift_purchase', TRUE );

	if( !empty( $gift_purchase ) )
	{

		
		
		
		$gift_name = get_post_meta( $order_id, '_gift_name', TRUE );
		$gift_last_name = get_post_meta( $order_id, '_gift_last_name', TRUE );
		$gift_email = get_post_meta( $order_id, '_gift_email', TRUE );
		$gift_note = get_post_meta( $order_id, '_gift_note', TRUE );
		$gift_delivery_datetime = get_post_meta( $order_id, '_gift_delivery_datetime', TRUE );
		$gift_delivery_status = get_post_meta( $order_id, '_gift_delivery_status', TRUE ); // pending, delivered

		$gift_delivery_scheduled = ( 
			wp_next_scheduled( 'gb_ldg_scheduled_delivery', array( $order_id ) ) ? 
			__( 'scheduled', 'learndash-gift' ) : 
			__( 'not scheduled', 'learndash-gift' )
		);

		if( !empty( $gift_delivery_status ) && $gift_delivery_status === 'pending' )
		{
			$gift_delivery_status .= ' (' . $gift_delivery_scheduled . ')';
		}

		if( empty( $gift_note ) ) $gift_note = '';

		if( !empty( $gift_last_name ) )
		{
			$gift_name .= ' ' . $gift_last_name;
		}

		echo '<p><strong>' . 
				__( 'Purchased as a gift', 'learndash-gift' ) . ':</strong> Yes' .
			'</p>';

		echo '<p><strong>' . 
				__( 'Recipient\'s name', 'learndash-gift' ) . ':</strong> ' .
				$gift_name . 
			'</p>';

		echo '<p><strong>' . 
				__( 'Recipient\'s email', 'learndash-gift' ) . ':</strong> ' .
				$gift_email .
			'</p>';

		echo '<p><strong>' . 
				__( 'Gift message', 'learndash-gift' ) . ':</strong> ' .
				$gift_note .
			'</p>';

		if( $gift_delivery_datetime ) {

			echo '<p><strong>' . 
					__( 'Gift delivery scheduled at', 'learndash-gift' ) . ':</strong> ' .
					$gift_delivery_datetime .
				'</p>';

			echo '<p><strong>' . 
					__( 'Gift delivery status', 'learndash-gift' ) . ':</strong> ' .
					$gift_delivery_status .
				'</p>';
		}
    }
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'gb_ldg_display_admin_order_meta', 10, 1 );

/**
 * Add a custom email to the list of emails WooCommerce should load
 *
 * @since 1.0.0
 */

function gb_ldg_gift_order_woocommerce_email( $email_classes )
{
    require_once( plugin_dir_path( __FILE__ ) . 'includes/class-wc-gift-order-email.php' );

    $email_classes['WC_Gift_Order_Email'] = new WC_Gift_Order_Email();

    return $email_classes;
}
add_filter( 'woocommerce_email_classes', 'gb_ldg_gift_order_woocommerce_email' );

/**
 * Feed gift order email template from plugin folder instead of 
 * default WooCommerce location
 * 
 * @since 1.0.0
 */

function gb_ldg_gift_order_woocommerce_get_template( $template, $template_name, $template_path )
{
	if( 
		$template_name === 'emails/gift-message.php' ||  
		$template_name === 'emails/plain/gift-message.php'
	)
	{
		$template = $template_path . $template_name;

		// if template exists in theme folder, override

		$theme_path = get_template_directory() . '/woocommerce/';

		if( file_exists( $theme_path . $template_name ) )
		{
			$template = $theme_path . $template_name;
		}
	}

	return $template;
}
add_filter( 'woocommerce_locate_template', 'gb_ldg_gift_order_woocommerce_get_template', 999, 3 );

/**
 * HELPER: Wrapper for wc_create_new_customer function
 *
 * @since 1.0.0
 */

function gb_ldg_create_new_customer( $email = '', $args = array() )
{
    if( empty( $email ) )
    {
        return FALSE;
    }

    $username = sanitize_user( current( explode( '@', $email ) ), TRUE );

    // ensure username is unique

    $append = 1;
    $o_username = $username;

    while( username_exists( $username ) )
    {
        $username = $o_username . $append;

        ++$append;
    }

    // $password = wp_generate_password();

	add_filter( 'option_woocommerce_registration_generate_password', 'gb_ldg_force_generate_password_option' );

    $user_id = wc_create_new_customer( $email, $username, $password = '', $args );

    remove_filter( 'option_woocommerce_registration_generate_password', 'gb_ldg_force_generate_password_option' );

    // for custom email notifications

    do_action( 'ldg_after_create_new_customer', $user_id, $username );

    return $user_id;
}

/**
 * HELPER: Force generate password option in WooCommerce
 *
 * @since 1.0.0
 */

function gb_ldg_force_generate_password_option()
{
	return 'yes';
}

/**
 * HELPER: Create new customer account if it doesn't exist
 *
 * @since 1.0.0
 */

function gb_ldg_maybe_create_new_customer( $email = '', $args = array() )
{
    $email = trim( $email );

    $email_exists = email_exists( $email );

    if( $email_exists )
    {
        $user_id = $email_exists;
    }
    else
    {
        $user_id = gb_ldg_create_new_customer( $email, $args );
    }

    return $user_id;
}

/**
 * HELPER: Simple logging function, useful to debug
 *
 * @since 1.0.0
 */

function gb_ldg_log( $data = array(), $file = 'log.txt' )
{
    if( empty( $file ) )
    {
        $file = 'log.txt';
    }

	$options = get_option( 'gb_ldg_options' );

    if( empty( $options['enable_logging'] ) )
    {
    	return;
    }

    if( !empty( $data ) )
    {
        $data[ '_hook' ] = current_action();
        $data[ '_time' ] = date( 'H:i:s d.m.y' );

        return @file_put_contents( dirname( __FILE__ ) . '/' . $file, json_encode( $data ) . PHP_EOL . PHP_EOL, FILE_APPEND );
    }
}
