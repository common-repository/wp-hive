<a name="wphive-form-<?php echo $form_id; ?>"></a>
<div class="wphive-form" id="wphive-form-<?php echo $form_id; ?>">

    <h3>Enter your email address to download <em><?php echo $title; ?></em></h3>

    <?php if ( !empty( $message ) ): ?>
    <p class="message error"><?php echo $message; ?></p>
    <?php endif; ?>

    <form action="<?php the_permalink(); ?>" method="post" name="capture_<?php echo $form_id; ?>">
        <?php wp_nonce_field( 'wphive_forms_submit_form' ); ?>
        <input type="hidden" name="wphive_forms_action" value="submit_form"/>
        <input type="hidden" name="form_id" value="<?php echo $form_id; ?>"/>
        <input class="input-text" size="50" placeholder="Enter your email address..." type="text" name="email" value="<?php echo $email; ?>"/>
        <?php echo $button_html; ?>
    </form>

</div>
