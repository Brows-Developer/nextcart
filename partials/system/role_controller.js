angular.module('Roles', ['ui.bootstrap']).controller('RolesControllers', function ($scope, $location, $http, $rootScope, $uibModal, $log, $document, modalService, $filter) { 
	$scope.getRoles = function() {
		$scope.loading = true;
		$http.get('rest/api/v1/roles').then(function(res) {
			if(res.data.status == "success") {
				$scope.startPageModules();
				$scope.Modules();
				$scope.roles = res.data.data;
				$scope.loading = false;
			}
		});
	}
	$scope.getRoles();
	$scope.startPageModules = function() {
		$scope.loading = true;
		$http.get('rest/api/v1/modules/startPage').then(function(res) {
			if(res.data.status == "success") {
				$scope.startPage = res.data.data;
				$scope.loading = false;
			}
		});
	}
	$scope.Modules = function() {
		$scope.loading = true;
		$http.get('rest/api/v1/modules').then(function(res) {
			if(res.data.status == "success") {
				$scope.modules = res.data.data;
				$scope.loading = false;
			}
		});
	}
	$scope.addRole = function() {
		var size = 'm';
		var modalInstance = $uibModal.open({
			templateUrl: 'addRole.html',
			controller: 
				function($rootScope, $scope, $uibModalInstance, $http, startPage, modules) {
					$scope.startPage = startPage;
					$scope.modules = modules;
					$scope.role = {
						ids: {"1": true}
					};
					$scope.ok = function (a) { 
						$uibModalInstance.close(a); 
					};
					$scope.cancel = function () { 
						$uibModalInstance.dismiss('cancel'); 
					};
					$scope.saveRole = function(role) {
						var allowed_url = "/,/login,";
						angular.forEach(role.ids, 
							function(value, key) {
								if(value == true) {
									var filter = $filter('filter')($scope.modules, {modules_id: key}, true);
									allowed_url = allowed_url + filter[0].modules_urls +',';
								}
							}
						);
						/* console.log(allowed_url); */
						var data = $.param({
							'role' : role,
							'start_page' : role.start_page.split(',')[0],
							'start_url' : role.start_page.split(',')[1].replace('#', ''),
							'allowed_url' : allowed_url,
							'inserted_by': $rootScope.user_id
						});
						$http.post('rest/api/v1/roles', data).then(function(res) {
							if(res.data.status == "success") {
								$uibModalInstance.close('reload'); 
							}
						});
					};
				},
			size: size,
			resolve: {
				startPage:  function() {
					return $scope.startPage;
				},
				modules: function() {
					return $scope.modules;
				}
			}
		});
		modalInstance.result.then( 
			function (a) {
				if(a == 'reload') { 
					$scope.getRoles();
				}
			}, 
			function (a) {
				//$log.info('Modal dismissed at: ' + new Date());
			}
		);
	}
	$scope.showUpdate = function(role) {
		var size = 'm';
		$scope.loading = true;
		/* console.log(role); */
		$http.get('rest/api/v1/role_modules_relationship/' + role.role_id).then(function(res) {
			if(res.data.status == "success") {
				$scope.loading = false;
				/* console.log(res.data.data); */
				var modalInstance = $uibModal.open({
				templateUrl: 'updateRole.html',
				controller: 
					function($rootScope, $scope, $uibModalInstance, $http, startPage, modules, role_details, role_modules) {
						$scope.startPage = startPage;
						$scope.modules = modules;
						console.log(role_modules);
						var ids = {};
						
						for(var i=0; i<role_modules.length; i++) {
							ids[role_modules[i].module_id] = true;
						}
						
						$scope.role = {
							role_name: role_details.role_name,
							start_page: role_details.modules_id + ',' + role_details.modules_url,
							role_id: role_details.role_id,
							ids: ids
						};

						$scope.old_role = angular.copy($scope.role);
						
						$scope.ok = function (a) { 
							$uibModalInstance.close(a); 
						};
						$scope.cancel = function () { 
							$uibModalInstance.dismiss('cancel'); 
						};
						$scope.saveRole = function(role) {
							var allowed_url = "/,/login,";
							angular.forEach(role.ids, 
								function(value, key) {
									if(value == true) {
										var filter = $filter('filter')($scope.modules, {modules_id: key}, true);
										allowed_url = allowed_url + filter[0].modules_urls +',';
									}
								}
							);
							/* console.log(allowed_url); */
							var data = $.param({
								'role' : role,
								'start_page' : role.start_page.split(',')[0],
								'start_url' : role.start_page.split(',')[1].replace('#', ''),
								'allowed_url' : allowed_url,
								'inserted_by': $rootScope.user_id,

								'old_role': $scope.old_role,
								'old_start_page' : $scope.old_role.start_page.split(',')[0],
								'old_start_url' : $scope.old_role.start_page.split(',')[1].replace('#', '')
							});
							
							$http.put('rest/api/v1/roles', data).then(function(res) {
								if(res.data.status == "success") {
									$uibModalInstance.close('reload');
								}
							});
						};
					},
				size: size,
				resolve: {
					startPage:  function() {
						return $scope.startPage;
					},
					modules: function() {
						return $scope.modules;
					},
					role_details: function() {
						return role;
					},
					role_modules: function() {
						return res.data.data;
					}
				}
			});
			modalInstance.result.then( 
				function (a) {
					if(a == 'reload') { 
						$scope.getRoles();
					}
				}, 
				function (a) {
					//$log.info('Modal dismissed at: ' + new Date());
				}
			);
			}
		});
	}
	/*Get User's Details*/
	$rootScope.fname = $rootScope.globals.currentUser.firstname;
	$rootScope.lname = $rootScope.globals.currentUser.lastname;
	$rootScope.user_id = $rootScope.globals.currentUser.id;
	$rootScope.prof_pic = $rootScope.globals.currentUser.prof_pic;
	$rootScope.title1 = $rootScope.globals.currentUser.title;
});