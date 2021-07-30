'use strict';
 
angular.module('ProductAdd', ['ui.bootstrap']).controller('ProductAddControllers', function (Page, $scope, $location, $http, $rootScope, $uibModal, $window, $uibModalStack, $filter, $route) { 

	/*Get User's Details*/
	$rootScope.fname = $rootScope.globals.currentUser.firstname;
	$rootScope.lname = $rootScope.globals.currentUser.lastname;
	$rootScope.user_id = $rootScope.globals.currentUser.id;
	$rootScope.prof_pic = $rootScope.globals.currentUser.prof_pic;
	$rootScope.title1 = $rootScope.globals.currentUser.title;

	$scope.loading = false;

	$scope.newProduct = {};
	$scope.newProduct.Media = [];

	$scope.Margin = "-";
	$scope.Profit = 0;

	$scope.getVendor = function(){
		$scope.loading = true;
		$http.get('rest/api/v1/vendor').then(function(res) {
			if(res.data.status == "success") {
				$scope.availableVendor = res.data.vendor;
				$scope.loading = false;
			}
		});
	}
	$scope.getVendor();

	$scope.getProductType = function(){
		$scope.loading = true;
		$http.get('rest/api/v1/product_type').then(function(res) {
			if(res.data.status == "success") {
				$scope.availableProductType = res.data.product_type;
				$scope.loading = false;
			}
		});
	}
	$scope.getProductType();

	$scope.calculateMarginProfit = function(CostPerItem, Price){
		if(Price == 0 || Price == null){
			$scope.Margin = "-";
			$scope.Profit = 0;
		}else{
			var margin = Price - CostPerItem
			$scope.Margin = margin + "%";
			$scope.Profit = Price - CostPerItem;
		}
	}

	$scope.AddNewProduct = function(newProduct){
		var data = $.param(newProduct);

		$http.post('rest/api/v1/product', data).then(function(res) {
			if(res.data.status == "success") {
				
			}
		});
	}


})

.directive('fileModel', function ($parse) {
    return {
        restrict: 'A', //the directive can be used as an attribute only

        /*
         link is a function that defines functionality of directive
         scope: scope associated with the element
         element: element on which this directive used
         attrs: key value pair of element attributes
         */
        link: function (scope, element, attrs) {
            var model = $parse(attrs.fileModel),
                modelSetter = model.assign; //define a setter for fileModel
            //Bind change event on the element
            element.bind('change', function () {
                //Call apply on scope, it checks for value changes and reflect them on UI
                scope.$apply(function () {
                    //set the model value
                    var s = modelSetter(scope, element[0].files[0]);
                    scope.newProduct.displayMedia.push(s);
                    console.log(scope.newProduct);
                });
            });
        }
    };
});