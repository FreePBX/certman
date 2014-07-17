<div id="certpage">
	<form autocomplete="off" name="editM" action="<?php $_SERVER['PHP_SELF'] ?>" method="post">
		<table>
			<tr>
				<td colspan="2"><h4><?php echo _("Certificate Settings")?></h4><hr></td>
			</tr>
			<tr>
				<td><a href="#" class="info"><?php echo _("Name")?>:<span><?php echo _("DNS name or our IP address")?></span></a></td>
				<td><input type="text" autocomplete="off" name="hostname" maxlength="100" size="40" value="<?php echo $_SERVER['SERVER_NAME'] ?>" placeholder="<?php echo $_SERVER['SERVER_NAME'] ?>"></td>
			</tr>
			<tr>
				<td><a href="#" class="info"><?php echo _("Description")?>:<span><?php echo _("DNS name or our IP address")?></span></a></td>
				<td><input type="text" autocomplete="off" name="hostname" maxlength="100" size="40" value="<?php echo $_SERVER['SERVER_NAME'] ?>" placeholder="<?php echo $_SERVER['SERVER_NAME'] ?>"></td>
			</tr>
			<tr>
				<td colspan="2"><button class="submit" data-type="upload"><?php echo _('Generate Certificate')?></button></td>
			</tr>
		</table>
	</form>
</div>
