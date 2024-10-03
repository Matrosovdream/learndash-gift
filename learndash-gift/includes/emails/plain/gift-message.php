<?php
/**
 * Gift message email PLAIN
 *
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo '= ' . esc_html( $email_heading ) . " =\n\n";

// if custom message available, send it

if( !empty( $custom_message ) )
{
	// prepare order items list

	$custom_message_list = '';

	foreach( $order->get_items() as $item )
	{
	    $product = $item->get_product();

	    $custom_message_list .= '&mdash; ' . wp_kses_post( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, FALSE ) ) . ' ' . esc_url( get_permalink( $product->get_id() ) ) . "\n\n";
	}

	// prepare gift note

	$custom_gift_note = wptexturize( $gift_note );

	// prepare custom message body

	$custom_message = str_replace( 
		array(
			'[recipient_name]',
			'[recipient_last_name]',
			'[sender_name]',
			'[items_list]',
			'[gift_note]',
		), 
		array(
			esc_html( $gift_name ),
			esc_html( $gift_last_name ),
			esc_html( $order->get_billing_first_name() ),
			$custom_message_list,
			$custom_gift_note
		), 
		$custom_message 
	);

	echo $custom_message;
}

// else, send default one

else
{

	/* translators: %s Customer first name */
	echo sprintf( esc_html__( 'Hi %s,', 'learndash-gift' ), esc_html( $gift_name ) ) . "\n\n";
	echo sprintf( esc_html__( '%s has sent you this gift:', 'learndash-gift' ), esc_html( $order->get_billing_first_name() ) ) . "\n\n";

	foreach( $order->get_items() as $item )
	{
	    $product = $item->get_product();

	    echo '&mdash; ' . wp_kses_post( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, FALSE ) ) . ' ' . esc_url( get_permalink( $product->get_id() ) ) . "\n\n";
	}

	echo esc_html__( 'The following note has been sent along with this gift:', 'learndash-gift' ) . "\n\n";

	echo "----------\n\n";

	echo wptexturize( $gift_note ) . "\n\n"; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped

	echo "----------\n\n";

	echo esc_html__( 'You will receive the access to your gift shortly after this message.', 'learndash-gift' ) . "\n\n";

	echo esc_html__( 'Cheers!', 'learndash-gift' ) . "\n\n";

}

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
