<!-- Certificates table-->
<div class="table-responsive">
	<div id="toolbar-all">
		<a href="?display=certman&amp;action=new" class="btn btn-primary" <?php echo !($caExists) ? "disabled" : ""?>><i class="fa fa-plus"></i> <?php echo _('New Certificate')?></a>
	</div>
		<table data-toolbar="#toolbar-all" data-maintain-selected="true" data-show-columns="true" data-show-toggle="true" data-toggle="table" data-pagination="true" data-search="true" class="table table-striped">
			<thead>
				<tr>
					<th><?php echo _("Certificate") ?></th>
					<th><?php echo _("Description") ?></th>
					<th><?php echo _("Action") ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($certs as $cert){?>
					<tr>
						<td><?php echo $cert['basename']?></td>
						<td><?php echo $cert['description']?></td>
						<td><a href='?display=certman&amp;action=view&amp;id=<?php echo $cert['cid']?>'>
							<i class="fa fa-edit"></i></a>&nbsp;&nbsp;
							<a id="deletecert" href='config.php?display=certman&amp;type=cert&amp;action=delete&amp;id=<?php echo $cert['cid']?>'>
							<i class="fa fa-trash-o"></i></a>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
<!--End Certificates table-->
