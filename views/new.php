<?php
if(!empty($message)) {
	$messagehtml = '<div class="alert alert-' . $message['type'] .'">'. $message['message'] . '</div>';
}
//ca input
if(count($cas) == 1){
	$rpf = !empty($cas[0]['passphrase']) ? 'no' : 'yes';
	$cainput = '<b>' . $cas[0]['on'] . '</b>';
	$cainput .= '<input type="hidden" name="ca" id="ca" value="' . $cas[0]['uid'] . '" data-requirespassphrase="'. $rpf . '" >';
}else{
	$cainput = '<select name="ca" id="ca" class="form-control">';
	foreach($cas as $ca) {
		$rpf = !empty($ca['passphrase']) && $ca['passphrase'] == 'yes' && !empty($ca['salt']) ? 'no' : 'yes';
		$cainput .= '<option data-requirespassphrase="'. $rpf . '" value="'. $ca['uid'].'">' . $ca['on'] .'</option>';
	}
	$cainput .= '</select>';
}

?>

<div class="container-fluid">
	<h1><?php echo _('New Certificate')?></h1>
	<?php echo !empty($messagehtml) ? $messagehtml : "" ?>
	<div class = "display full-border">
		<div class="row">
			<div class="col-sm-12">
				<div class="fpbx-container">
					<div class="display full-border" id='certpage'>
						<form class="fpbx-submit" name="frm_certman" action="config.php?display=certman" method="post" enctype="multipart/form-data">
						<input id="certtype" type="hidden" name="type" value="generate">
						<input id="action" type="hidden" name="action" value="new">
						<!--CA-->
							<div class="element-container">
								<div class="row">
									<div class="col-md-12">
										<div class="row">
											<div class="form-group">
												<div class="col-md-3">
													<label class="control-label" for="ca"><?php echo _("Certificate Authority")?></label>
													<i class="fa fa-question-circle fpbx-help-icon" data-for="ca"></i>
												</div>
												<div class="col-md-9">
													<?php echo $cainput ?>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="ca-help" class="help-block fpbx-help-block"><?php echo _("Certificate Authority to Reference")?></span>
									</div>
								</div>
							</div>
							<!--END CA-->
							<!--Name-->
							<div class="element-container">
								<div class="row">
									<div class="col-md-12">
										<div class="row">
											<div class="form-group">
												<div class="col-md-3">
													<label class="control-label" for="name"><?php echo _("Name")?></label>
													<i class="fa fa-question-circle fpbx-help-icon" data-for="name"></i>
												</div>
												<div class="col-md-9">
													<input type="text" class="form-control" autocomplete="off" name="name" id="name" placeholder="BaseName" data-invalid="<?php echo _('This field cannot be blank and must be alphanumeric')?>" required>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="name-help" class="help-block fpbx-help-block"><?php echo _("The base name of the certificate, Can only contain alphanumeric characters")?></span>
									</div>
								</div>
							</div>
							<!--END Name-->
							<!--Description-->
							<div class="element-container">
								<div class="row">
									<div class="col-md-12">
										<div class="row">
											<div class="form-group">
												<div class="col-md-3">
													<label class="control-label" for="description"><?php echo _("Description")?></label>
													<i class="fa fa-question-circle fpbx-help-icon" data-for="description"></i>
												</div>
												<div class="col-md-9">
													<input type="text" class="form-control" autocomplete="off" name="description" id="description">
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="description-help" class="help-block fpbx-help-block"><?php echo _("The Description of this certificate. Used in the module only")?></span>
									</div>
								</div>
							</div>
							<!--END Description-->
							<!--Passphrase-->
							<?php if($rpf == 'yes') {?>
								<div class="element-container">
									<div class="row">
										<div class="col-md-12">
											<div class="row">
												<div class="form-group">
													<div class="col-md-3">
														<label class="control-label" for="passphrase"><?php echo _("Passphrase")?></label>
														<i class="fa fa-question-circle fpbx-help-icon" data-for="passphrase"></i>
													</div>
													<div class="col-md-9">
														<input type="text" class="form-control" autocomplete="off" name="passphrase" id="passphrase">
													</div>
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<span id="passphrase-help" class="help-block fpbx-help-block"><?php echo _("The Passphrase of the Certificate Authority")?></span>
										</div>
									</div>
								</div>
							<?php } ?>
							<!--END Passphrase-->
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
													<label class="control-label" for="enableul"><?php echo _("Upload Certificate")?></label>
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
										<span id="privatekey-help" class="help-block fpbx-help-block"><?php echo _("Private Key File")?> (key)</span>
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
										<span id="certificate-help" class="help-block fpbx-help-block"><?php echo _("Certificate File")?> (crt)</span>
									</div>
								</div>
							</div>
							<!--END Certificate-->
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
</div>
