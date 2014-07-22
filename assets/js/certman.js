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
	$("#capage #caexistscheck").change(function() {
		if ($(this).is(":checked")) {
			$("#capage .selection button").prop("disabled", false);
		} else {
			$("#capage .selection button").prop("disabled", true);
		}
	});
	$("#capage button.submit").click(function() {
		if ($(this).data("submitting")) {
			return false;
		}
		var type = $(this).data("type"), r = true;
		$("#catype").val(type);
		if (type == "generate") {
			$("#capage input[type=\"text\"]").each( function(i, v) {
				if ($(this).val() === "") {
					alert("No Fields Can Be Left Blank");
					r = false;
					return false;
				}
			});
			if ($("#capage input[type=\"password\"]").val() === "") {
				if (!confirm("Are you sure you dont want a passphrase?")) {
					$("#capage input[type=\"password\"]").focus();
					return false;
				}
			}
		} else if (type == "upload") {
		} else if (type == "delete") {
			r = confirm("Are you sure you want to delete the Certificate Authority?");
		} else {
			r = false;
		}
		if (r === true) {
			if ($(this).data("type") == "generate") {
				$(this).text("Generating.. Please wait");
			}
			$(this).data("submitting", true);
		}
		return r;
	});
	$("#certpage .selection button.visual").click(function() {
		var type = $(this).data("type");
		$("#certpage .general").fadeIn("slow");
		$("#certpage ." + type).fadeIn("slow");
		$("#certpage .selection").fadeOut("slow");
		if ($("#ca :selected").data("requirespassphrase") == "yes") {
			$("#certpage .passphrase").fadeIn("slow");
		}
		return false;
	});
	$("#certpage button.submit").click(function() {
		var type = $(this).data("type"), r = true;
		$("#certtype").val(type);
		$("#certpage input[type=\"text\"]").each( function(i, v) {
			if ($(this).val() === "") {
				alert("No Fields Can Be Left Blank");
				r = false;
				return false;
			}
			if ($(this).prop("name") == "name") {
				if (!isAlphanumeric($(this).val())) {
					alert("Name Must Be Alphanumeric!");
					r = false;
					return false;
				}
			}
		});
		if ($("#certpage .passphrase").is(":visible") && $("#certpage .passphrase input[type=\"password\"]").val() === "") {
			r = confirm("Are you sure there is no passphrase for the Certificate Authority?");
		}
		return r;
	});
});
