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
	<div class = "display no-border">
		<?php echo load_view(__DIR__.'/certgrid.php',array('certs' => $certs, 'csr' => $csr, 'ca' => $ca)); ?>
	</div>
</div>
