<?php

$login_url    = wp_login_url() . '?redirect_to=' . urlencode( get_permalink( $post->ID ) );
$register_url = wp_login_url() . '?action=register&redirect_to=' . urlencode( get_permalink( $post->ID ) );

?>
<a name="wphive-form-<?php echo $form_id; ?>"></a>
<div class="wphive-form" id="wphive-form-<?php echo $form_id; ?>">
    <h3>You must be logged in to download <em><?php echo $title; ?></em></h3>
    <p>
        <a href="<?php echo $login_url; ?>">Login here</a> or
        <a href="<?php echo $register_url; ?>">click here to register</a>.
    </p>
</div>
