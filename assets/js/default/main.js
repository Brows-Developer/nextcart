'use strict';

// declare modules
angular.module('Authentication', []);
angular.module('Home', []);

angular.module('tutorialWebApp', [
    'ui.select',
    'Authentication',
    'Home',
    'ngRoute',
    'ngCookies',
	'color.picker',
	'Users',
	'Roles',
	'ngWebSocket',
	'Header',
	'Notification',
	'Title',
	'Currency',
	'pascalprecht.translate',
	'tmh.dynamicLocale'
])
 
.config(['$routeProvider','$locationProvider' ,'$httpProvider', '$translateProvider', 'tmhDynamicLocaleProvider', function ($routeProvider,$locationProvider, $httpProvider, $translateProvider, tmhDynamicLocaleProvider) {
	$httpProvider.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded;charset=utf-8;';
  	$httpProvider.defaults.headers.put['Content-Type'] = 'application/x-www-form-urlencoded;charset=utf-8;';
	$locationProvider.hashPrefix('');
    $routeProvider
        .when('/', {
            controller: 'LoginController',
            templateUrl: 'partials/authentication/views/login.html',
            hideMenus: true
        })
        .when('/dashboard', {
            controller: 'HomeController',
            templateUrl: 'partials/home/views/home.html'
        })
		.when('/users', {
			controller: 'UsersControllers',
			templateUrl: 'partials/settings/views/users.html'
		})
		.when('/roles', {
			controller: 'RolesControllers',
			templateUrl: 'partials/settings/views/roles.html'
		})
		.when('/notification', {
			controller: 'NotificationController',
			templateUrl: 'partials/notification/views/notification-list.html',
			resolve: {
				ntype: function(){
					return "notification";
				}
			}
		})
		.when('/activity-log', {
			controller: 'NotificationController',
			templateUrl: 'partials/notification/views/notification-list.html',
			resolve: {
				ntype: function(){
					return "activity-log";
				}
			}
		})		
		.when('/currency', {
            controller: 'CurrencyController',
            templateUrl: 'partials/settings/views/currency.html'
        })        
        .otherwise({ redirectTo: '/' });
		
		$httpProvider.interceptors.push('APIPathInterceptor');

	    $translateProvider
	    .useStaticFilesLoader({
	        prefix: './locales/locale-',
	        suffix: '.json'
	    }) 
	    // remove the warning from console log by putting the sanitize strategy
	    //.useSanitizeValueStrategy('sanitizeParameters')    
	    .useSanitizeValueStrategy('escapeParameters')    
	    //.useSanitizeValueStrategy('sce') 
	    .preferredLanguage('en');

	    tmhDynamicLocaleProvider.localeLocationPattern('./locales/formats/angular-locale_{{locale}}.js');
	    tmhDynamicLocaleProvider.defaultLocale('en');

}])
.factory('permissions', function($rootScope, $window) {
  var permissionList = [];
  return {
	roles: function(rolesList) {
		permissionList = rolesList;
	},
	allowedURL_exist: function(URL) {
		var keepGoing = true, match = false;
		if($rootScope.globals.currentUser != null) {
			angular.forEach($rootScope.globals.currentUser.roles, function(value, key){
				if(keepGoing) {
					if(value.allowed_url.includes(URL) == true){
					  keepGoing = false;
					  match = true;
					}
				}
			});
		}
		return match;
	},
	getRoles: function() {
		if($rootScope.globals.currentUser != null) {
			return $rootScope.globals.currentUser.roles;
		}
	},
	reload: function() {
		if($rootScope.fromLogIn) {	
			$rootScope.fromLogIn = false;
			/* setTimeout(function(){window.location.reload();},1); */
		}
	}
  };
})
.directive('hasPermission', function(permissions) {  
  return {
    link: function(scope, element, attrs) {
		var roles = permissions.getRoles(), match = false;
		angular.forEach(roles, function(value, key) {
			if(attrs.hasPermission == value.role_name) {
				match = true;
			}
		});
		if(match) {
			element[0].style.display = 'block';
		} else {
			element[0].style.display = 'none';
		}
    }
  };
})
.run(['$rootScope', '$location', '$cookieStore', '$http', 'permissions',
    function ($rootScope, $location, $cookieStore, $http, permissions) {
    	$rootScope.lang = 'en';
        // keep user logged in after page refresh
        $rootScope.globals = $cookieStore.get('globals') || {};
		$rootScope.modules = $cookieStore.get('modules') || {};
		$rootScope.sub_modules = $cookieStore.get('sub_modules') || {};
        if ($rootScope.globals.currentUser) {
            $http.defaults.headers.common['Authorization'] = 'Basic ' + $rootScope.globals.currentUser.authdata; // jshint ignore:line
        }
 
        $rootScope.$on('$locationChangeStart', function (event, next, current) {
            // redirect to login page if not logged in
			$rootScope.curr_loc = $location.absUrl();
            if ($location.path() !== '/' && !$rootScope.globals.currentUser) {
                $location.path('/');
            }
			
			/* console.log("Next: " + next.substring(next.indexOf('#')+1, next.length) + ", Current: " + current.substring(current.indexOf('#')+1, current.length));
			console.log(permissions.allowedURL_exist(next.substring(next.indexOf('#')+1, next.length))); */
			/* check Allowed URL for a specific Users */
            var url = next.substring(next.indexOf('#')+1, next.length).replace(/[0-9]/g, '');
            for (;;) { 
                if(url[url.length-1] == '/') {
                    url = url.slice(0, -1)
                } else {
                    break;
                }
            }
			if($rootScope.globals.currentUser && permissions.allowedURL_exist(url) == false) {
        //alert("You're not allowed to view this page.");
				$location.path(current.substring(current.indexOf('#')+1, current.length));
			}
        });
		
 }]).directive('highlightOnhover', function () {
    return {
      restrict: 'A',
      link: function ($scope, element, attrs) {
          element.on('mouseenter', function () { 
              var cellIdx = element[0].cellIndex,
                    table = element.closest("table");
               // console.log(cellIdx);
              if(cellIdx > 0) {
                  jQuery(table).find('tr').each(function(){
                    $(this).children('td, th').eq(cellIdx).addClass('highlightColumn');
                  });
              }
          });
          element.on('mouseleave', function () {
              var table = element.closest("table");
              jQuery(table).find('tr').children('td, th').removeClass('highlightColumn');
          });
      }
    };
}).directive('loading', ['$http', function ($http) {
    return {
        restrict: 'AE',
        link: function (scope, elm, attrs) {
            scope.isLoading = function () {
                //console.log('Remaining: ' + $http.pendingRequests.length);
                //scope.remained = $http.pendingRequests.length;
                return $http.pendingRequests.length > 0;
            };
            scope.iAmLoading = scope.isLoading();

            scope.$watch(scope.isLoading, function (v) {
                scope.iAmLoading = v;
                //if (!v) console.log('All loaded');
            });
        }
    };
}]).factory('MyData', function($websocket, $rootScope, $location, $http) {
  // Open a WebSocket connection
	var collection = [];
	var roomOptim = 0;
	var unallocated_bkngs = [];
	var split_rooms = [];
	var msg = {}, update = [], cashbox=[];
	var dataStream;
	// $http.get('rest/api/v1/get_websocket_url').then(function(res) {
	// 	dataStream = $websocket(res.data.data);
	// 	dataStream.onOpen(function(e) {
	// 		console.log("Connection established!");
	// 		//$rootScope.getCurrenDateNotification();
	// 	});

	// 	dataStream.onError(function() {
	// 	  //console.log("Connection Error!");
	// 	  $rootScope.WebSocketError = true;
	// 	});

	// 	dataStream.onMessage(function(message) {
	// 		/* console.log(message); */
	// 		msg = JSON.parse(message.data);
	// 		/* console.log(msg); */
	// 		collection.push(msg);
	// 		/* update.pop(); */
	// 		/* if(msg.action == "table_update") {
	// 		  if(msg.table == 'ticket') {
	// 			$rootScope.updateTicketList();
	// 		  }
	// 		} */
	// 		// if( msg.action == "cashbox" && msg.host == $location.host() ) {
	// 		// 	$rootScope.getCashbox_check(msg.data);
	// 		// 	/* console.log($rootScope); */
	// 		// }
	// 		// if( ( msg.action == "reservation" || msg.action == "notify" ) && msg.host == $location.host()) {
	// 		// 	$rootScope.getCurrenDateNotification();
	// 		// }
	// 		// if((msg.action == "table_update" && msg.cashbox_update == 'yes') && msg.host == $location.host()){
	// 		// 	$rootScope.get_cashbox_update();
	// 		// }
	// 		if((msg.action == "item")){
	// 			// $rootScope.select_item();
	// 			console.log("item has been inserted: notifications");
	// 		}
	// 		if((msg.action == "order")){
	// 			// $rootScope.select_item();
	// 			console.log("order has been inserted: notifications");
	// 		}
	// 	});
	// });
	
	var methods = {
		collection: collection,
		roomOptim: roomOptim,
		unallocated_bkngs: unallocated_bkngs,
		split_rooms: split_rooms,
		table_updated: update,
		cashbox: cashbox
	};
	return methods;
}).filter('dateToISO', function() {
  return function(input) {
    input = new Date(input).toISOString();
    return input;
  };
}).factory('Page', function($http){
  var title = 'iServe';
  var icon = 'images/logo3.ico';
  return {
    title: function() { 
		return title; 
	},
    setTitle: function(newTitle) { 
		title = newTitle; 
	},
	favicon: function() { 
		return icon; 
	},
	setFavicon: function(newFavicon) { 
		icon = newFavicon;
	}
  };
}).service('StorageService', function() {
	this.get = function(key) {
		return JSON.parse(localStorage.getItem(key));
	};
	this.put = function(key, value) {
		localStorage.setItem(key, JSON.stringify(value));
	};
	this.remove = function(key) {
		localStorage.removeItem(key);
	};
	this.clear = function() {
		localStorage.clear();
	};
}).factory('APIPathInterceptor', function($location){
	var path = {
			request: function(config) {
				if( config.url.indexOf('rest/') !== -1 ) { // fix cache problem on 67. server
					config.headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
					config.headers['Pragma'] = 'no-cache';
					config.headers['Expires'] = '0';   
				}
				return config;
			}
	};
	return path;
}).filter("mysqlDateFormatToTimestamp", function(){
    return function(date){
        var date1 =  '', date2 = '', date3 = '', timestamp = '', hours = '', minutes = '', seconds = '';               
        date1 = date.split(':'); 
        date2 = date1[0].split(' '); 
        date3 = date2[0].split('-'); // Change it based on your format
        if( date1.length == 1 && date2.length == 1 ){
            hours = '00';
            minutes = '00';
            seconds = '00';
        }else{
            hours = parseInt(date2[1]);
            minutes = parseInt(date1[1]);
            seconds = parseInt(date1[2]);
        } 
        timestamp = new Date(parseInt(date3[0]), parseInt(date3[1])-1, parseInt(date3[2]), hours, minutes, seconds);
        return timestamp;
   } 
}).filter('searchAndFormatDate', [ '$filter', '$rootScope' ,  function ($filter, $rootScope) { /* used in activity log */
  return function (str) {
    str = str || '';

    let x, c;
    /* for date format dd/mm/yyyy e.g. 31/12/2019 */
    let regex = /(([1-2][0-9])|([1-9])|(3[0-1]))[\/-]((1[0-2])|([1-9])|(0[1-9]))[\/-][0-9]{4}/g
    let matches = str.match(regex);
    if (matches != null) {
    	var dateArray, newDate;
		for( x=0,c=matches.length; x<c; x++ ) {
			dateArray = matches[x].split('/');
			newDate = new Date(dateArray[1]+'-'+dateArray[0]+'-'+dateArray[2]);
			//var dateMatch = $filter('date')( newDate, "dd/mm/yyyy");
			str = str.replace( 
				matches[x], 
				$filter('date')( newDate, $rootScope.dateFormatString) 
			);
			str = str.replace( 
				"00"+matches[x], 
				$filter('date')( newDate, $rootScope.dateFormatString) 
			);
		}

	} 

    /* for date format yyyy-mm-dd e.g. 2019-12-31 */
    regex = /[0-9]{4}[\/-]((1[0-2])|([1-9])|(0[1-9]))[\/-](([0-2][0-9])|([1-9])|(3[0-1]))/g
    matches = str.match(regex);	
    if (matches != null) {
		for( x=0, c=matches.length; x<c; x++ ) {
			str = str.replace( 
				matches[x], 
				$filter('date')( matches[x], $rootScope.dateFormatString) 
			);
		}

	} 

	return str;

  };
}]).filter("ddmmyyToDate", [ '$filter', '$rootScope' ,  function ($filter, $rootScope) {
    return function(from){
    	from = from.split("/");
        return $filter('date')( new Date( from[2] + "-" + from[1] + "-" + from[0] ), $rootScope.dateFormatString);
   } 
}]).filter("yyyy-mm-ddToDate", [ '$filter', '$rootScope' ,  function ($filter, $rootScope) {
    return function(str_date){
        var arr_date = str_date.split("-");
		var result_data = '';
		if(arr_date.length == 3){ // must be 3 array values (year, month, day)
			var isValid = true;
			for(var x=0; x<arr_date.length; x++){ // check if all are integers
				if(isNaN(arr_date[x])){ 
					isValid = false;
					break;
				}
			}
			if(isValid){
				result_data = new Date(arr_date[0], arr_date[1] - 1, arr_date[2]);
			}
			else{
				result_data = new Date(str_date);
			}
		}
		else{
			result_data = new Date(str_date);
		}
		return result_data;
    } 
}])
.config(['$qProvider', function($qProvider){
   $qProvider.errorOnUnhandledRejections(false);
}]);