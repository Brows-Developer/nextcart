'use strict';
 
angular.module('Title', ['ui.bootstrap'])
.controller('TitleController', 
function ($scope, $location, $http, $rootScope, $uibModal, $log, $document, $filter, $routeParams, $window, MyData, $cookieStore, Page) { 
	$scope.Page = Page;
	
	$http.get('rest/api/v1/global_variables/site_title').then(function(res) {
		Page.setTitle(res.data.data);
	});
	
	$http.get('rest/api/v1/global_variables/favicon').then(function(res) {
		Page.setFavicon(res.data.data);
	});
});