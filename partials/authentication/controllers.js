'use strict';
 
angular.module('Authentication')
 
.controller('LoginController',
    ['$scope', '$rootScope', '$location', 'AuthenticationService', 'permissions', '$http',	
    function ($scope, $rootScope, $location, AuthenticationService, permissions, $http) {
        // reset login status
        AuthenticationService.ClearCredentials();
		
		$http.get('rest/api/v1/global_variables/login_header_text').then(function(res) {
			$scope.login_for_heading = res.data.data;
		});
		
		$http.get('rest/api/v1/global_variables/login_image').then(function(res) {
			$scope.login_image = res.data.data;
		});


		
        $scope.login = function () {
            $scope.dataLoading = true;
            AuthenticationService.Login($scope.username, $scope.password, function(response) {
                if(response.success) {
					permissions.roles($rootScope.currentUser.roles);
					AuthenticationService.SetCredentials($rootScope.currentUser);
					$rootScope.fromLogIn = true;
					$scope.redirect();
                    //$location.path('/housekeeping');
                } else {
					$scope.error = response.message;
                    $scope.dataLoading = false;
					$scope.username = '';
					$scope.password = '';
                }
            });
        };
		$scope.redirect = function() {
			$location.path($rootScope.currentUser.roles[0].start_url);
			permissions.reload();
		};
    }]);