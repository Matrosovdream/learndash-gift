<?php

if ( ! defined( 'ABSPATH' ) ) die();

/**
 * A custom Gift Message WooCommerce Email class
 *
 * @since 1.0.0
 * @extends \WC_Email
 */

class WC_Gift_Order_Email extends WC_Email {

    /**
     * Gift recipient name.
     *
     * @var string
     */

    public $gift_name;

    /**
     * Gift recipient last name (optional)
     *
     * @var string
     */

    public $gift_last_name;

    /**
     * Gift note.
     *
     * @var string
     */

    public $gift_note;

    /**
     * Constructor.
     */

    public function __construct()
    {
        $this->id             = 'gift_message';
        $this->customer_email = TRUE;
        $this->title          = __( 'Gift message', 'learndash-gift' );
        $this->description    = __( 'Gift message emails are sent to a gift recipient, containing a gift message, when customer (gift sender) places a gift order.', 'learndash-gift' );
        $this->template_html  = 'emails/gift-message.php';
        $this->template_plain = 'emails/plain/gift-message.php';
        $this->placeholders   = array(
            '{site_title}'      => $this->get_blogname(),
            '{sender_name}'     => '',
            '{order_date}'      => '',
        );

        $this->custom_message = FALSE;

        add_action( 'ldg_gift_message_notification', array( $this, 'trigger' ) );

        parent::__construct();
    }

    /**
     * Get email subject.
     *
     * @since  3.1.0
     * @return string
     */

    public function get_default_subject()
    {
        return __( '{sender_name} sent you a gift on {site_title}!', 'learndash-gift' );
    }

    /**
     * Get email heading.
     *
     * @since  3.1.0
     * @return string
     */

    public function get_default_heading()
    {
        return __( 'You\'ve received a gift', 'learndash-gift' );
    }

    /**
     * Trigger.
     *
     * @param int $order_id Order ID
     */

    public function trigger( $order_id )
    {
        $already_sent = TRUE;

        $this->setup_locale();

        if( !empty( $order_id ) )
        {
            $this->object = wc_get_order( $order_id );

            if( $this->object )
            {
                $already_sent                        = get_post_meta( $order_id, '_ldg_gift_message_sent', TRUE );

                $this->recipient                     = get_post_meta( $order_id, '_gift_email', TRUE );
                $this->gift_name                     = get_post_meta( $order_id, '_gift_name', TRUE );
                $this->gift_last_name                = get_post_meta( $order_id, '_gift_last_name', TRUE );
                $this->gift_note                     = get_post_meta( $order_id, '_gift_note', TRUE );

                $this->placeholders['{sender_name}'] = $this->object->get_billing_first_name();
                $this->placeholders['{order_date}']  = wc_format_datetime( $this->object->get_date_created() );

                $options = get_option('gb_ldg_options');

                if( !empty( $options['custom_message'] ) )
                {
                    $this->custom_message = $options['custom_message'];
                }
            }
        }

        if( $this->is_enabled() && $this->get_recipient() )
        {
            if( empty( $already_sent ) )
            {
                $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

                update_post_meta( $order_id, '_ldg_gift_message_sent', 1 );
            }
        }

        $this->restore_locale();
    }

    /**
     * Get content html.
     *
     * @return string
     */

    public function get_content_html()
    {
        $template = wc_get_template_html(
            $this->template_html, 
            array(
                'order'          => $this->object,
                'email_heading'  => $this->get_heading(),
                'gift_name'      => $this->gift_name,
                'gift_last_name' => $this->gift_last_name,
                'gift_note'      => $this->gift_note,
                'custom_message' => $this->custom_message,
                'sent_to_admin'  => FALSE,
                'plain_text'     => FALSE,
                'email'          => $this,
            ),
            plugin_dir_path( __FILE__ )
        );

        return $template;
    }

    /**
     * Get content plain.
     *
     * @return string
     */

    public function get_content_plain()
    {
        $template = wc_get_template_html(
            $this->template_plain, 
            array(
                'order'          => $this->object,
                'email_heading'  => $this->get_heading(),
                'gift_name'      => $this->gift_name,
                'gift_last_name' => $this->gift_last_name,
                'gift_note'      => $this->gift_note,
                'custom_message' => $this->custom_message,
                'sent_to_admin'  => FALSE,
                'plain_text'     => TRUE,
                'email'          => $this,
            ),
            plugin_dir_path( __FILE__ )
        );

        return $template;
    }

    /**
     * Initialize settings form fields.
     */

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled'    => array(
                'title'   => __( 'Enable/Disable', 'woocommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable this email notification', 'woocommerce' ),
                'default' => 'yes',
            ),
            'subject'    => array(
                'title'       => __( 'Subject', 'woocommerce' ),
                'type'        => 'text',
                'desc_tip'    => true,
                /* translators: %s: list of placeholders */
                'description' => sprintf( __( 'Available placeholders: %s', 'woocommerce' ), '<code>' . implode( '</code>, <code>', array_keys( $this->placeholders ) ) . '</code>' ),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'    => array(
                'title'       => __( 'Email heading', 'woocommerce' ),
                'type'        => 'text',
                'desc_tip'    => true,
                /* translators: %s: list of placeholders */
                'description' => sprintf( __( 'Available placeholders: %s', 'woocommerce' ), '<code>' . implode( '</code>, <code>', array_keys( $this->placeholders ) ) . '</code>' ),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'email_type' => array(
                'title'       => __( 'Email type', 'woocommerce' ),
                'type'        => 'select',
                'description' => __( 'Choose which format of email to send.', 'woocommerce' ),
                'default'     => 'html',
                'class'       => 'email_type wc-enhanced-select',
                'options'     => $this->get_email_type_options(),
                'desc_tip'    => true,
            ),
        );
    }
}

return new WC_Gift_Order_Email();
