<div class="abprotector_configuration_wrapper">

    <?php include ('top_header.php'); ?>

    <div class="mtop20 mbottom40">
        <a href="<?php echo esc_url(ABPROTECTOR_APP_URL); ?>/wp/login?shop=<?php echo esc_attr(rawurlencode($shop_domain)); ?>" class="chp_btn btn_teal btn_lg" target="_blank">
            <strong>Go to Abandonment Protector</strong>
        </a>
    </div>

    <div class="step_container fs18">
        <div>
            <strong>Public key:</strong>
            <span><?php echo esc_html(substr($abprotector_keys['api_key'], 0, 7) . "..." . substr($abprotector_keys['api_key'], -5)); ?></span>
        </div>
        <div class="mtop20">
            <strong>Status:</strong>
            <?php if( $keys_validation_status == false ){ ?>
                <span class='msg_api_keys valid'>Connected</span>
            <?php }else{ ?>
                <span class='msg_api_keys invalid'>Invalid API keys, please check you have copied them correctly.</span>
            <?php } ?>
        </div>
        <br>
        <div class="btn_change_keys_wrapper">
            <button class="button-secondary btn_change_abprotector_keys">Change keys</button>
        </div>

        <div id="chp_form_keys_container" style="display: none; margin-top: 10px;">
            <form method="post" action="options.php">
                <?php
                    settings_fields( 'abprotector_settings' );
                    do_settings_sections( 'abprotector_admin_settings' );
                ?>

                <div class="mtop20">
                    <button class="chp_btn btn_teal btn_change_abprotector_keys">Save changes</button>
                    <button class="chp_btn btn_cancel_abprotector_keys fs14">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>