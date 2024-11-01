<div id="wphive-leadview-overview" style="display:none;">

  <h1>Contact Details &ndash; Overview</h1>

  <table border="0" cellpadding="5">

  <?php if ( !empty( $lead->name ) ): ?>
  <tr>
    <td align="right">Name:</td>
    <td><?php echo $lead->name; ?></td>
  </tr>
  <?php endif; ?>

  <?php if ( !empty( $lead->xprofile['demographics']['age'] ) ): ?>
  <tr>
    <td align="right">Age:</td>
    <td><?php echo $lead->xprofile['demographics']['age']; ?></td>
  </tr>
  <?php elseif ( !empty( $lead->xprofile['demographics']['ageRange'] ) ): ?>
  <tr>
    <td align="right">Age Range:</td>
    <td><?php echo $lead->xprofile['demographics']['ageRange']; ?></td>
  </tr>
  <?php endif; ?>

  <?php if ( !empty( $lead->xprofile['demographics']['gender'] ) ): ?>
  <tr>
    <td align="right">Gender:</td>
    <td><?php echo $lead->xprofile['demographics']['gender']; ?></td>
  </tr>
  <?php endif; ?>

  <?php if ( !empty( $lead->xprofile['demographics']['locationGeneral'] ) ): ?>
  <tr>
    <td align="right">Location:</td>
    <td><?php echo $lead->xprofile['demographics']['locationGeneral']; ?></td>
  </tr>
  <?php endif; ?>

  <?php if ( !empty( $lead->email ) ): ?>
  <tr>
    <td align="right">Email:</td>
    <td><?php echo '<a href="mailto:' . $lead->email . '">' . $lead->email . '</a>'; ?></td>
  </tr>
  <?php endif; ?>

  <?php if ( !empty( $lead->country_cc ) && !empty( $lead->country ) ): ?>
  <tr>
    <td align="right">Country (first visit):</td>
    <td><div class="f16"><span class="flag <?php echo strtolower( $lead->country_cc ); ?>" title="<?php echo $lead->country; ?>"></span>&nbsp;<?php echo $lead->country; ?></div></td>
  </tr>
  <?php endif; ?>

  <?php if ( !empty( $lead->referer ) ): ?>
  <tr>
    <td align="right">HTTP Referer (first visit):</td>
    <td><?php echo $lead->referer; ?></td>
  </tr>
  <?php endif; ?>

  <?php if ( is_array( $lead->xprofile['contactInfo']['websites'] ) ): ?>
  <tr>
    <td align="right" valign="top">Websites:</td>
    <td>
      <?php foreach ( $lead->xprofile['contactInfo']['websites'] as $website ) : ?>
      <?php echo '<a href="' . $website['url'] . '" target="_blank">' . $website['url'] . '</a>'; ?><br />
      <?php endforeach; ?>
    </td>
  </tr>
  <?php endif; ?>

  <?php if ( is_array( $lead->xprofile['socialProfiles'] ) ): ?>
  <tr>
    <td align="right" valign="top">Social Profiles:</td>
    <td>
      <?php foreach ( $lead->xprofile['socialProfiles'] as $social ) : ?>
      <?php echo '<a href="' . $social['url'] . '" target="_blank">' . $social['typeName'] . '</a>'; ?><br />
      <?php endforeach; ?>
    </td>
  </tr>
  <?php endif; ?>

  <?php if ( count( $lead->subscriptions ) > 0 ): ?>
  <?php
      $ml_stats_url = null;
      $emailuser = $lead->emailuser();
      if ( is_array( $emailuser ) && !empty( $emailuser['user_id'] ) ) {
          $ml_stats_url = admin_url( 'admin.php?page=wysija_subscribers&id=' . $emailuser['user_id'] . '&action=edit' );
      }
  ?>
  <tr>
    <td align="right" valign="top">
      Mailing List Subscriptions:
      <?php if ( !empty( $ml_stats_url ) ): ?>
      <br /><a href="<?php echo $ml_stats_url; ?>" target="_blank">&raquo; Mailing List Stats</a>
      <?php endif; ?>
    </td>
    <td>
      <?php foreach ( $lead->subscriptions as $subscription ) : ?>
      <?php echo $subscription; ?><br />
      <?php endforeach; ?>
    </td>
  </tr>
  <?php endif; ?>

  <?php if ( count( $lead->tags ) > 0 ): ?>
  <tr>
    <td align="right" valign="top">Tags:</td>
    <td>
      <?php foreach ( $lead->tags as $tag ) : ?>
      <?php echo $tag; ?><br />
      <?php endforeach; ?>
    </td>
  </tr>
  <?php endif; ?>

  </table>

</div>

<div id="wphive-leadview-history" style="display:none;">

  <h1>Timeline</h1>
  <br />

  <div id="wphive-leadview-timeline"></div>

</div>

<div id="wphive-leadview-container">

  <div id="wphive-leadview-left" style="width:300px; padding:10px; padding-left:20px; background-color:#E8E8E8; float:left;">
    <h1><?php echo ( !empty( $lead->name ) ) ? $lead->name : $lead->email; ?></h1>
    <?php if ( !empty( $lead->xprofile['photo'] ) ): ?>
    <img src="<?php echo $lead->xprofile['photo']; ?>" alt="User Photo" style="max-width:120px; max-height:120px" />
    <?php endif; ?>
    <br /><br />
    <strong>Contact Details</strong>
    <ul>
      <li><a href="#wphive-leadview-overview" onclick="javascript:wphive_leadview('overview');" >Overview</a></li>
      <li><a href="#wphive-leadview-history" onclick="javascript:wphive_leadview('history');">Timeline</a></li>
    </ul>
    <br />
    <strong>Contact Research</strong>
    <ul>
      <li><a href="https://google.com/search?q=<?php echo urlencode( $lead->name ); ?>" target="_blank">Search in Google</a></li>
      <li><a href="http://www.linkedin.com/pub/dir/?first=<?php echo urlencode( $lead->firstname ); ?>&amp;last=<?php echo urlencode( $lead->lastname ); ?>&amp;search=Go" target="_blank">Search in LinkedIn</a></li>
    </ul>
    <br />
    <strong>Statistics</strong>
    <ul>
      <li>&bull; First Interaction: <strong><?php echo ( 0 == $lead->stats['first'] ) ? '<em>never<em>' : date( 'M d, Y', $lead->stats['first'] ); ?></strong></li>
      <li>&bull; Last Interaction: <strong><?php echo ( 0 == $lead->stats['latest'] ) ? '<em>never<em>' : date( 'M d, Y', $lead->stats['latest'] ); ?></strong></li>
      <li>&bull; Interaction Count: <strong><?php echo $lead->stats['count']; ?></strong></li>
      <li>&bull; Profile Completion: <strong><?php echo $lead->completion( 'pct_string' ); ?></strong></li>
      <li>&bull; Lead Score: <strong><?php echo $lead->stats['score']; ?></strong></li>
    </ul>
  </div>

  <div id="wphive-leadview-right" style="width:650px; padding:20px; float:left; overflow:auto;">
    <div id="wphive-leadview-right-content"></div>
  </div>

</div>

<script type="text/javascript">
//<![CDATA[
  function wphive_leadview( view ) {
    if ( 'overview' == view ) {
      content = jQuery('div#wphive-leadview-overview').html();
      jQuery('div#wphive-leadview-right-content').html(content);
    }
    if ( 'history' == view ) {
      content = jQuery('div#wphive-leadview-history').html();
      jQuery('div#wphive-leadview-right-content').html(content);
      var timelinedata = <?php echo $lead->timeline(); ?>;
      if ( timelinedata.length > 0 ) {
        jQuery('div#wphive-leadview-timeline').verticalTimeline({
          defaultDirection: 'newest',
          defaultExpansion: 'collapsed',
          data: timelinedata
        });
      } else {
        jQuery('div#wphive-leadview-timeline').html('<p><em>No interactions yet!</em></p>');
      }
    }
    return false;
  }
  jQuery(document).ready(function($){
    wphive_leadview('overview');
  });
//]]>
</script>
