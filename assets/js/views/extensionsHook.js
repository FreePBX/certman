document.addEventListener('DOMContentLoaded', function(ev) {
	document.removeEventListener('DomContentLoaded', arguments.callees, false);

	$("input[name=dtls_enable]").change(function() {
		if ($("input[name='dtls_enable']:checked").val() == 'yes') {
			if ($( "#devinfo_media_encryption option:selected" ).val() != 'dtls') {
				$("input[name='dtls_enable']:checked").val('no');
				return warnInvalid("#devinfo_media_encryption",_("Please enable DTLS for Media Encryption field first before enabling DTLS parameters"));
			}
	   	}
	});

	$("#devinfo_media_encryption").change(function() {
		if($(this).val() != 'dtls' && $("input[name='dtls_enable']:checked").val() == 'yes'){
			$("input[name='dtls_enable']:checked").val('no');
		}else{
			$("input[name='dtls_enable']:checked").val('yes');
		}
		return false;
	});
});
