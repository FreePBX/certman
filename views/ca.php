<div id="capage">
	<form autocomplete="off" name="editM" action="<?php $_SERVER['PHP_SELF'] ?>" method="post">
		<input id="catype" type="hidden" name="type" value="">
		<table>
			<tr>
				<td colspan="2"><h4><?php echo _("Certificate Authority Settings")?></h4><hr></td>
			</tr>
			<tr class="selection">
				<td colspan="2">
					<?php if($caExists) { ?>
						<div class="alert alert-danger"><?php echo _('A Certificate Authority is already present on this system. Deleting/Generating/Uploading will invalidate all of your current certificates!')?></div>
						<input id="caexistscheck" type="checkbox"> <label for="caexistscheck"><?php echo _('I know what I am doing and I understand the risks')?></label><br/><br/>
						<button class="submit" data-type="delete" disabled><?php echo _('Delete The Certificate Authority')?></button>
					<?php } ?>
					<button class="visual" data-type="generate" <?php echo ($caExists) ? 'disabled' : '' ?>><?php echo _('Generate A New Certificate Authority')?></button>
					<button class="visual" data-type="upload" <?php echo ($caExists) ? 'disabled' : '' ?>><?php echo _('Upload A New Certificate Authority')?></button>
				</td>
			</tr>
			<tr class="general hiden">
				<td><a href="#" class="info"><?php echo _("Host Name")?>:<span><?php echo _("DNS name or our IP address")?></span></a></td>
				<td><input type="text" autocomplete="off" name="hostname" maxlength="100" size="40" value="<?php echo $_SERVER['SERVER_NAME'] ?>" placeholder="<?php echo $_SERVER['SERVER_NAME'] ?>"></td>
			</tr>
			<tr class="general hiden">
				<td><a href="#" class="info"><?php echo _("Organization Name")?>:<span><?php echo _("The Organization Name")?></span></a></td>
				<td><input type="text" autocomplete="off" name="orgname" maxlength="100" size="40" value="" placeholder="My Super Organization"></td>
			</tr>
			<tr class="general hiden">
				<td><a href="#" class="info"><?php echo _("Passphrase")?>:<span><?php echo _("Passphrase used to access this certificate and generate new client certificates.
				If you don't use a passphrase when generating a new certifcate, then the private key is not encrypted with any symmetric cipher - it is output completely unprotected.
				If you don't provide a passphrase when uploading a certificate you will have to provide the passphrase everytime a new certificate is needed")?></span></a></td>
				<td><input type="password" autocomplete="off" name="passphrase" size="40" value=""></td>
			</tr>
			<tr class="general hiden">
				<td><a href="#" class="info"><?php echo _("Save Passphrase")?>:<span><?php echo _("Whether to store the password in the database (encrypted) so that new certificates can be generated without entering the passphrase in.
				Note that encrypted does not mean cant be hacked or stolen or reverse engineered. This is useful for letting other module generate certificates")?></span></a></td>
				<td>
					<span class="radioset">
						<input type="radio" name="savepassphrase" value="yes" id="phsaveyes" checked><label for="phsaveyes">Yes</label>
						<input type="radio" name="savepassphrase" value="no" id="phsaveno"><label for="phsaveno">No</label>
					</span>
				</td>
			</tr>
			<tr class="generate hiden">
				<td colspan="2"><button class="submit" data-type="generate"><?php echo _('Generate Certificate')?></button></td>
			</tr>
			<tr class="upload hiden">
				<td><a href="#" class="info"><?php echo _("Private Key")?>:<span><?php echo _("DNS name or our IP address")?></span></a></td>
				<td><input type="file" name="privatekey"></td>
			</tr>
			<tr class="upload hiden">
				<td><a href="#" class="info"><?php echo _("Certificate")?>:<span><?php echo _("DNS name or our IP address")?></span></a></td>
				<td><input type="file" name="certificate"></td>
			</tr>
			<tr class="upload hiden">
				<td colspan="2"><button class="submit" data-type="upload"><?php echo _('Upload Certificates')?></button></td>
			</tr>
		</table>
	</form>
</div>
