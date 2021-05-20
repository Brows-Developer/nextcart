angular.module('Users', ['ui.bootstrap']).controller('UsersControllers', function ($scope, $location, $http, $rootScope, $uibModal, $log, $document, modalService, $filter) { /*$scope, $location, $http, $rootScope, $modal*/
	$scope.loading = true;
	$scope.getUser = function() {
		$scope.loading = true;
		$http.get('rest/api/v1/users').then(function(res) {
			if(res.data.status == "success") {
				$scope.loading = false;
				$scope.users = res.data.data;
			}
		});
	}
	$scope.getUser();
	$scope.getRoles = function() {
		$http.get('rest/api/v1/roles').then(function(res) {
			if(res.data.status == "success") {
				$scope.roles = res.data.data;
			}
		});
	}
	$scope.getRoles();
	$scope.addUser = function() {
		var size = 'm';
		var modalInstance = $uibModal.open({
			templateUrl: 'addUser.html',
			controller: 
				function($rootScope, $scope, $uibModalInstance, $http, roles) {
					$scope.roles = roles;
					$scope.saveUser = function(users) {
						var data = $.param({
							'users' : users,
							'inserted_by' : $rootScope.user_id
						});
						$http.post('rest/api/v1/users', data).then(function(res) {
							if(res.data.status == "success") {
								if(res.data.exist == 1) {
									$scope.post_response = res.data;
								} else {
									$scope.post_response = res.data;
									$uibModalInstance.close('reload'); 
								}
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
				roles:  function() {
					return $scope.roles;
				}
			}
		});
		modalInstance.result.then( 
			function (a) {
				if(a == 'reload') { 
					$scope.getUser();
				}
			}, 
			function (a) {
				//$log.info('Modal dismissed at: ' + new Date());
			}
		);
	}
	$scope.updateUser = function(user) {
		console.log(user);
		var old_user_data = angular.copy(user);
		var size = 'm';
		var modalInstance = $uibModal.open({
			templateUrl: 'updateUser.html',
			controller: 
				function($rootScope, $scope, $uibModalInstance, $http, roles, user, old_user_data) {
					$scope.roles = roles;
					$scope.users = {
						users_id: user.users_id,
						firstname: user.firstname,
						lastname: user.lastname,
						title: user.title,
						role: user.role_id,
						username: user.username
					}
					$scope.old_user_data = {
						users_id: old_user_data.users_id,
						firstname: old_user_data.firstname,
						lastname: old_user_data.lastname,
						title: old_user_data.title,
						role: old_user_data.role_id,
						username: old_user_data.username
					}
					$scope.saveUser = function(users) {
						var data = $.param({
							'users' : users,
							'old_users' : $scope.old_user_data,
							'inserted_by' : $rootScope.user_id
						});
						$http.put('rest/api/v1/users', data).then(function(res) {
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
				roles:  function() {
					return $scope.roles;
				},
				user: function() {
					return user;
				},
				old_user_data: function() {
					return old_user_data;
				}
				
			}
		});
		modalInstance.result.then( 
			function (a) {
				if(a == 'reload') { 
					$scope.getUser();
				}
			}, 
			function (a) {
				//$log.info('Modal dismissed at: ' + new Date());
			}
		);
	}
	$scope.remove = function(user) {
		var modalOptions = {
            closeButtonText: 'Cancel',
            actionButtonText: 'Delete User',
            headerText: 'Delete User: ' + user.username + '?',
            bodyText: 'Are you sure you want to delete ' + user.username + '?'
        };
		modalService.showModal({}, modalOptions).then(function (result) {
			$scope.loading = true;
			$http.delete('rest/api/v1/users/'+user.users_id+ '/' +$rootScope.user_id).then(function(res) {
				//console.log(res.data);
				if(res.data.status == "success") {
					$scope.getUser();
				} else {
					//do nothing
					/* alert(res.data.message); */
				}
			});
		});
	}
	/*Get User's Details*/
	$rootScope.fname = $rootScope.globals.currentUser.firstname;
	$rootScope.lname = $rootScope.globals.currentUser.lastname;
	$rootScope.user_id = $rootScope.globals.currentUser.id;
	$rootScope.prof_pic = $rootScope.globals.currentUser.prof_pic;
	$rootScope.title1 = $rootScope.globals.currentUser.title;
})
.service('modalService',
function ($uibModal) {

	var modalDefaults = {
		backdrop: true,
		keyboard: true,
		modalFade: true,
		templateUrl: 'partials/settings/views/modal-confirms.html'
	};

	var modalOptions = {
		closeButtonText: 'Close',
		actionButtonText: 'OK',
		headerText: 'Proceed?',
		bodyText: 'Perform this action?'
	};

	this.showModal = function (customModalDefaults, customModalOptions) {
		if (!customModalDefaults) customModalDefaults = {};
		customModalDefaults.backdrop = 'static';
		return this.show(customModalDefaults, customModalOptions);
	};

	this.show = function (customModalDefaults, customModalOptions) {
		//Create temp objects to work with since we're in a singleton service
		var tempModalDefaults = {};
		var tempModalOptions = {};

		//Map angular-ui modal custom defaults to modal defaults defined in service
		angular.extend(tempModalDefaults, modalDefaults, customModalDefaults);

		//Map modal.html $scope custom properties to defaults defined in service
		angular.extend(tempModalOptions, modalOptions, customModalOptions);

		if (!tempModalDefaults.controller) {
			tempModalDefaults.controller = function ($scope, $uibModalInstance) {
				$scope.modalOptions = tempModalOptions;
				$scope.modalOptions.ok = function (result) {
					$uibModalInstance.close(result);
				};
				$scope.modalOptions.close = function (result) {
					$uibModalInstance.dismiss('cancel');
				};
			}
		}

		return $uibModal.open(tempModalDefaults).result;
	};

});