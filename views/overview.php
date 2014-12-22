<?php
if(!empty($message)) {
	$messagehtml = '<div class="alert alert-' . $message['type'] .'">'. $message['message'] . '</div>';
}
?>
<div class="container-fluid">
	<h1><?php echo _('Certificate Management')?></h1>
	<div class="well well-info">
		<?php echo _('This module is intended to manage and generate certificates used for extensions in asterisk')?>
	</div>
	<?php echo $messagehtml ?>
	<div class = "display full-border">
	    <div class="row">
	        <div class="col-sm-9">
	            <div class="fpbx-container">
					<ul class="nav nav-tabs" role="tablist">
						<li role="presentation" data-name="certificates" class="active"><a href="#certificates" aria-controls="certificates" role="tab" data-toggle="tab"><?php echo _("Certificates")?></a></li>
						<li role="presentation" data-name="casettings" class="change-tab"><a href="#casettings" aria-controls="casettings" role="tab" data-toggle="tab"><?php echo _("Certificate Authority Settings (CA)")?></a></li>
					</ul>
					<div class="tab-content display">
						<div role=tabpanel" id="certificates" class="tab-pane active">
							<?php echo load_view(__DIR__.'/certgrid.php',array('certs' => $certs)); ?>
						</div>
						<div role=tabpanel" id="casettings" class="tab-pane">
							<?php echo load_view(__DIR__.'/ca.php',array('caExists' => $caExists)); ?>
						</div>
					</div>
				</div>
			</div>
			<?php echo load_view(__DIR__.'/rnav.php',array()); ?>
		</div>
	</div>
</div>
