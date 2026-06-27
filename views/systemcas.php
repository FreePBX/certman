<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
$sources = $systemcas['sources'] ?? array();
$cas = $systemcas['cas'] ?? array();
$managed = $managed ?? array();
$now = time();
$driftCount = 0;
foreach($managed as $m) { if(!empty($m['wantInstall']) !== !empty($m['present'])) { $driftCount++; } }
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

	<!-- Modal: load a CA certificate into Certman (step 1) -->
	<div class="modal fade" id="loadCaModal" tabindex="-1" role="dialog" aria-labelledby="loadCaModalLabel">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<form name="frm_installca" action="config.php?display=certman&amp;action=systemcas" method="post" enctype="multipart/form-data">
					<input type="hidden" name="certaction" value="savecert">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="<?php echo _("Close")?>"><span aria-hidden="true">&times;</span></button>
						<h4 class="modal-title" id="loadCaModalLabel"><i class="fa fa-upload"></i> <?php echo _("Load a CA Certificate")?></h4>
					</div>
					<div class="modal-body">
						<div class="alert alert-info">
							<?php echo _("Step 1 of 2. This loads the certificate into Certman but does NOT install it into the system trust store yet. After loading it, use the Install button in the list below to add it to the operating-system trust store. Only trust CA certificates you control: a malicious CA can be used to intercept TLS traffic.")?>
						</div>
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
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _("Cancel")?></button>
						<button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> <?php echo _("Load CA")?></button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- Trust stores (summary) -->
	<div class="display full-border">
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
	</div>

	<!-- CAs loaded into Certman (desired state) -->
	<div class="display full-border">
		<div class="section-title"><h3><i class="fa fa-shield"></i>&nbsp;<?php echo sprintf(_("CAs Loaded into Certman (%d)"), count($managed))?></h3></div>
		<?php if($driftCount > 0) { ?>
			<div class="alert alert-warning">
				<i class="fa fa-exclamation-triangle"></i>
				<?php echo sprintf(_("%d CA(s) are out of sync with the system trust store (marked for install but missing, or marked for uninstall but still present)."), $driftCount); ?>
				<form style="display:inline" method="post" action="config.php?display=certman&amp;action=systemcas">
					<input type="hidden" name="certaction" value="reinstallca">
					<button type="submit" class="btn btn-xs btn-warning"><i class="fa fa-refresh"></i> <?php echo _("Reconcile now")?></button>
				</form>
			</div>
		<?php } ?>
		<!-- Custom toolbar: the Load button renders to the left of the search box -->
		<div id="caLoadedToolbar">
			<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#loadCaModal">
				<i class="fa fa-upload"></i> <?php echo _("Load a CA Certificate")?>
			</button>
		</div>
		<table data-toggle="table" data-search="true" data-toolbar="#caLoadedToolbar" data-cache="false" data-show-refresh="true"
		       data-url="ajax.php?module=certman&amp;command=getManagedCAsGrid" class="table table-striped">
			<thead>
				<tr>
					<th data-field="cn" data-sortable="true" data-formatter="caCnFmt"><?php echo _("Common Name")?></th>
					<th data-field="fingerprint" data-sortable="true" data-formatter="caFpFmt"><?php echo _("SHA1 Fingerprint")?></th>
					<th data-field="wantInstall" data-sortable="true" data-halign="center" data-align="center" data-formatter="caDesiredFmt"><?php echo _("Desired")?></th>
					<th data-field="present" data-sortable="true" data-halign="center" data-align="center" data-formatter="caPresentFmt"><?php echo _("In System Trust Store")?></th>
					<th data-field="fp" data-searchable="false" data-halign="center" data-align="center" data-width="240" data-formatter="caActionsFmt"><?php echo _("Actions")?></th>
				</tr>
			</thead>
		</table>
	</div>
	<br>

	<!-- All CA certificates trusted by the system -->
	<div class="display full-border">
		<div class="section-title"><h3><i class="fa fa-certificate"></i>&nbsp;<?php echo sprintf(_("Trusted CA Certificates (%d)"), count($cas))?></h3></div>
		<table data-toggle="table" data-pagination="true" data-page-size="25" data-search="true" data-show-columns="true" data-show-refresh="true" data-cache="false"
		       data-url="ajax.php?module=certman&amp;command=getSystemCAsGrid" class="table table-striped">
			<thead>
				<tr>
					<th data-field="cn" data-sortable="true" data-formatter="caCnSysFmt"><?php echo _("Common Name / Organization")?></th>
					<th data-field="issuer" data-sortable="true"><?php echo _("Issuer")?></th>
					<th data-field="selfSigned" data-sortable="true" data-formatter="caTypeFmt"><?php echo _("Type")?></th>
					<th data-field="expires" data-sortable="true" data-formatter="caExpiresFmt"><?php echo _("Expires")?></th>
					<th data-field="fingerprint" data-formatter="caFpFmt"><?php echo _("SHA1 Fingerprint")?></th>
					<th data-field="source" data-formatter="caSrcFmt"><?php echo _("Source File")?></th>
				</tr>
			</thead>
		</table>
	</div>

</div>

<script type="text/javascript">
	// Escape helper (renders text safely inside the bootstrap-table formatters).
	function certmanEsc(s){ return $('<div>').text(s == null ? '' : s).html(); }
	var CERTMAN_SYSACTION = 'config.php?display=certman&action=systemcas';

	// "CAs Loaded into Certman" formatters
	function caCnFmt(v,r){ return '<strong>'+certmanEsc(v)+'</strong>'; }
	function caFpFmt(v,r){ return '<small><code>'+certmanEsc(v)+'</code></small>'; }
	function caSrcFmt(v,r){ return '<small>'+certmanEsc(v)+'</small>'; }
	function caDesiredFmt(v,r){
		return r.wantInstall
			? '<span class="label label-info"><?php echo _("Install")?></span>'
			: '<span class="label label-default"><?php echo _("Loaded only")?></span>';
	}
	function caPresentFmt(v,r){
		return r.present
			? '<span class="label label-success"><i class="fa fa-check"></i> <?php echo _("Present")?></span>'
			: '<span class="label label-default"><?php echo _("Not present")?></span>';
	}
	function caActionsFmt(v,r){
		var fp = certmanEsc(r.fp);
		var toggle = r.wantInstall
			? '<form style="display:inline-block" method="post" action="'+CERTMAN_SYSACTION+'"><input type="hidden" name="certaction" value="uninstallca"><input type="hidden" name="fp" value="'+fp+'"><button type="submit" class="btn btn-xs btn-warning"><i class="fa fa-sign-out"></i> <?php echo _("Uninstall")?></button></form>'
			: '<form style="display:inline-block" method="post" action="'+CERTMAN_SYSACTION+'"><input type="hidden" name="certaction" value="installca"><input type="hidden" name="fp" value="'+fp+'"><button type="submit" class="btn btn-xs btn-primary"><i class="fa fa-download"></i> <?php echo _("Install")?></button></form>';
		var remove = '<form style="display:inline-block" method="post" action="'+CERTMAN_SYSACTION+'" onsubmit="return confirm(\'<?php echo _("Remove this CA from Certman entirely (and uninstall it from the system)?")?>\');"><input type="hidden" name="certaction" value="removeca"><input type="hidden" name="fp" value="'+fp+'"><button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i> <?php echo _("Remove")?></button></form>';
		return '<div style="white-space:nowrap;">' + toggle + ' ' + remove + '</div>';
	}

	// "Trusted CA Certificates" formatters
	function caCnSysFmt(v,r){
		return '<strong>'+certmanEsc(v)+'</strong> <i class="fa fa-question-circle fpbx-help-icon" title="'+certmanEsc(r.subject)+'"></i>';
	}
	function caTypeFmt(v,r){
		return r.selfSigned
			? '<span class="label label-primary"><?php echo _("Root (self-signed)")?></span>'
			: '<span class="label label-info"><?php echo _("Intermediate")?></span>';
	}
	function caExpiresFmt(v,r){
		return certmanEsc(v) + (r.expired ? ' <span class="label label-danger"><?php echo _("Expired")?></span>' : '');
	}
</script>
