<a name="wphive-form-<?php echo $form_id; ?>"></a>
<div class="wphive-form" id="wphive-form-<?php echo $form_id; ?>">

    <?php if ( 'simple' == $type ): ?>

    <h3>Thanks for submitting your details</h3>

    <p>
        Your email address: <?php echo $email; ?>
        <?php if ( !empty( $email_reset_link ) ): ?>
        (<a href="<?php echo $email_reset_link; ?>">reset</a>)
        <?php endif; ?>
    </p>

    <?php else: ?>

    <h3>Your download is ready &raquo;</h3>

    <p>
        <a href="<?php echo add_query_arg( array( 'wphive-download' => $form_id ), get_permalink() ); ?>" target=" _blank"><?php echo $title; ?> </a>
    </p>

    <?php endif; ?>

</div>
