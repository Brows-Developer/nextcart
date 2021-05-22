'use strict';
 
angular.module('Setting', ['ui.bootstrap']).controller('SettingsControllers', function (Page, $scope, $location, $http, $rootScope, $uibModal, $window, $uibModalStack, $filter, $route) { 

	/*Get User's Details*/
	$rootScope.fname = $rootScope.globals.currentUser.firstname;
	$rootScope.lname = $rootScope.globals.currentUser.lastname;
	$rootScope.user_id = $rootScope.globals.currentUser.id;
	$rootScope.prof_pic = $rootScope.globals.currentUser.prof_pic;
	$rootScope.title1 = $rootScope.globals.currentUser.title;

	$scope.loading = true;

	$scope.getSettings = function() {
		$scope.loading = true;
		$http.get('rest/api/v1/settings').then(function(res) {
			if(res.data.status == "success") {
				$scope.loading 	= false;
				$scope.settings = res.data.data;
			}
		});
	}
	$scope.getSettings();

	$scope.editSetting = function(setting){
		var size = 'm';
		var modalInstance = $uibModal.open({
			templateUrl: 'editSetting.html',
			controller: 
				function($rootScope, $scope, $uibModalInstance, $http, setting) {
					$scope.setting = setting;

					$scope.saveSetting = function(setting) {
						var data = $.param({
							'setting' : setting,
							'inserted_by' : $rootScope.user_id
						});

						console.log(setting);
						$http.put('rest/api/v1/settings', data).then(function(res) {
							if(res.data.status == "success") {
								$uibModalInstance.close('reload'); 
							}
						});
					};

					$scope.ok = function (a) { 
						$uibModalInstance.close(a); 
					};
					$scope.cancel = function () { 
						$uibModalInstance.dismiss('cancel'); 
					};	
				},
			size: size,
			resolve: {
				setting:  function() {
					return setting;
				}
			}
		});
		modalInstance.result.then( 
			function (a) {
				if(a == 'reload') { 
					$scope.getSettings();
				}
			}, 
			function (a) {
				//$log.info('Modal dismissed at: ' + new Date());
			}
		);
	}
})