<a href='config.php?display=certman&amp;type=cert&amp;action=delete&amp;id=<?php echo $cert['cid']?>'>
	<img src='images/trash.png' /> <?php echo _('Delete Certificate')?>
</a>
<form autocomplete="off" method="post">
	<input id="certtype" type="hidden" name="type" value="update">
	<input id="cid" type="hidden" name="cid" value="<?php echo $cert['cid']?>">
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
		<tr class="general">
			<td><a href="#" class="info"><?php echo _("Name")?>:<span><?php echo _("The base name of the certificate, Can only contain alphanumeric characters")?></span></a></td>
			<td><input type="text" autocomplete="off" name="name" maxlength="100" size="40" placeholder="BaseName" value="<?php echo $cert['basename']?>"></td>
		</tr>
		<tr class="general">
			<td><a href="#" class="info"><?php echo _("Description")?>:<span><?php echo _("The Description of this certificate. Used in the module only")?></span></a></td>
			<td><input type="text" autocomplete="off" name="description" maxlength="100" size="40" value="<?php echo $cert['description']?>"></td>
		</tr>
		<tr class="upload">
			<td colspan="2"><button class="submit"><?php echo _('Update Certificate')?></button></td>
		</tr>
	</table>
</form>
