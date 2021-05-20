angular.module('Currency', ['ui.bootstrap']).controller('CurrencyController', function ($scope, $location, $http, $rootScope, $uibModal, $log, $document, modalService, $filter, $locale) { 

	$rootScope.fname = $rootScope.globals.currentUser.firstname;
	$rootScope.lname = $rootScope.globals.currentUser.lastname;
	$rootScope.user_id = $rootScope.globals.currentUser.id;
	$rootScope.prof_pic = $rootScope.globals.currentUser.prof_pic;
	$rootScope.title1 = $rootScope.globals.currentUser.title;	


	/*
	$scope.currency_symbol = $rootScope.currency_sign;
	$scope.currency_decimal_mark = $locale.NUMBER_FORMATS.DECIMAL_SEP;
	$scope.currency_thousands_separator = $locale.NUMBER_FORMATS.GROUP_SEP;
	*/
	$scope.getCurrencyData = function(){
		$scope.loading = true;
		var global_keys =   { keys: "date_format,currency,decimal_mark,thousands_separator,currency_symbol" };
		$http.get('rest/api/v1/get_multiple_global_variables',  { params: global_keys }).then(function(res) {
			//$rootScope.dateFormatString = res.data.data;
			if(res.data.status == "success"){
				var len = res.data.data.length, x, res_data = res.data.data;
				for( x=0; x<len; x++ ) {
					if( res_data[x].key == "date_format" ){
						//console.log( res_data[x] );
						$rootScope.dateFormatString = res_data[x].value;
					} else if ( res_data[x].key == "currency" ) {
						$scope.currency_code = res_data[x].value;
					} else if ( res_data[x].key == "decimal_mark" ) {
						$scope.currency_decimal_mark =  res_data[x].value;
					} else if ( res_data[x].key == "thousands_separator" ) {
						$scope.currency_thousands_separator =  res_data[x].value;
					} else if ( res_data[x].key == "currency_symbol" ) {
						$scope.currency_symbol =  res_data[x].value;
					}
				}
			}
			$scope.loading = false;

		}, function(){
			//$rootScope.dateFormatString = "dd-MMM-yy";
			$scope.loading = false;
		});		
	}
	$scope.getCurrencyData();


	$scope.saveCurrency = function(){
		var data = {
			keys: {
				'currency_symbol' : $scope.currency_symbol,
				'currency': $scope.currency_code,
				'decimal_mark' : $scope.currency_decimal_mark,
				'thousands_separator' : $scope.currency_thousands_separator				
			},
			'inserted_by': $rootScope.user_id
			
		};
		console.log(data);
		data = $.param( data );
	
		$http.post('rest/api/v1/currency_settings', data).then(function(res) {
			if(res.data.status == "success") {
				//$uibModalInstance.close('reload'); 
				//console.log(res.data);
				$rootScope.get_currency_settings();
			}
		});
	}

	
});