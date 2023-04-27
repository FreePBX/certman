<?php
if(!empty($message)) {
	$loghtml = '';
	$hinthtml = '';
	if (!empty($message['hints'])) {
		$hinthtml = '<ul>';
		foreach($message['hints'] as $hint) {
			$hinthtml .= '<li>' . $hint . '</li>';
		}
		$hinthtml .= '</ul>';
	}
	if(!empty($message['log']) && $message['log'] != '') {
		$loghtml = '<pre class="alert-' . $message['type'] .' pre-scrollable" style="overflow-x: auto; white-space: pre-wrap; word-wrap: normal; overflow-wrap: normal;">' . $message['log'] . '</pre>';
	}

	$mhtml = '<div class="fpbx-container element-container alert alert-' . $message['type'] .' alert-dismissable">
			<div class="display no-border" >
				<div class="row">
					<div class="col-md-11">';
	if(!empty($message['title'])) {
		$mhtml .=			'<label style="font-size: large;" class="mb-0">'.$message['title'].'</label>
						<i class="fpbx-help-icon" data-for="alert">
							<span class="alert-' . $message['type'] .' align-top fa fa-question-circle"></span>
						</i>
						<span id="alert-help" class="fpbx-help-block">
						<div class="alert-' . $message['type'] . '">
							<strong>' . $message['message'] . '</strong>' . $hinthtml . $loghtml . '
						</div></span>';
	} else {
		$mhtml .=			'<label style="font-size: large;">'.$message['message'].'</label>' . $hinthtml . $loghtml;
	}
	$mhtml .=			'</div>
					<div>
						<span class="fa fa-times close" style="margin-top: -13px; position: absolute; right: 5px;" data-dismiss="alert" aria-hidden="true"></span>
					</div>
				</div>
			</div>
		</div>
	';
}
?>
<div class="container-fluid">
	<h1><?php echo _('Certificate Management')?></h1>

	<div class="panel panel-info">
		<div class="panel-heading">
			<div class="panel-title">
				<a href="#" data-toggle="collapse" data-target="#moreinfo"><i class="fa fa-info-circle"></i></a>&nbsp;&nbsp;&nbsp;<?php echo _("What is Certificate Manager?")?></div>
		</div>
		<!--At some point we can probably kill this... Maybe make is a 1 time panel that may be dismissed-->
		<div class="panel-body collapse" id="moreinfo">
			<p><?php echo _("Certificate Manager manages certificates for secure calling (TLS/SRTP), secure web sessions (HTTPS/WEBRTC[WSS] and more")?></p>
			<p><?php echo _("From this interface you can generate a Certificate Signing Request (CSR) which you can then use to issue a certificate to use for this server")?></p>
			<p><?php echo sprintf(_("Additionally if you have opened internet access up to the outside world you can signup for a FREE certificate from the Let's Encrypt project. Learn more %s"),'<a href="https://letsencrypt.org/">'._("Here").'</a>')?></p>
			<p><?php echo sprintf(_("To manually import certificate files place them into %s and make sure they have the same basename, EG: %s"),$location,"mycert.key, mycert.crt")?></p>
			<p><?php echo _("Optionally upload existing certificate information through the web interface.")?></p>
			<p><?php echo _("A Self-Signed Certificate has been generated for you on install. You can use this certificate now to get started however we strongly urge you to get a real certificate from a standard authority or through Let's Encrypt")?></p>
		</div>
	</div>
	<?php echo !empty($mhtml) ? $mhtml : "" ?>
	<div class = "display no-border">
		<?php echo load_view(__DIR__.'/certgrid.php',array('certs' => $certs, 'csr' => $csr, 'ca' => $ca)); ?>
		<i><?php echo _("Hover over the 'Default' column and click to make a certificate the system default")?></i>
		<p><strong><?php echo _("Note:")?></strong> <?php echo sprintf(_("Making a certificate the 'default' changes certificate settings in Advanced Settings ONLY. It will force said certificate to be the default for options in Advanced Settings that require certificates. It will also place a standard set of the certificate and it's key into %s for use by other applications"),$location."/integration")?></p>
	</div>
</div>
