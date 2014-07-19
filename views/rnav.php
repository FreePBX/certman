<div class='rnav'>
	<ul>
		<li><a href='?display=certman'><?php echo _('Overview')?></a></li>
		<li><a href='?display=certman&amp;action=ca'><?php echo _('Certificate Authority Settings (CA)')?></a></li>
		<li><a href='?display=certman&amp;action=new'><?php echo _('New Certificate')?></a></li>
		<li><hr></li>
		<?php foreach($certs as $cert) { ?>
			<li><a href='?display=certman&amp;action=view&amp;id=<?php echo $cert['cid']?>'><?php echo $cert['basename']?></a></li>
		<?php } ?>
	</ul>
</div>
