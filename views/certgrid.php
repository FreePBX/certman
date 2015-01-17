<?php

foreach($certs as $cert){
	$cid = $cert['cid'];
	$basename = $cert['basename'];
	$description = $cert['description'];
$certrows .= <<<HERE
<tr id = "row$cid">
	<!--<td><input type = "checkbox" class="" id="actonthis$cid" name="actionList[]" value="$cid"></td>!-->
	<td>$basename</td>
	<td>$description</td>
	<td><a href='?display=certman&amp;action=view&amp;id=$cid'>
		<i class="fa fa-edit"></i></a>&nbsp;&nbsp;
		<a href='config.php?display=certman&amp;type=cert&amp;action=delete&amp;id=$cid'>
		<i class="fa fa-trash-o"></i></a>
	</td>
</tr>
HERE;
}
?>
<!--Certificates table-->
<div class="table-responsive">
    <table class="table table-striped table-bordered">
      <thead>
        <tr>
		  <!--<th><input type="checkbox" class="" id="action-toggle-all"></th>-->
          <th><?php echo _("Certificate") ?></th>
          <th><?php echo _("Description") ?></th>
          <th><?php echo _("Action") ?></th>
        </tr>
      </thead>
      <tbody>
		  <?php echo $certrows ?>
      </tbody>
    </table>
  </div>
<!--End Certificates table-->
