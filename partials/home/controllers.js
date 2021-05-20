'use strict';
 
angular.module('Home', ['ui.bootstrap']).controller('HomeController', function (Page, $scope, $location, $http, $rootScope, $uibModal, $window, $uibModalStack, $filter, $route) { 
	/** 
		Function for getting the user information who login code start 
	**/
  $rootScope.fname = $rootScope.globals.currentUser.firstname;
  $rootScope.lname = $rootScope.globals.currentUser.lastname;
  $rootScope.user_id = $rootScope.globals.currentUser.id;
  $rootScope.prof_pic = $rootScope.globals.currentUser.prof_pic;
  $rootScope.title1 = $rootScope.globals.currentUser.title;

  console.log("This is home");

})