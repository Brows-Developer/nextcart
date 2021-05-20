'use strict';
 
angular.module('Notification', ['ui.bootstrap']).controller('NotificationController', 
  function (MyData, $scope, $location, $http, $rootScope, $uibModal, $log, $document, $filter, $window, ntype, $cookieStore, $translate) { 
    
    $rootScope.fname = $rootScope.globals.currentUser.firstname;
    $rootScope.lname = $rootScope.globals.currentUser.lastname;
    $rootScope.user_id = $rootScope.globals.currentUser.id;
    $rootScope.prof_pic = $rootScope.globals.currentUser.prof_pic;
    $rootScope.title1 = $rootScope.globals.currentUser.title;

	$rootScope.ClearNotifications();
	
    $scope.notif_or_log = ntype || null;

    $scope.get_notification = function(start_date, end_date, type) {
    	type = type ? type : 'all';
        $http.get('rest/api/v1/notification/' + start_date +'/' +  end_date +'/'+ type + '/' + $scope.notif_or_log).then(function(res) {
            if(res.data.status == "success") {
                //console.log(res.data.data);
                var res_data = res.data.data, len=res_data.length, x, match;
                for(x=0; x<len; x++){
                    
                    match = $rootScope.checkNotificationPattern(res_data[x].message);
                    //console.log(match);
                    res_data[x].data = null;
                    if( match != null) {
                        res_data[x].data = match.matches;
                        res_data[x].message = match.translate_id;
                    }
                    //console.log(match);
                }

                //$scope.notification_data = res.data.data;
                $scope.notification_data = res_data;
            }
        });
    }
	
	$scope.initialState = function() {
		/*var tomorrow = new Date();
		tomorrow.setDate(tomorrow.getDate() + 1);
        $scope.checkin_date = new Date();
        $scope.checkout_date = tomorrow;*/
        // HTL-654 fix ----------
        if(angular.isUndefined($cookieStore.get('def_date_range'))){
            var tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            $scope.checkin_date = new Date();
            $scope.checkout_date = tomorrow;
        }
        else{
            var def_date_range = $cookieStore.get('def_date_range');
            $scope.checkin_date = new Date(def_date_range.notif_date_range.date_from);
            $scope.checkout_date = new Date(def_date_range.notif_date_range.date_to);
        }
        // HTL-654 fix ----------
		$scope.notification_type = {notification_type_id:''};
		$scope.filter_notification();

	};
	

    $scope.get_notification_type = function() {
        $http.get('rest/api/v1/notification_type').then(function(res) {
            if(res.data.status == "success") {
                $scope.notification_types = res.data.data;
                $scope.initialState();
            }
        });
    }
    $scope.get_notification_type();


    $scope.filter_notification = function(){
    	var notification_type_id = $scope.notification_type ? $scope.notification_type.notification_type_id : '';
    	$scope.get_notification(formatDate($scope.checkin_date), formatDate($scope.checkout_date), notification_type_id);
        // HTL-654 fix ----------
        if(!angular.isUndefined($cookieStore.get('def_date_range'))){
            var def_date_range = $cookieStore.get('def_date_range');
            var notif_date_range = {
                                date_from: $scope.checkin_date,
                                date_to: $scope.checkout_date
                        };
            var rmoptim_date_range = {
                                date_from: def_date_range.rmoptim_date_range.date_from,
                                date_to: def_date_range.rmoptim_date_range.date_to
                        };
            var new_val = {
                                notif_date_range: notif_date_range,
                                rmoptim_date_range: rmoptim_date_range
                        };
            $cookieStore.put('def_date_range', new_val);
        }
        // HTL-654 fix ----------
    	//console.log($scope.notification_type.notification_type_id);
    }

    $scope.go_to_reservation = function(link){
    	if( link != '' ) $window.location.assign(link);
    }

	$scope.open1 = function($event, opened) {
		$event.preventDefault();
		$event.stopPropagation();

		$scope[opened] = true;
	};
	
	$scope.inlineOptions = {
		minDate: new Date(),
		showWeeks: true
	};

	$scope.dateOptionsCI = {
		formatYear: 'yyyy',
		startingDay: 1
	};


	$scope.$watch('checkin_date', function(i){
		if($scope.checkin_date > $scope.checkout_date){
			var tomorrow = new Date($scope.checkin_date);
			tomorrow.setDate(tomorrow.getDate() + 1);
			$scope.checkout_date = tomorrow;
		}
	});
	
	$scope.$watch('checkout_date', function(i){
		var previousDay = new Date($scope.checkout_date);
		previousDay.setDate(previousDay.getDate() - 1);
		if(previousDay < $scope.checkin_date){
			$scope.checkin_date = previousDay;
		}
	});	

	function formatDate(date) {
        var d = new Date(date),
            month = '' + (d.getMonth() + 1),
            day = '' + d.getDate(),
            year = d.getFullYear();

        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;

        return [year, month, day].join('-');
    }

    //$scope.msgToTranslate = $translate("Notif_loggedin", { username: "Gwion Support" });.
    //$scope.dataToTranslate = { username: "Gwion Support", extra: "asdasdasd " };
    


});