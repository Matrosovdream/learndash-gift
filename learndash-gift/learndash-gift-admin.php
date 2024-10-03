<?php 
    if( !defined( 'ABSPATH' ) ) die();

    $options = get_option('gb_ldg_options');
?>
<style type="text/css">
.wrap input[type="text"], 
.wrap select, 
.wrap textarea {
    max-width: 400px;
    width: 100%;
}
.wrap textarea {
    min-height: 200px;
}
.wrap p.gb-hint {
    max-width: 400px;
    font-size: 13px;
    font-style: italic;
}
.gb-ldg-input-key {
    min-width: 288px;
}
.gb-ldg-admin-response {
    padding: 10px;
    background: #76F1A7;
    max-width: 600px;
}
.radio_wrap {
    margin-bottom: 5px;
}
</style>
<div class="wrap">
    <h2>
        <span><?php echo GB_LDG_NAME; ?></span>
    </h2>

    <?php // if( !defined( 'GB_BRM_REMOVE' ) ): ?>

    <h3><?php _e( 'Your license', 'learndash-gift' ); ?></h3>

    <?php
        $response = $type = '';

        // check for updates

        if( !empty( $_SERVER['SERVER_NAME'] ) )
        {
            $update = get_option( 'gb_ldg_update_data' );

            // set the key to check or clear it

            if( !empty( $_POST['gb_key'] ) )
            {
                if( $_POST['gb_key'] == 'deactivate' )
                {
                    // send remove domain request, if status == ok

                    if( is_array( $update ) && !empty( $update['status'] ) && $update['status'] == 'ok' )
                    {
                        $remove = wp_remote_get( 'https://bogdanfix.com/cp/api/index.php?r=domain_remove&k=' . $options['key'] . '&p=' . GB_LDG_ID . '&d=' . $_SERVER['SERVER_NAME'] );

                        if( is_array( $remove ) )
                        {
                            $remove = $remove['body']; // use the content

                            $remove = json_decode( $remove, TRUE );
                        }
                    }

                    // erase the key

                    $options['key'] = '';

                    update_option( 'gb_ldg_options', $options );
                }
                else
                {
                    $options['key'] = trim( $_POST['gb_key'] );

                    update_option( 'gb_ldg_options', $options );
                }

                delete_site_transient( 'gb_ldg_update_data' );
            }

            // display license & update data

            if( is_array( $update ) && !empty( $update['status'] ) )
            {
                if(
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
                        $response = '
                            <div>
                                <b>' . sprintf( __( 'Update to %s is available!', 'learndash-gift' ), $update['result']['update_version'] ) . '</b>
                            </div>
                            <br />
                            <div><b>' . __( 'Description:', 'learndash-gift' ) . '</b><br />' . $update_description . '</div>';
                            
                        if( !empty( $update_url ) )
                        {
                            $response .= '<br />
                                <div>
                                    <a href="' . admin_url( 'plugins.php?s=bogdanfix' ) . '" class="button button-primary">' . __( 'Go to Plugins menu to update', 'learndash-gift' ) . '</a>
                                </div>';
                        }
                        else
                        {
                            $response .= '<br />
                                <div>
                                    <a href="https://bogdanfix.com/learndash-gift-woocommerce/?utm_source=ldg-admin" class="button button-primary" target="_blank">' . __( 'Get the license key now', 'learndash-gift' ) . '</a>
                                </div>';
                        }
                    }
                    elseif( $current_version >= $update_version )
                    {
                        $response = __( 'You have the latest version', 'learndash-gift' ) . ' (' . GB_LDG_VER . ')';
                    }

                    // license type

                    if( !empty( $update['result']['type'] ) && mb_strpos( $update['result']['type'], '99' ) > 0 )
                    {
                        $type = 'pro';
                    }
                }
                elseif( 
                    $update['status'] == 'error' && 
                    !empty( $update['error'] ) && 
                    !empty( $update['error_text'] ) 
                )
                {
                    $response = $update['error_text'];
                }
                else
                {
                    $response = __( 'Undefined error. Please, contact plugin support.', 'learndash-gift' );
                }
            }
            else
            {
                $response = __( 'Undefined server response. Please, contact plugin support.', 'learndash-gift' );
            }
        }
        else
        {
            $response = __( 'Server variable SERVER_NAME is not defined. Please, contact your hosting support.', 'learndash-gift' );
        }

        if( !empty( $response ) )
        {
            echo '<div class="gb-ldg-admin-response">' . $response . '</div>';
        }
    ?>

    <div>
        <table class="form-table">
            
            <tr>
                <th><?php _e( 'License key', 'learndash-gift' ); ?>:</th>
                <td>
                    <form method="POST">
                    <?php
                        if( empty( $options['key'] ) )
                        {
                    ?>
                        <input type="text" name="gb_key" value="" class="gb-ldg-input-key" placeholder="<?php _e( 'Paste your key here...', 'learndash-gift' ); ?>" />&nbsp;
                        <input type="submit" name="submit" class="button button-primary" value="<?php _e( 'Activate', 'learndash-gift' ); ?>">
                    <?php
                        }
                        else
                        {
                    ?>
                        <input type="hidden" name="gb_key" value="deactivate" />
                        <input type="submit" name="submit" class="button" value="<?php _e( 'Remove key', 'learndash-gift' ); ?>">
                    <?php
                        }
                    ?>
                    </form>
                </td>
            </tr>

        </table>
    </div>

    <?php // endif; ?>

    <h3><?php _e( 'Settings', 'learndash-gift' ); ?></h3>

    <div>
        <form method="POST" action="options.php">

            <?php settings_fields( 'gb_ldg_options' ); ?>

            <input type="hidden" name="gb_ldg_options[key]" value="<?php if( !empty( $options['key'] ) ) echo $options['key']; ?>" />

            <table class="form-table">

                <tr>
                    <th><?php _e( 'Customize gift message', 'learndash-gift' ); ?>:</th>
                    <td>
                        <div>

                            <textarea name="gb_ldg_options[custom_message]" placeholder="Hi [recipient_name], ..."><?php if( !empty( $options['custom_message'] ) ) echo esc_textarea( $options['custom_message'] ); ?></textarea>

                            <p class="gb-hint"><?php _e( 'Line breaks will turn into paragraphs. Available shortcodes', 'learndash-gift' ); ?>: [recipient_name], [recipient_last_name], [sender_name], [items_list], [gift_note]</p>

                        </div>
                    </td>
                </tr>

                <tr>
                    <th><?php _e( 'Hide all gift fields if there are no courses in cart', 'learndash-gift' ); ?>:</th>
                    <td>
                        <div>

                            <input 
                                type="checkbox" 
                                name="gb_ldg_options[hide_gift_fields]"
                                value="1" 
                                <?php if( !empty( $options[ 'hide_gift_fields' ] ) )
                                {
                                    checked( $options[ 'hide_gift_fields' ], '1' );
                                } ?> /> <?php _e( 'yes', 'learndash-gift' ); ?>

                            <p class="gb-hint"><?php _e( 'Checkbox "Purchase as a gift" and all fields will not be shown on checkout if there are no courses added to the cart.', 'learndash-gift' ); ?></p>

                        </div>
                    </td>
                </tr>

                <tr>
                    <th><?php _e( 'Enable last name field', 'learndash-gift' ); ?>:</th>
                    <td>
                        <div>

                            <input 
                                type="checkbox" 
                                name="gb_ldg_options[enable_last_name]"
                                value="1" 
                                <?php if( !empty( $options[ 'enable_last_name' ] ) )
                                {
                                    checked( $options[ 'enable_last_name' ], '1' );
                                } ?> /> <?php _e( 'yes', 'learndash-gift' ); ?>

                            <p class="gb-hint"><?php _e( 'If you want to have a distinct "Last name" field, enable this option.', 'learndash-gift' ); ?></p>

                        </div>
                    </td>
                </tr>

                <tr>
                    <th><?php _e( 'Attribute gift subscription ownership to recipient', 'learndash-gift' ); ?>:</th>
                    <td>
                        <div>

                            <input 
                                type="checkbox" 
                                name="gb_ldg_options[attribute_subscription_to_recipient]"
                                value="1" 
                                <?php if( !empty( $options[ 'attribute_subscription_to_recipient' ] ) )
                                {
                                    checked( $options[ 'attribute_subscription_to_recipient' ], '1' );
                                } ?> /> <?php _e( 'yes', 'learndash-gift' ); ?>

                            <p class="gb-hint"><?php _e( 'Enable this option to make recipient the owner of the gifted subscription instead of the buyer.', 'learndash-gift' ); ?></p>

                        </div>
                    </td>
                </tr>

                <tr>
                    <th><?php _e( 'Enable scheduled gift delivery', 'learndash-gift' ); ?>:</th>
                    <td>
                        <div>

                            <input 
                                type="checkbox" 
                                name="gb_ldg_options[enable_scheduled_delivery]"
                                value="1" 
                                <?php if( !empty( $options[ 'enable_scheduled_delivery' ] ) )
                                {
                                    checked( $options[ 'enable_scheduled_delivery' ], '1' );
                                } ?> /> <?php _e( 'yes', 'learndash-gift' ); ?>

                            <p class="gb-hint"><?php _e( 'If you want to allow gift buyers to pick date and time for a future gift delivery, enable this option.', 'learndash-gift' ); ?></p>

                        </div>
                    </td>
                </tr>

                <tr>
                    <th><?php _e( 'Enable logging', 'learndash-gift' ); ?>:</th>
                    <td>
                        <div>

                            <input 
                                type="checkbox" 
                                name="gb_ldg_options[enable_logging]"
                                value="1" 
                                <?php if( !empty( $options[ 'enable_logging' ] ) )
                                {
                                    checked( $options[ 'enable_logging' ], '1' );
                                } ?> /> <?php _e( 'yes', 'learndash-gift' ); ?>

                        </div>
                    </td>
                </tr>

            </table>

            <div>
                <?php submit_button(); ?>
            </div>
            
        </form>
    </div>

</div>