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
		var type = $(this).data("type");
		$("#catype").val(type);
		if (type == "generate") {
			$("#capage input[type=\"text\"]").each( function(i, v) {
				if ($(this).val() === "") {
					alert("No Fields Can Be Left Blank");
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
			return confirm("Are you sure you want to delete the Certificate Authority?");
		} else {
			return false;
		}
		return true;
	});
	$("#certpage .selection button.visual").click(function() {
		var type = $(this).data("type");
		$("#certpage .general").fadeIn("slow");
		$("#certpage ." + type).fadeIn("slow");
		$("#certpage .selection").fadeOut("slow");
		return false;
	});
	$("#certpage button.submit").click(function() {
		var type = $(this).data("type");
		$("#certtype").val(type);
		$("#certpage input[type=\"text\"]").each( function(i, v) {
			if ($(this).val() === "") {
				alert("No Fields Can Be Left Blank");
				return false;
			}
			if ($(this).prop("name") == "name") {
				if (!isAlphanumeric($(this).val())) {
					alert("Name Must Be Alphanumeric!");
					return false;
				}
			}
		});
		return true;
	});
});
