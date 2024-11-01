<a name="wphive-form-<?php echo $form_id; ?>"></a>
<div class="wphive-form" id="wphive-form-<?php echo $form_id; ?>">

    <h3>Enter your name and email address to download <em><?php echo $title; ?></em></h3>

    <?php if ( !empty( $message ) ): ?>
    <p class="message error"><?php echo $message; ?></p>
    <?php endif; ?>

    <form action="<?php the_permalink(); ?>" method="post" name="capture_<?php echo $form_id; ?>">
        <?php wp_nonce_field( 'wphive_forms_submit_form' ); ?>
        <input type="hidden" name="wphive_forms_action" value="submit_form"/>
        <input type="hidden" name="form_id" value="<?php echo $form_id; ?>"/>
        <table>
            <tr>
                <td>Name:</td>
                <td><input class="input-text" size="30" type="text" id="name" name="name" value="<?php echo $name; ?>"/></td>
            </tr>
            <tr>
                <td>Email:</td>
                <td><input class="input-text" size="30" type="text" name="email" value="<?php echo $email; ?>"/></td>
            </tr>
            <tr>
                <td colspan="2"><?php echo $button_html; ?></td>
            </tr>
        </table>
    </form>

</div>
