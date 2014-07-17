<div id="certpage">
	<form autocomplete="off" method="post">
		<input id="certtype" type="hidden" name="type" value="">
		<table>
			<tr>
				<td colspan="2"><h4><?php echo _("Certificate Settings")?></h4><hr></td>
			</tr>
			<?php if(!empty($message)) { ?>
				<tr>
					<td colspan="2">
						<div class="alert alert-<?php echo $message['type']?>"><?php echo $message['message']?></div>
					</td>
				</tr>
			<?php } ?>
			<tr class="selection">
				<td colspan="2">
					<button class="visual" data-type="generate" <?php echo ($caExists) ? 'disabled' : '' ?>><?php echo _('Generate A New Certificate')?></button>
					<button class="visual" data-type="upload" <?php echo ($caExists) ? 'disabled' : '' ?>><?php echo _('Upload A New Certificate')?></button>
				</td>
			</tr>
			<tr class="general hiden">
				<td><a href="#" class="info"><?php echo _("Certificate Authority")?>:<span><?php echo _("Certificate Authority to Reference")?></span></a></td>
				<td>
					<select name="ca">
						<?php foreach($cas as $ca) {?>
							<option value="<?php echo $ca['uid']?>"><?php echo $ca['on']?></option>
						<?php } ?>
					</select>
				</td>
			</tr>
			<tr class="general hiden">
				<td><a href="#" class="info"><?php echo _("Name")?>:<span><?php echo _("The base name of the certificate, Can only contain alphanumeric characters")?></span></a></td>
				<td><input type="text" autocomplete="off" name="name" maxlength="100" size="40" placeholder="BaseName"></td>
			</tr>
			<tr class="general hiden">
				<td><a href="#" class="info"><?php echo _("Description")?>:<span><?php echo _("The Description of this certificate. Used in the module only")?></span></a></td>
				<td><input type="text" autocomplete="off" name="description" maxlength="100" size="40"></td>
			</tr>
			<tr class="generate hiden">
				<td colspan="2"><button class="submit" data-type="generate"><?php echo _('Generate Certificate')?></button></td>
			</tr>
			<tr class="upload hiden">
				<td><a href="#" class="info"><?php echo _("Private Key")?>:<span><?php echo _("Private Key File")?></span></a></td>
				<td><input type="file" name="privatekey"></td>
			</tr>
			<tr class="upload hiden">
				<td><a href="#" class="info"><?php echo _("Certificate")?>:<span><?php echo _("Certificate File")?></span></a></td>
				<td><input type="file" name="certificate"></td>
			</tr>
			<tr class="upload hiden">
				<td colspan="2"><button class="submit" data-type="upload"><?php echo _('Upload Certificates')?></button></td>
			</tr>
		</table>
	</form>
</div>
