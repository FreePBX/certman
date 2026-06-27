<script type='text/javascript' src='modules/certman/assets/js/views/regions.js?123'></script>
<?php
if(!empty($message)) {
	$messagehtml = '<div class="alert alert-' . $message['type'] .'">'. $message['message'] . '</div>';
}

$alert = "<div class='alert alert-info'><h3>"._("Important")."</h3>";
$alert .= "<p>"._("Let's Encrypt certificate creation and validation requires unrestricted inbound http access on port 80 to the Let's Encrypt token directories")." </p>";
$alert .= "<p>"._("If security is managed by the PBX Firewall module, this process should be automatic. Alternate security methods and external firewalls will require manual configuration.")." </p>";
$alert .= "<p>"._("For more information see: ")."<a href='https://wiki.sangoma.com/display/FPG/Certificate+Management+User+Guide' target='_blank'>https://wiki.sangoma.com/display/FPG/Certificate+Management+User+Guide</a> </p>";
$alert .= "</div>";
?>

<div class="container-fluid">
	<h1><?php echo !empty($cert['cid']) ? _("Edit Let's Encrypt Certificate") : _("New Let's Encrypt Certificate")?></h1>
	<?php echo !empty($messagehtml) ? $messagehtml : "" ?>
	<div class='alert alert-info'><?php echo $alert; printf(_("Let's Encrypt Certificates are <strong>automatically</strong> updated by %s when required (Approximately every 2 months). Do not install your own certificate updaters!"), \FreePBX::Config()->get("DASHBOARD_FREEPBX_BRAND")); ?></div>
	<div class = "display full-border">
		<div class="row">
			<div class="col-sm-12">
				<div class="fpbx-container">
					<div class="display full-border" id='certpage'>
						<form class="fpbx-submit" name="frm_certman" action="config.php?display=certman" method="post" enctype="multipart/form-data" data-fpbx-delete="config.php?display=certman&amp;certaction=delete&amp;type=cert&amp;id=<?php echo $cert['cid'] ?? "" ?>">
							<input id="certaction" type="hidden" name="certaction" value="<?php echo !empty($cert['cid']) ? 'edit' : 'add'?>">
							<input id="certtype" type="hidden" name="type" value="le">
							<input id="cid" type="hidden" name="cid" value="<?php echo !empty($cert['cid']) ? $cert['cid'] : ''?>">

							<!-- Begin Section -->
							<div class="section-title" data-for="edit-cert">
								<h3>
									<i class="fa fa-minus"></i>
									<?php echo !empty($cert['cid']) ? _("Edit Certificate") : _("New Certificate")?>
								</h3>
							</div>
							<div class="section" data-id="edit-cert">
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
												<input type="text" class="form-control" id="email" name="email" placeholder="you@example.com" value="<?php echo $cert['additional']['email'] ?? ""; ?>">
											</div>
										</div>
										<div class="col-md-12">
											<span id="email-help" class="help-block fpbx-help-block" style=""><?php echo _("This email address is given to Let's Encrypt. It may be used by them if the certificate is approaching expiration and it has not been renewed.")?></span>
										</div>
									</div>
								</div>

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

								<!-- Alternative Names -->
								<div class="element-container">
									<div class="row">
										<div class="form-group form-horizontal">
											<div class="col-md-3">
												<label class="control-label" for="SAN"><?php echo _("Alternative Names"); ?></label>
												<i class="fa fa-question-circle fpbx-help-icon" data-for="SAN"></i>
											</div>
											<div class="col-md-9">
												<textarea id="SAN" name="SAN" class="form-control" cols=50 rows=2><?php echo isset($cert['additional']['san'])?implode("\n",$cert['additional']['san']):"";?></textarea>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<span id="SAN-help" class="help-block fpbx-help-block"><?php echo _("List alternate Fully Qualified Domain Names for this certificate, one per line. Names must be resolvable by public DNS and point to this server.")?></span>
										</div>
									</div>
								</div>
								<!-- END Alternative Names -->

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

								<!-- Custom / Private ACME server -->
								<?php
									$acmeUrl = $cert['additional']['acmeUrl'] ?? '';
									$acmeCa  = $cert['additional']['acmeCaBundle'] ?? '';
									$acmeInsecure = !empty($cert['additional']['acmeInsecure']);
								?>
								<div class="element-container">
									<div class="row">
										<div class="form-group form-horizontal">
											<div class="col-md-3">
												<label class="control-label" for="acme_url"><?php echo _("Custom ACME Server URL")?></label>
												<i class="fa fa-question-circle fpbx-help-icon" data-for="acme_url"></i>
											</div>
											<div class="col-md-9">
												<input type="text" class="form-control" id="acme_url" name="acme_url" placeholder="https://acme.internal.example.com/acme/acme/directory" value="<?php echo htmlspecialchars($acmeUrl, ENT_QUOTES); ?>">
											</div>
										</div>
										<div class="col-md-12">
											<span id="acme_url-help" class="help-block fpbx-help-block"><?php echo _("Leave empty to use the public Let's Encrypt service. To use a private/self-hosted ACME server, enter the full ACME <strong>directory</strong> URL (e.g. step-ca: <code>/acme/&lt;provisioner&gt;/directory</code>, Pebble: <code>/dir</code>). Validation still uses the http-01 challenge on port 80.")?></span>
										</div>
									</div>
								</div>

								<div class="element-container">
									<div class="row">
										<div class="form-group form-horizontal">
											<div class="col-md-3">
												<label class="control-label" for="acme_ca"><?php echo _("ACME Server CA Bundle")?></label>
												<i class="fa fa-question-circle fpbx-help-icon" data-for="acme_ca"></i>
											</div>
											<div class="col-md-9">
												<input type="text" class="form-control" id="acme_ca" name="acme_ca" placeholder="/etc/ssl/certs/internal-ca.pem" value="<?php echo htmlspecialchars($acmeCa, ENT_QUOTES); ?>">
											</div>
										</div>
										<div class="col-md-12">
											<span id="acme_ca-help" class="help-block fpbx-help-block"><?php echo _("Optional. Only needed when the custom ACME server presents a TLS certificate signed by a private CA that this server does not already trust. You have three options, pick one: (1) install that CA into the system trust store from the <strong>Installed CAs</strong> page and leave this field empty; (2) enter here the path to a PEM CA bundle that signs the ACME server certificate; or (3) enable <strong>Skip ACME Server TLS Verification</strong> below. Leave empty for the public Let's Encrypt service.")?></span>
										</div>
									</div>
								</div>

								<div class="element-container">
									<div class="row">
										<div class="form-group form-horizontal">
											<div class="col-md-3">
												<label class="control-label" for="acme_insecure"><?php echo _("Skip ACME Server TLS Verification")?></label>
												<i class="fa fa-question-circle fpbx-help-icon" data-for="acme_insecure"></i>
											</div>
											<div class="col-md-9">
												<input type="checkbox" id="acme_insecure" name="acme_insecure" <?php echo ($acmeInsecure ? "checked" : ""); ?>>
											</div>
										</div>
										<div class="col-md-12">
											<span id="acme_insecure-help" class="help-block fpbx-help-block"><?php echo _("Disable TLS certificate verification when connecting to the custom ACME server. Use only for self-signed servers on a trusted network when no CA bundle is available. Ignored for the public Let's Encrypt service.")?></span>
										</div>
									</div>
								</div>
								<!-- END Custom / Private ACME server -->
							</div>
							<!-- END Section -->

							<!-- Begin Section -->
							<?php if(!empty($cert['cid'])) { ?>
								<div class="section-title" data-for="show-cert">
									<h3>
										<i class="fa fa-minus"></i>
										<?php echo _("Issued Certificate Details") ?>
									</h3>
								</div>
								<div class="section" data-id="show-cert">
									<!-- Common Name -->
									<div class="element-container">
										<div class="row">
											<div class="form-group form-horizontal">
												<div class="col-md-3">
													<label class="control-label" for="cn"><?php echo _("Certificate Common Name")?></label>
												</div>
												<div class="col-md-9">
													<?php echo $certinfo['subject']['CN']?>
												</div>
											</div>
										</div>
									</div>
									<!-- END Common Name -->

									<!-- Expiration -->
									<div class="element-container">
										<div class="row">
											<div class="form-group form-horizontal">
												<div class="col-md-3">
													<label class="control-label" for="an"><?php echo _("Certificate Alternative Names")?></label>
												</div>
												<div class="col-md-9">
													<?php echo $certinfo['extensions']['subjectAltName']?>
												</div>
											</div>
										</div>
									</div>
									<!-- END Expiration -->

									<!-- Expiration -->
									<div class="element-container">
										<div class="row">
											<div class="form-group form-horizontal">
												<div class="col-md-3">
													<label class="control-label" for="expires"><?php echo _("Certificate Valid Until")?></label>
												</div>
												<div class="col-md-9"> <?php echo \FreePBX::Certman()->getReadableExpiration($certinfo['validTo_time_t']); ?> </div>
											</div>
										</div>
									</div>
									<!-- END Expiration -->

									<!-- Policies -->
									<div class="element-container">
										<div class="row">
											<div class="form-group form-horizontal">
												<div class="col-md-3">
													<label class="control-label" for="cp"><?php echo _("Certificate Policies")?></label>
													<i class="fa fa-question-circle fpbx-help-icon" data-for="cp"></i>
												</div>
												<div class="col-md-9">
													<textarea class="form-control" rows=3 readonly><?php echo $certinfo['extensions']['certificatePolicies']?></textarea>
												</div>
											</div>
											<div class="col-md-12">
												<span id="cp-help" class="help-block fpbx-help-block" style=""><?php echo _('A certificate policy (CP) is a document which aims to state what are the different actors of a public key infrastructure (PKI), their roles and their duties')?></span>
											</div>
										</div>
									</div>
									<!-- END Policies -->
								</div>
							<?php } ?>
							<!-- END Section -->
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Background Let's Encrypt generation: progress modal -->
<div class="modal fade" id="leGenModal" tabindex="-1" role="dialog" aria-labelledby="leGenModalLabel">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="leGenModalLabel"><i class="fa fa-certificate"></i> <?php echo _("Generating Let's Encrypt Certificate")?></h4>
			</div>
			<div class="modal-body">
				<p id="leGenStatus"><i class="fa fa-spinner fa-spin"></i> <?php echo _("Starting...")?></p>
				<div class="progress" style="margin-bottom:10px;">
					<div id="leGenBar" class="progress-bar progress-bar-striped active" role="progressbar" style="width:100%;"></div>
				</div>
				<label class="control-label"><?php echo _("Output")?></label>
				<pre id="leGenLog" style="margin-top:5px; min-height:80px; max-height:320px; overflow:auto; white-space:pre-wrap; word-break:break-all;"><?php echo _("Waiting for output...")?></pre>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" id="leGenClose" data-dismiss="modal" style="display:none;"><?php echo _("Close")?></button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
(function(){
	// certman.js disables the action-bar Submit button and changes its label to
	// "Generating... Please wait" on every form submit. Because we handle the
	// generation via AJAX, restore it when the operation fails or the modal closes.
	var leGenSubmitVal = '';
	function leGenResetSubmit(){ $('#Submit').val(leGenSubmitVal).prop('disabled', false); }

	// Progress bar state: 'running' (animated), 'success' (green 100%), 'error' (red 100%).
	// Colours are forced inline because the theme styles .progress-bar with its own
	// brand colour/stripes at a higher specificity than the Bootstrap state classes.
	function leGenBarState(state){
		var bar = $('#leGenBar');
		bar.removeClass('progress-bar-striped active progress-bar-success progress-bar-danger').css('width','100%');
		if(state === 'success'){
			bar.addClass('progress-bar-success').css({'background-color':'#5cb85c','background-image':'none'});
		} else if(state === 'error'){
			bar.addClass('progress-bar-danger').css({'background-color':'#d9534f','background-image':'none'});
		} else {
			bar.addClass('progress-bar-striped active').css({'background-color':'','background-image':''});
		}
	}

	function leGenPoll(host){
		$.get('ajax.php?module=certman&command=generateLEStatus&host='+encodeURIComponent(host), function(res){
			if(res && typeof res.log === 'string'){
				var el = document.getElementById('leGenLog');
				el.textContent = res.log.trim() !== '' ? res.log : '<?php echo _("Waiting for output...")?>';
				el.scrollTop = el.scrollHeight;
			}
			if(res && res.finished){
				if(res.success){
					leGenBarState('success');
					$('#leGenStatus').html('<i class="fa fa-check text-success"></i> <?php echo _("Certificate generated successfully. Redirecting...")?>');
					setTimeout(function(){ window.location = 'config.php?display=certman'; }, 1500);
				} else {
					leGenBarState('error');
					$('#leGenStatus').html('<i class="fa fa-times text-danger"></i> <?php echo _("Generation failed. See the log below.")?>');
					$('#leGenClose').show();
					leGenResetSubmit();
				}
				return;
			}
			$('#leGenStatus').html('<i class="fa fa-spinner fa-spin"></i> <?php echo _("Generating certificate, please wait. This can take a minute...")?>');
			setTimeout(function(){ leGenPoll(host); }, 2000);
		}, 'json').fail(function(){
			setTimeout(function(){ leGenPoll(host); }, 3000);
		});
	}

	$(document).ready(function(){
		leGenSubmitVal = $('#Submit').val();
		$('#leGenModal').on('hidden.bs.modal', leGenResetSubmit);
		$('form[name=frm_certman]').on('submit', function(e){
			// Intercept both new generation and edits of existing LE certificates.
			var leAct = $('#certaction').val();
			if(leAct !== 'add' && leAct !== 'edit'){ return true; }
			if(this.checkValidity && !this.checkValidity()){
				e.preventDefault();
				if(this.reportValidity){ this.reportValidity(); }
				return false;
			}
			e.preventDefault();
			var form = this;
			var host = ($('#host').val()||'').toLowerCase();
			$('#leGenLog').text('<?php echo _("Waiting for output...")?>');
			$('#leGenStatus').html('<i class="fa fa-spinner fa-spin"></i> <?php echo _("Starting...")?>');
			$('#leGenClose').hide();
			leGenBarState('running');
			$('#leGenModal').modal({backdrop:'static', keyboard:false});
			$('#leGenModal').modal('show');
			$.post('ajax.php?module=certman&command=generateLEStart', $(form).serialize(), function(res){
				if(!res || !res.status){
					leGenBarState('error');
					$('#leGenStatus').html('<i class="fa fa-times text-danger"></i> <?php echo _("Generation failed.")?>');
					$('#leGenLog').text((res&&res.message)?res.message:'<?php echo _("Could not start generation.")?>');
					$('#leGenClose').show();
					leGenResetSubmit();
					return;
				}
				leGenPoll(res.host || host);
			}, 'json').fail(function(){
				leGenBarState('error');
				$('#leGenStatus').html('<i class="fa fa-times text-danger"></i> <?php echo _("Generation failed.")?>');
				$('#leGenLog').text('<?php echo _("Could not start generation.")?>');
				$('#leGenClose').show();
				leGenResetSubmit();
			});
			return false;
		});
	});
})();
</script>
