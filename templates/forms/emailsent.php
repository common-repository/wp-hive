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

    <?php elseif ( isset( $_SESSION['wphive_downloads_sent'][$form_id] ) ): ?>

    <h3>Your download has been emailed to you &raquo;</h3>

    <p>
        Click here for a direct download:
        <a href="<?php echo add_query_arg( array( 'wphive-download' => $form_id ), get_permalink() ); ?>" target=" _blank"><?php echo $title; ?> </a>
    </p>

    <?php else: ?>

    <h3>Your download is ready &raquo;</h3>

    <p>
        Click here to receive <?php echo $title; ?> by email:<br/>
        <a href="<?php echo add_query_arg( array( 'wphive-email-a' => $form_id ), get_permalink() ); ?>">Send File As Email Attachment</a>
        or
        <a href="<?php echo add_query_arg( array( 'wphive-email-l' => $form_id ), get_permalink() ); ?>">Send Link To File By Email</a>
    </p>

    <?php endif; ?>

</div>
