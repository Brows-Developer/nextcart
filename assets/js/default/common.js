 function isNumberKey(evt)
      {
		var charCode = (evt.which) ? evt.which : event.keyCode
		if (charCode > 31 && (charCode < 43 || charCode > 57))
		return false;
		return true;
      } // allow numeric inppur only



 function isAlphaKey(evt)
	 {
		var charCode = (evt.which) ? evt.which : event.keyCode
		if (charCode > 31 && (charCode < 48 || charCode > 57) && (charCode < 33 || charCode > 47) || charCode == 44)
		return true;
		return false;
	 } // allow alphabet input only

