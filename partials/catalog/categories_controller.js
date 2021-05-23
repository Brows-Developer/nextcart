'use strict';
 
angular.module('Category', ['ui.bootstrap']).controller('CategoriesControllers', function (Page, $scope, $location, $http, $rootScope, $uibModal, $window, $uibModalStack, $filter, $route) { 

	/*Get User's Details*/
	$rootScope.fname = $rootScope.globals.currentUser.firstname;
	$rootScope.lname = $rootScope.globals.currentUser.lastname;
	$rootScope.user_id = $rootScope.globals.currentUser.id;
	$rootScope.prof_pic = $rootScope.globals.currentUser.prof_pic;
	$rootScope.title1 = $rootScope.globals.currentUser.title;

	$scope.loading = true;

})