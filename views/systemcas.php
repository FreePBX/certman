<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
$sources = $systemcas['sources'] ?? array();
$cas = $systemcas['cas'] ?? array();
$now = time();
?>
<div class="container-fluid">
	<h1><?php echo _('Installed Certificate Authorities')?></h1>

	<?php if(!empty($message) && !empty($message['message'])) { ?>
		<div class="alert alert-<?php echo htmlspecialchars($message['type'] ?? 'info', ENT_QUOTES); ?> alert-dismissable">
			<span class="fa fa-times close" data-dismiss="alert" aria-hidden="true"></span>
			<?php echo $message['message']; ?>
		</div>
	<?php } ?>

	<div class="alert alert-info">
		<?php echo _("These are the CA certificates trusted by this server (the same trust store used by PHP/cURL for outbound TLS). Use this list to confirm that a private/self-hosted ACME server's CA is already trusted, and to find a CA bundle path for the \"ACME Server CA Bundle\" field when generating a Let's Encrypt certificate.")?>
	</div>

	<!-- Install a CA into the system trust store (collapsible) -->
	<div class="panel panel-default">
		<div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#install-ca-body" aria-expanded="false">
			<div class="panel-title">
				<i class="fa fa-upload"></i>&nbsp;<?php echo _("Install a CA Certificate")?>
				<i class="fa fa-chevron-down pull-right"></i>
			</div>
		</div>
		<div class="panel-body collapse" id="install-ca-body">
		<div class="alert alert-warning">
			<?php echo _("This installs the certificate into the operating system trust store, so it becomes trusted system-wide (cURL, PHP, etc.). Only install CA certificates you trust: a malicious CA can be used to intercept TLS traffic. This requires the Sysadmin module (the install runs as root).")?>
		</div>
		<form class="" name="frm_installca" action="config.php?display=certman&amp;action=systemcas" method="post" enctype="multipart/form-data">
			<input type="hidden" name="certaction" value="installca">
			<div class="element-container">
				<div class="row">
					<div class="form-group form-horizontal">
						<div class="col-md-3"><label class="control-label" for="ca_name"><?php echo _("Friendly Name")?></label></div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="ca_name" name="ca_name" placeholder="internal-acme-ca">
							<span class="help-block fpbx-help-block"><?php echo _("Optional. Used for the stored filename (it will be prefixed with 'certman-'). Defaults to the certificate Common Name.")?></span>
						</div>
					</div>
				</div>
			</div>
			<div class="element-container">
				<div class="row">
					<div class="form-group form-horizontal">
						<div class="col-md-3"><label class="control-label" for="ca_pem"><?php echo _("CA Certificate (PEM)")?></label></div>
						<div class="col-md-9">
							<textarea class="form-control" id="ca_pem" name="ca_pem" rows="8" placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"></textarea>
							<span class="help-block fpbx-help-block"><?php echo _("Paste the PEM encoded CA certificate, or upload a file below. If both are provided, the uploaded file is used.")?></span>
						</div>
					</div>
				</div>
			</div>
			<div class="element-container">
				<div class="row">
					<div class="form-group form-horizontal">
						<div class="col-md-3"><label class="control-label" for="ca_file"><?php echo _("Or Upload File")?></label></div>
						<div class="col-md-9">
							<input type="file" id="ca_file" name="ca_file" accept=".pem,.crt,.cer">
						</div>
					</div>
				</div>
			</div>
			<div class="element-container">
				<div class="row">
					<div class="col-md-9 col-md-offset-3">
						<button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> <?php echo _("Install CA")?></button>
					</div>
				</div>
			</div>
		</form>
		</div>
	</div>

	<div class="display full-border">
		<!-- Trust stores -->
		<div class="section-title"><h3><i class="fa fa-folder-open"></i>&nbsp;<?php echo _("Trust Stores")?></h3></div>
		<table class="table table-striped">
			<thead>
				<tr>
					<th><?php echo _("Path")?></th>
					<th><?php echo _("Description")?></th>
					<th><?php echo _("Status")?></th>
					<th><?php echo _("Certificates")?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($sources as $s) { ?>
					<tr>
						<td><code><?php echo htmlspecialchars($s['path'], ENT_QUOTES); ?></code></td>
						<td><?php echo htmlspecialchars($s['label'], ENT_QUOTES); ?></td>
						<td>
							<?php if(!empty($s['exists'])) { ?>
								<span class="label label-success"><?php echo _("Present")?></span>
							<?php } else { ?>
								<span class="label label-default"><?php echo _("Not found")?></span>
							<?php } ?>
						</td>
						<td><?php echo (int)$s['count']; ?></td>
					</tr>
				<?php } ?>
			</tbody>
		</table>

		<!-- CA certificates -->
		<div class="section-title"><h3><i class="fa fa-certificate"></i>&nbsp;<?php echo sprintf(_("Trusted CA Certificates (%d)"), count($cas))?></h3></div>
		<table data-toggle="table" data-pagination="true" data-page-size="25" data-search="true" data-show-columns="true" class="table table-striped">
			<thead>
				<tr>
					<th data-sortable="true"><?php echo _("Common Name / Organization")?></th>
					<th data-sortable="true"><?php echo _("Issuer")?></th>
					<th data-sortable="true"><?php echo _("Type")?></th>
					<th data-sortable="true" data-field="expires"><?php echo _("Expires")?></th>
					<th><?php echo _("SHA1 Fingerprint")?></th>
					<th><?php echo _("Source File")?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($cas as $ca) {
					$expired = (!empty($ca['validTo_time_t']) && $ca['validTo_time_t'] < $now);
				?>
					<tr>
						<td><strong><?php echo htmlspecialchars($ca['cn'], ENT_QUOTES); ?></strong>
							<i class="fa fa-question-circle fpbx-help-icon" title="<?php echo htmlspecialchars($ca['subject'], ENT_QUOTES); ?>"></i>
						</td>
						<td><?php echo htmlspecialchars($ca['issuer'], ENT_QUOTES); ?></td>
						<td>
							<?php if(!empty($ca['selfSigned'])) { ?>
								<span class="label label-primary"><?php echo _("Root (self-signed)")?></span>
							<?php } else { ?>
								<span class="label label-info"><?php echo _("Intermediate")?></span>
							<?php } ?>
						</td>
						<td data-value="<?php echo (int)$ca['validTo_time_t']; ?>">
							<?php
								echo !empty($ca['validTo_time_t']) ? date('Y-m-d', $ca['validTo_time_t']) : '-';
								if($expired) { echo ' <span class="label label-danger">'._("Expired").'</span>'; }
							?>
						</td>
						<td><small><code><?php echo htmlspecialchars($ca['fingerprint'], ENT_QUOTES); ?></code></small></td>
						<td><small><?php echo htmlspecialchars($ca['source'], ENT_QUOTES); ?></small></td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</div>
