<?php
if(!empty($message)) {
	$messagehtml = '<div class="alert alert-' . $message['type'] .'">'. $message['message'] . '</div>';
}
?>

<div class="container-fluid">
	<h1><?php echo _("New Let's Encrypt Certificate")?></h1>
	<?php echo !empty($messagehtml) ? $messagehtml : "" ?>
	<div class = "display full-border">
		<div class="row">
			<div class="col-sm-12">
				<div class="fpbx-container">
					<div class="display full-border" id='certpage'>
						<form class="fpbx-submit" name="frm_certman" action="config.php?display=certman" method="post" enctype="multipart/form-data">
							<input id="certaction" type="hidden" name="certaction" value="<?php echo !empty($cert['cid']) ? 'edit' : 'add'?>">
							<input id="certtype" type="hidden" name="type" value="le">
							<input id="cid" type="hidden" name="cid" value="<?php echo !empty($cert['cid']) ? $cert['cid'] : ''?>">
							<div class="element-container">
								<div class="row">
									<div class="col-md-12">
										<div class="row">
											<div class="form-group">
												<div class="col-md-3">
													<label class="control-label" for="host"><?php echo _("Host Name")?></label>
													<i class="fa fa-question-circle fpbx-help-icon" data-for="host"></i>
												</div>
												<div class="col-md-9">
													<?php if (empty($cert['cid'])) { ?>
														<input type="text" class="form-control" id="host" name="host" placeholder="server.example.com" required="">
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
										<span id="host-help" class="help-block fpbx-help-block"><?php echo _("Full name of Server. Must be publicly accessible")?> ("server.example.com")</span>
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
