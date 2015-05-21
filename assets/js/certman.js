var CertmanC = Class.extend({
}), Certman = new CertmanC();
$(function() {
	$("#capage .selection button.visual").click(function() {
		var type = $(this).data("type");
		$("#capage .general").fadeIn("slow");
		$("#capage ." + type).fadeIn("slow");
		$("#capage .selection").fadeOut("slow");
		return false;
	});

	$("#enableul").click(function() {
		console.log($(this).prop("checked"));
		if($(this).prop("checked")){
			$("#pkdiv").removeClass("hidden");
			$("#cdiv").removeClass("hidden");
			$("#certmanSubmit").val(_("Upload Certificate"));
		}else{
			$("#pkdiv").addClass("hidden");
			$("#cdiv").addClass("hidden");
			$("#certmanSubmit").val(_("Generate Certificate"));
		}
	});
	//CA Show/Hide
	$("#replaceCA").click(function(){
		$("#caexists").addClass('hidden');
		$("#caform").removeClass('hidden');
		$("#Submit").prop('disabled', false);
		$("#Reset").prop('disabled', false);
		$('#Delete').prop('disabled', false);
		$("#replace").val("replace");
	});
	if($("#hostname").is(":visible")) {
		$('#Submit').removeClass('hidden');
		$('#Reset').removeClass('hidden');
	}
	$(document).on('shown.bs.tab', 'a[data-toggle="tab"]', function (e) {
	    var clicked = $(this).attr('href');
	    switch(clicked){
			case '#casettings':
				if($("#caexists").length) {
					$('#Delete').removeClass('hidden');
				}
				$('#Submit').removeClass('hidden');
				$('#Reset').removeClass('hidden');
				if($("#caexists").length > 0 && !$("#hostname").is(":visible")){
					$("#Submit").prop('disabled', true);
					$("#Reset").prop('disabled', true);
					$('#Delete').prop('disabled', true);
				} else if($("#hostname").is(":visible")) {
					$("#Submit").prop('disabled', false);
					$("#Reset").prop('disabled', false);
					$('#Delete').prop('disabled', false);
				}
			break;
			default:
				$('#Submit').addClass('hidden');
				$('#Reset').addClass('hidden');
				$('#Delete').addClass('hidden');
			break;
		}
	});
	$("#capage #caexistscheck").change(function() {
		if ($(this).is(":checked")) {
			$("#capage .selection button").prop("disabled", false);
		} else {
			$("#capage .selection button").prop("disabled", true);
		}
	});

	$("#Submit").click(function(e) {
		var stop = false;
		if($("#certpage").length) {

		} else if($("#hostname").is(":visible")) {
			$("#caform input[type=\"text\"]").each( function(i, v) {
				if ($(this).val() === "") {
					warnInvalid($(this),_("Can not be left blank!"));
					stop = true;
					return false;
				}
			});
			if(stop) {
				return false;
			}
			if ($("#caform input[type=\"password\"]").val() === "") {
				if (!confirm(_("Are you sure you dont want a passphrase?"))) {
					$("#caform input[type=\"password\"]").focus();
					return false;
				}
			}
		}
		if(stop) {
			e.stopPropagation();
			e.preventDefault();
		} else {
			$(this).val(_("Generating.. Please wait"));
			$(this).prop("disabled", true);
			$(".fpbx-submit").submit();
		}
	});

	$("#deletecert").click(function() {
		if(!confirm(_("Are you sure you want to delete this certificate?"))) {
			e.stopPropagation();
			e.preventDefault();
		}
	});
});
