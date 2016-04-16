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
	<h1><?php echo !empty($cert['cid']) ? _("Update Existing Certificate") : _("Add New Certificate")?></h1>
	<?php echo !empty($messagehtml) ? $messagehtml : "" ?>
	<div class = "display full-border">
		<div class="row">
			<div class="col-sm-12">
				<div class="fpbx-container">
					<div class="display full-border" id='certpage'>
						<form class="fpbx-submit" name="frm_certman" action="config.php?display=certman" method="post" enctype="multipart/form-data">
							<input id="certaction" type="hidden" name="certaction" value="<?php echo !empty($cert['cid']) ? 'edit' : 'add'?>">
							<input id="certtype" type="hidden" name="type" value="ss">
							<input id="cid" type="hidden" name="cid" value="<?php echo !empty($cert['cid']) ? $cert['cid'] : ''?>">
							<input id="caid" type="hidden" name="caid" value="<?php echo !empty($cas[0]['uid']) ? $cas[0]['uid'] : ""?>">
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
													<?php if (empty($cert['cid'])) { ?>
														<input type="text" class="form-control" autocomplete="off" name="name" id="name" placeholder="BaseName" data-invalid="<?php echo _('This field cannot be blank and must be alphanumeric')?>" value="<?php echo !empty($cert['basename']) ? $cert['basename'] : ""?>" required pattern="[A-Za-z0-9]{3,100}">
													<?php } else { ?>
														<?php echo !empty($cert['basename']) ? $cert['basename'] : ""?>
													<?php } ?>
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
							<?php if(!empty($cert['cid'])) { ?>
								<div class="element-container">
									<div class="row">
										<div class="col-md-12">
											<div class="row">
												<div class="form-group">
													<div class="col-md-3">
														<label class="control-label" for="expires"><?php echo _("Valid Until")?></label>
														<i class="fa fa-question-circle fpbx-help-icon" data-for="expires"></i>
													</div>
													<div class="col-md-9">
														<?php echo date('m/d/Y',$certinfo['validTo_time_t'])?>
													</div>
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<span id="expires-help" class="help-block fpbx-help-block" style=""><?php echo _('How long the certificate is valid until')?></span>
										</div>
									</div>
								</div>
								<div class="element-container">
									<div class="row">
										<div class="col-md-12">
											<div class="row">
												<div class="form-group">
													<div class="col-md-3">
														<label class="control-label" for="cn"><?php echo _("Common Name")?></label>
														<i class="fa fa-question-circle fpbx-help-icon" data-for="cn"></i>
													</div>
													<div class="col-md-9">
														<?php echo $certinfo['subject']['CN']?>
													</div>
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<span id="cn-help" class="help-block fpbx-help-block" style=""><?php echo _('The certificate common name, usually the same as the host name')?></span>
										</div>
									</div>
								</div>
								<?php if(!empty($certinfo['extensions']['certificatePolicies'])) {?>
									<div class="element-container">
										<div class="row">
											<div class="col-md-12">
												<div class="row">
													<div class="form-group">
														<div class="col-md-3">
															<label class="control-label" for="cp"><?php echo _("Certificate Policies")?></label>
															<i class="fa fa-question-circle fpbx-help-icon" data-for="cp"></i>
														</div>
														<div class="col-md-9">
															<textarea class="form-control" readonly><?php echo $certinfo['extensions']['certificatePolicies']?></textarea>
														</div>
													</div>
												</div>
											</div>
										</div>
										<div class="row">
											<div class="col-md-12">
												<span id="cp-help" class="help-block fpbx-help-block" style=""><?php echo _('TThe certificate policies')?></span>
											</div>
										</div>
									</div>
								<?php } ?>
							<?php } ?>
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
													<input type="text" class="form-control" autocomplete="off" name="description" id="description" value="<?php echo !empty($cert['description']) ? $cert['description'] : ""?>">
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
														<input type="password" class="form-control" autocomplete="off" name="passphrase" id="passphrase">
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
							<?php if($caExists) { ?>
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
														<b><?php echo $cas[0]['on']?></b> <a href="?display=certman&amp;action=certaction&amp;type=ca" id="delCA"><i class="fa fa-trash-o"></i></i>
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
							<?php } else { ?>
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
														<input type="text" class="form-control" id="hostname" name="hostname" placeholder="<?php echo $hostname ?>" value="<?php echo $hostname?>" required>
													</div>
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<span id="hostname-help" class="help-block fpbx-help-block"><?php echo _("DNS name or your IP address")?></span>
										</div>
									</div>
								</div>
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
														<input type="text" class="form-control" id="orgname" name="orgname" placeholder="My Super Organization" value="" required>
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
														<div class="input-group">
															<input type="password" class="form-control" id="passphrase" name="passphrase" value="<?php echo !empty($cert['cid']) ? '' : $pass?>">
															<span class="input-group-btn">
																<button data-id="passphrase" class="btn btn-default toggle-password" type="button">
																	<i class="fa fa-2x fa-eye" style="margin-top: -2px;"></i>
																</button>
															</span>
														</div>

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
							<?php } ?>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
