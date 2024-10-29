<div class="abprotector_configuration_wrapper">

    <?php include ('top_header.php'); ?>

    <br><br>

    <div class="step_container with_line">
        <span class="step_label">1</span>
        <div class="fs18 mbottom20">
            Step 1: Subscribe to get the API keys
        </div>
        <a href="<?php echo esc_attr($auth_url) ?>" class="chp_btn btn_teal btn_lg" target="_blank">
            Go to create API Keys
        </a>
    </div>

    <div class="step_container with_line">
        <span class="step_label">2</span>
        <div class="fs18 mbottom20">
            Step 2: Enter API keys to connect your website to Abandonment Protector
        </div>

        <form method="post" action="options.php">
            <?php
                settings_fields( 'abprotector_settings' );
                do_settings_sections( 'abprotector_admin_settings' );
            ?>

            <div class="mtop20">
                <button class="chp_btn btn_teal btn_change_abprotector_keys">Connect</button>
            </div>
        </form>
    </div>
</div>