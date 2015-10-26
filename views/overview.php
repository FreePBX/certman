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
	<?php echo !empty($messagehtml) ? $messagehtml : "" ?>
	<div class = "display full-border">
	    <div class="row">
	        <div class="col-sm-12">
	            <div class="fpbx-container">
					<ul class="nav nav-tabs" role="tablist">
						<li role="presentation" data-name="certificates" class="change-tab <?php echo ($caExists) ? "active" : ""?>"><a href="#certificates" aria-controls="certificates" role="tab" data-toggle="tab"><?php echo _("Certificates")?></a></li>
						<li role="presentation" data-name="casettings" class="change-tab <?php echo ($caExists) ? "" : "active"?>"><a href="#casettings" aria-controls="casettings" role="tab" data-toggle="tab"><?php echo _("Certificate Authority Settings (CA)")?></a></li>
					</ul>
					<div class="tab-content display">
						<div role=tabpanel" id="certificates" class="tab-pane <?php echo ($caExists) ? "active" : ""?>">
							<?php echo load_view(__DIR__.'/certgrid.php',array('certs' => $certs, 'caExists' => $caExists)); ?>
						</div>
						<div role=tabpanel" id="casettings" class="tab-pane <?php echo ($caExists) ? "" : "active"?>">
							<?php echo load_view(__DIR__.'/ca.php',array('caExists' => $caExists)); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
