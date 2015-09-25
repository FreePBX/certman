<?php
if($caExists){
	$cafclass = 'hidden';
	$caehtml = '<div class ="well" id="caexists">';
	$caehtml .= _('A Certificate Authority is already present on this system. Deleting/Generating/Uploading will invalidate all of your current certificates!');
	$caehtml .= '<br/><br/>';
	$caehtml .= '<button class="btn btn-danger" id="replaceCA">'._("I Know what I am doing").'</button>';
	$caehtml .= '</div>';
}

?>
<div class="fpbx-container">
	<h2><?php echo $caExists ? _("Edit Certificate Authority Settings") : _("New Certificate Authority Settings")?></h2>
	<?php echo !empty($caehtml) ? $caehtml : "" ?>
	<div id = "caform" class="<?php echo !empty($cafclass) ? $cafclass : ""?>">
		<form autocomplete="off" class="fpbx-submit" name="editCAS" method="post" action="config.php?display=certman" data-fpbx-delete="config.php?display=certman&amp;action=ca&amp;type=delete" enctype="multipart/form-data">
		<input id="catype" type="hidden" name="type" value="generate">
		<input id="action" type="hidden" name="action" value="ca">
		<input id="replace" type="hidden" name="replace" value="no">
		<!--Hostname-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="hostname"><?php echo  _("Host Name") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="hostname"></i>
							</div>
							<div class="col-md-9">
								<input type="text" class="form-control" id="hostname" name="hostname" placeholder="<?php echo $_SERVER['SERVER_NAME'] ?>" value="<?php echo $_SERVER['SERVER_NAME'] ?>" required">
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="hostname-help" class="help-block fpbx-help-block"><?php echo _("DNS name or our IP address")?></span>
				</div>
			</div>
		</div>
		<!--END Hostname-->
		<!--Orgname-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="orgname"><?php echo  _("Organization Name") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="orgname"></i>
							</div>
							<div class="col-md-9">
								<input type="text" class="form-control" id="orgname" name="orgname" placeholder="My Super Organization" value="" required">
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="orgname-help" class="help-block fpbx-help-block"><?php echo  _("The Organization Name")?></span>
				</div>
			</div>
		</div>
		<!--END Orgname-->
		<!--Passphrase-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="passphrase"><?php echo  _("Passphrase") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="passphrase"></i>
							</div>
							<div class="col-md-9">
								<input type="password" class="form-control" id="passphrase" name="passphrase" value=""  required">
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="passphrase-help" class="help-block fpbx-help-block"><?php echo  _("Passphrase used to access this certificate and generate new client certificates.
					If you don't use a passphrase when generating a new certifcate, then the private key is not encrypted with any symmetric cipher - it is output completely unprotected.
					If you don't provide a passphrase when uploading a certificate you will have to provide the passphrase everytime a new certificate is needed")?></span>
				</div>
			</div>
		</div>
		<!--END Passphrase-->
		<!--Save Passphrase-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="savepassphrase"><?php echo _("Save Passphrase") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="savepassphrase"></i>
							</div>
							<div class="col-md-9 radioset">
								<input type="radio" name="savepassphrase" value="yes" id="phsaveyes" checked><label for="phsaveyes"><?php echo _("Yes")?></label>
								<input type="radio" name="savepassphrase" value="no" id="phsaveno"><label for="phsaveno"><?php echo _("No")?></label>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="savepassphrase-help" class="help-block fpbx-help-block"><?php echo _("Whether to store the password in the database so that new certificates can be generated automatically.<br/>
					<b>WARNING!!</b> The Passphrase is stored in PLAINTEXT! You have been warned. Use Something you dont care about or use!") ?></span>
				</div>
			</div>
		</div>
		<!--END Save Passphrase-->
		<!--Enable Upload-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
							</div>
							<div class="col-md-9 radioset">
								<input type="checkbox" id="enableul">
								<label class="control-label" for="enableul"><?php echo _("Upload CA")?></label>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="enableul-help" class="help-block fpbx-help-block"><?php echo _("Select this for additional fields used to upload your own certificate.")?></span>
				</div>
			</div>
		</div>
		<!--End Enable Upload-->
		<!--Private key-->
		<div class="element-container hidden" id='pkdiv'>
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="privatekey"><?php echo _("Private Key") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="privatekey"></i>
							</div>
							<div class="col-md-9">
								<span class="btn btn-default btn-file">
									Browse <input type="file" class="form-control" name="privatekey" id="privatekey">
								</span>
								<span class="filename"></span>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="privatekey-help" class="help-block fpbx-help-block"><?php echo _("Private Key File to use for this CA")?></span>
				</div>
			</div>
		</div>
		<!--END Privatekey-->
		<!--Certificate-->
		<div class="element-container hidden" id='cdiv'>
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="certificate"><?php echo _("Certificate") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="certificate"></i>
							</div>
							<div class="col-md-9">
								<span class="btn btn-default btn-file">
									Browse <input type="file" class="form-control" name="certificate" id="certificate">
								</span>
								<span class="filename"></span>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="certificate-help" class="help-block fpbx-help-block"><?php echo _("Certificate to use for this CA (must reference the Private Key)")?></span>
				</div>
			</div>
		</div>
		<!--END Certificate-->

		</form>
	</div>
</div>
