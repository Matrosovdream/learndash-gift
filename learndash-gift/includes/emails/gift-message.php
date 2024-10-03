<?php
/**
 * Gift message email HTML
 *
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php 

	// if custom message available, send it

    if( !empty( $custom_message ) )
    {
    	// prepare order items list

        $output_links = apply_filters( 'gb_ldg_custom_message_item_list_output_links', TRUE );

        $custom_message_list = '<ul>';

		foreach( $order->get_items() as $item )
		{
		    $product = $item->get_product();

            $custom_message_list .= '<li>';

            if( $output_links === TRUE )
            {
                $custom_message_list .= '<a href="' . esc_url( get_permalink( $product->get_id() ) ) . '">';
            }

		    $custom_message_list .= wp_kses_post( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, FALSE ) );

            if( $output_links === TRUE )
            {
                $custom_message_list .= '</a>';
            }

            $custom_message_list .= '</li>';
		}

    	$custom_message_list .= '</ul>';   

    	// prepare gift note

    	$custom_gift_note = '<blockquote>' . wpautop( wptexturize( $gift_note ) ) . '</blockquote>';

    	// add paragraphs

    	$custom_message = wpautop( $custom_message );

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

?>

<p><?php printf( esc_html__( 'Hi %s,', 'learndash-gift' ), esc_html( $gift_name ) ); ?></p>
<p><?php printf( esc_html__( '%s has sent you this gift:', 'learndash-gift' ), esc_html( $order->get_billing_first_name() ) ); ?></p>

<ul><?php

    foreach( $order->get_items() as $item )
    {
        $product = $item->get_product();

        echo '<li><a href="' . esc_url( get_permalink( $product->get_id() ) ) . '">' . wp_kses_post( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, FALSE ) ) . '</a></li>';
    }

?></ul>

<p><?php esc_html_e( 'The following note has been sent along with this gift:', 'learndash-gift' ); ?></p>

<blockquote><?php echo wpautop( wptexturize( $gift_note ) ); ?></blockquote><?php // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped ?>

<p><?php esc_html_e( 'You will receive the access to your gift shortly after this message.', 'learndash-gift' ); ?></p>

<p><?php esc_html_e( 'Cheers!', 'learndash-gift' ); ?></p>
<?php

	}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
