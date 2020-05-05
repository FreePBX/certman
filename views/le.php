<script type='text/javascript' src='modules/certman/assets/js/views/regions.js?123'></script>
<?php 
if(!empty($message)) {
	$messagehtml = '<div class="alert alert-' . $message['type'] .'">'. $message['message'] . '</div>';
}

$alert = "<div class='alert alert-info'><h3>"._("Important")."</h3>";
$alert .= "<p>"._("Let's Encrypt creation and validation requires unrestricted inbound http access on port 80 to the Let's Encrypt token directories. For more information see:")." </p>";
$alert .= "<a href='https://wiki.freepbx.org/display/FPG/Certificate+Management+User+Guide'>https://wiki.freepbx.org/display/FPG/Certificate+Management+User+Guide</a>";
$alert .= "</div>";
?>
<div class="container-fluid">
	<h1><?php echo !empty($cert['cid']) ? _("Edit Let's Encrypt Certificate") : _("New Let's Encrypt Certificate")?></h1>
	<?php echo !empty($messagehtml) ? $messagehtml : "" ?>
	<?php echo $alert; ?>
	<div class='alert alert-info'><?php printf(_("Let's Encrypt Certificates are <strong>automatically</strong> updated by %s when required (Approximately every 2 months). Do not install your own certificate updaters!"), \FreePBX::Config()->get("DASHBOARD_FREEPBX_BRAND")); ?></div>
	<div class = "display full-border">
		<div class="row">
			<div class="col-sm-12">
				<div class="fpbx-container">
					<div class="display full-border" id='certpage'>
						<form class="fpbx-submit" name="frm_certman" action="config.php?display=certman" method="post" enctype="multipart/form-data" data-fpbx-delete="config.php?display=certman&amp;certaction=delete&amp;type=cert&amp;id=<?php echo $cert['cid']?>">
							<input id="certaction" type="hidden" name="certaction" value="<?php echo !empty($cert['cid']) ? 'edit' : 'add'?>">
							<input id="certtype" type="hidden" name="type" value="le">
							<input id="cid" type="hidden" name="cid" value="<?php echo !empty($cert['cid']) ? $cert['cid'] : ''?>">
							<div class="element-container">
								<div class="row">
									<div class="form-group form-horizontal">
										<div class="col-md-3">
											<label class="control-label" for="host"><?php echo _("Certificate Host Name")?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="host"></i>
										</div>
										<div class="col-md-9">
											<?php if (empty($cert['cid'])) { ?>
												<input type="text" class="form-control" id="host" name="host" placeholder="server.example.com" required value="<?php echo $hostname?>">
											<?php } else { ?>
												<?php echo !empty($cert['basename']) ? $cert['basename'] : ""?>
											<?php } ?>
										</div>
									</div>
									<div class="col-md-12">
										<span id="host-help" class="help-block fpbx-help-block" style=""><?php echo _("This must be the hostname you are requesting a certificate for. LetsEncrypt will validate that the hostname resolves to this machine, and attempt to connect to it.")?></span>
									</div>
								</div>
							</div>
							<div class="element-container">
								<div class="row">
									<div class="form-group form-horizontal">
										<div class="col-md-3">
											<label class="control-label" for="email"><?php echo _("Owners Email")?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="email"></i>
										</div>
										<div class="col-md-9">
											<input type="text" class="form-control" id="email" name="email" placeholder="you@example.com" required value="<?php echo $cert['additional']['email']; ?>">
										</div>
									</div>
									<div class="col-md-12">
										<span id="email-help" class="help-block fpbx-help-block" style=""><?php echo _("This email address is given to Let's Encrypt. It may be used by them if the certificate is approaching expiration and it has not been renewed.")?></span>
									</div>
								</div>
							</div>
							<?php if(!empty($cert['cid'])) { ?>
								<div class="element-container">
									<div class="row">
										<div class="form-group form-horizontal">
											<div class="col-md-3">
												<label class="control-label" for="expires"><?php echo _("Valid Until")?></label>
											</div>
											<div class="col-md-9"> <?php echo \FreePBX::Certman()->getReadableExpiration($certinfo['validTo_time_t']); ?> </div>
										</div>
									</div>
								</div>
								<div class="element-container">
									<div class="row">
										<div class="form-group form-horizontal">
											<div class="col-md-3">
												<label class="control-label" for="cn"><?php echo _("Common Name")?></label>
											</div>
											<div class="col-md-9">
												<?php echo $certinfo['subject']['CN']?>
											</div>
										</div>
									</div>
								</div>
								<?php if(!empty($certinfo['extensions']['certificatePolicies'])) {?>
									<div class="element-container">
										<div class="row">
											<div class="form-group form-horizontal">
												<div class="col-md-3">
													<label class="control-label" for="cp"><?php echo _("Certificate Policies")?></label>
													<i class="fa fa-question-circle fpbx-help-icon" data-for="cp"></i>
												</div>
												<div class="col-md-9">
													<textarea class="form-control" readonly><?php echo $certinfo['extensions']['certificatePolicies']?></textarea>
												</div>
											</div>
											<div class="col-md-12">
												<span id="cp-help" class="help-block fpbx-help-block" style=""><?php echo _('A certificate policy (CP) is a document which aims to state what are the different actors of a public key infrastructure (PKI), their roles and their duties')?></span>
											</div>
										</div>
									</div>
								<?php } ?>
							<?php } ?>
							<!-- Challenge Method -->
							<div class="element-container">
								<div class="row">
									<div class="form-group form-horizontal">
										<div class="col-md-3">
											<label class="control-label" for="challengetype"><?php echo _("Challenge Over")?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="challengetype"></i>
										</div>
										<div class="col-md-9">
											<span class="form-control" disabled><strong>HTTP <?php echo _("(Port 80)"); ?></strong></span>
										</div>
									</div>
									<div class="col-md-12">
										<span id="challengetype-help" class="help-block fpbx-help-block"><?php echo _("LetsEncrypt only supports hostname validation via HTTP on port 80.")?></span>
									</div>
								</div>
							</div>
							<!-- END Challenge Method -->
							<div class="element-container">
								<div class="row">
									<div class="form-group form-horizontal">
										<div class="col-md-3">
											<label class="control-label" for="C"><?php echo _("Country")?></label>
										</div>
										<div class="col-md-9">
<?php 
$country = !empty($cert['additional']['C']) ? $cert['additional']['C'] : "CA"; 
$state = !empty($cert['additional']['ST']) ? $cert['additional']['ST'] : "Ontario";
?>
											<select class="form-control" id="C" name="C" data-current="<?php echo $country; ?>" disabled> </select>
										</div>
									</div>
								</div>
							</div>
							<div class="element-container">
								<div class="row">
									<div class="form-group form-horizontal">
										<div class="col-md-3">
											<label class="control-label" for="st"><?php echo _("State/Province/Region")?></label>
										</div>
										<div class="col-md-9">
											<select class="form-control" id="ST" name="ST" data-current="<?php echo $state; ?>"> </select>
										</div>
									</div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
