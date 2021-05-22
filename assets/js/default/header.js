'use strict';
 
angular.module('Header', ['ui.bootstrap'])
.controller('headerController', 
function (StorageService, $scope, $location, $http, $rootScope, $uibModal, $log, $document, $filter, $routeParams, $window, MyData, $cookieStore, Page, $locale, $translate, tmhDynamicLocale) { 
	$scope.MyData = MyData;
	
	$rootScope.dateFormatString = "dd-MMM-yy";
	$rootScope.timeFormatString = "hh:mm a";
	$rootScope.currency_sign = '₱';
	
	$locale.NUMBER_FORMATS.DECIMAL_SEP = ',';
	$locale.NUMBER_FORMATS.GROUP_SEP = '.';	

	$rootScope.get_currency_settings = function(){
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

					} else if ( res_data[x].key == "decimal_mark" ) {
						$locale.NUMBER_FORMATS.DECIMAL_SEP =  res_data[x].value;
					} else if ( res_data[x].key == "thousands_separator" ) {
						$locale.NUMBER_FORMATS.GROUP_SEP =  res_data[x].value;
					} else if ( res_data[x].key == "currency_symbol" ) {
						$rootScope.currency_sign =  res_data[x].value;
					}
				}
			}

		}, function(){
			//$rootScope.dateFormatString = "dd-MMM-yy";
		});		
	}
	$rootScope.get_currency_settings();

    $rootScope.checkNotificationPattern = function (str){
        var x, c, matches, match_len, y, temp_matches;
        var expr = [
            { translate_id: "notif_logged_in", regex:/([\s\S]*?) has logged in/ },
            { translate_id: "notif_logged_out", regex:/([\s\S]*?) has logged out/ }
        ], len = expr.length;

        for(x=0; x<len; x++){
            matches = str.match(expr[x].regex);
            if (matches != null) { 
                temp_matches = {};
                match_len= matches.length;
                for(y=0; y<match_len; y++) {
                    temp_matches['var_' + y] = matches[y];
                }
                
                expr[x].matches = temp_matches;
                return expr[x];
            }
        }
        return null;
    }


    $rootScope.changeLanguage = function (key = "none") {
        if(key == "none"){
	        $rootScope.lang = angular.isUndefined($cookieStore.get('lang')) ? 'en' : $cookieStore.get('lang');
        }
        else{
        	$rootScope.lang = key;
        	$cookieStore.remove('lang');
        	$cookieStore.put('lang', key);
        }
        $translate.use($rootScope.lang);
    };
    $rootScope.changeLanguage();

	$rootScope.curr_loc = $location.absUrl();
	$rootScope.notificationCount = 0;

	var cookieName = 'viewedNotifID-' + $location.host();

	$scope.to_notification = function() {
		$rootScope.ClearNotifications();
		$location.path('/notification');
	}
	
	$rootScope.getCurrenDateNotification = function() {
		var success = false;

		$http.get('rest/api/v1/notification').then(function(res) {
			if(res.data.status == "success") {

                var res_data = res.data.data, len=res_data.length, x, match;
                for(x=0; x<len; x++){
                    
                    match = $rootScope.checkNotificationPattern(res_data[x].message);
                    res_data[x].data = null;
                    if( match != null) {
                        res_data[x].data = match.matches;
                        res_data[x].message = match.translate_id;
                    }
                }

                //$scope.notification_data = res_data;

				MyData.collection = res_data;

				console.log(res_data);
				$scope.addViewedStatus();
			}
		});
	}

	$scope.addViewedStatus = function(){
		var data = '', len=0, x=0;
		data = MyData.collection;

		$rootScope.notificationCount = 0;
		len = data.length;

		/* var allViewed = $cookieStore.get(cookieName); */
		var allViewed = StorageService.get(cookieName) || {'date': '', 'id': []};
		allViewed = allViewed === undefined ? {'date': '', 'id': []} : allViewed;

		for(x=0; x<len; x++) {
			if (allViewed.id.indexOf(data[x].notification_id) !== -1) {
				data[x].viewed = true;
			} else {
				data[x].viewed = false;
				$rootScope.notificationCount++;
			}	
		}		
	}
	
	var viewed = {'date': '', 'id': []};
	$scope.addToViewed = function(notifId){
		var d = new Date();
		viewed = StorageService.get(cookieName) || {'date': '', 'id': []};
		if(viewed.date !== d.toLocaleDateString()){
			viewed.date = d.toLocaleDateString();
			viewed.id = [];
		}
		if (viewed.id.indexOf(notifId) == -1) {
			viewed.id.push(notifId);
			/* $cookieStore.put(cookieName, viewed); */
			StorageService.put(cookieName, viewed);
			$scope.addViewedStatus();
			//$scope.addViewedStatus1();
		}
	}
	
	$rootScope.ClearNotifications = function() {
		var notification = $scope.MyData.collection;
		for(var x=0; x<notification.length; x++) {
			$scope.addToViewed(notification[x].notification_id);
		}
	}
	
	$scope.reload = function(){
		$window.location.reload();
	}

	$scope.logout = function(){
		var data = $.param({
				'user_id': $rootScope.globals.currentUser.id,
				'fname': $rootScope.globals.currentUser.firstname,
				'lname': $rootScope.globals.currentUser.lastname
			});
		var config = {
				headers : {
				  'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8;'
				}	
			};
		$http.post('rest/api/v1/logout', data, config).then(function(res) {
			$window.location.href = '#/login';
		});
	}

   return $rootScope.$on('$translateChangeSuccess', function () {
       //console.log("It entered translateChangeSuccess");
       tmhDynamicLocale.set($rootScope.lang);

   });	
});