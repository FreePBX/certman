<!--Start bootnav-->
<div class="col-sm-3 hidden-xs bootnav">
	<div class="list-group">
		<a href='?display=certman' class="list-group-item <?php echo $_REQUEST['action']  || $_REQUEST['action'] != 'delete'  ? '':'hidden'?>"><i class="fa fa-th-list"></i>&nbsp&nbsp;<?php echo _('Certificate List')?></a>
		<a href='?display=certman&amp;action=new' class="list-group-item <?php echo $_REQUEST['action'] == 'new' ? 'hidden':''?>"><i class="fa fa-plus"></i>&nbsp&nbsp;<?php echo _('New Certificate')?></a>
	</div>
</div>
<!--End bootnav-->
