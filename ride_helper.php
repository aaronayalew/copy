<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


	/* Saving ride details for future stats */
	
	if (!function_exists('save_ride_details_for_stats')){
		function save_ride_details_for_stats($ride_id,$ridArr= array()) {
			$ci =& get_instance();
			$checkRide = $ci->app_model->get_selected_fields(RIDES, array('ride_id' => $ride_id),array('booking_information','user','location'));
			#echo "<pre>"; print_r($checkRide->row()); die;
			if($checkRide->num_rows() > 0 ){
				$dataArr = array(
									'user_id' => $checkRide->row()->user['id'],
									'location_id' => $checkRide->row()->location['id'],
									'location'=>$checkRide->row()->booking_information['pickup']['latlong'],'pickup_address'=> trim(
															preg_replace( "/\r|\n/", "", $checkRide->row()->booking_information['pickup']['location'] )
															),
									'category' => $checkRide->row()->booking_information['service_id'],
									'ride_time' => $checkRide->row()->booking_information['est_pickup_date']
								);
				$ci->app_model->simple_insert(RIDE_STATISTICS,$dataArr);
			}
		}
	}	
	/* Saving ride details for future stats */
	
	if ( ! function_exists('check_surge')){
		function check_surge($location_id,$category) {
			$change_txt = "";
			$ci =& get_instance();
			$checkLoc = $ci->app_model->get_all_details(LOCATIONS, array('_id' => MongoID($location_id)));
			if($checkLoc->num_rows() > 0 ){
				$surgeX = 0;
				$night_charge = 0;
				
				$night_charge_val = 0;
				$peak_charge_val = 0;
				
				if(isset($checkLoc->row()->fare[$category]['night_charge'])){
					$night_charge = $checkLoc->row()->fare[$category]['night_charge'];
					$surgeX = $checkLoc->row()->fare[$category]['night_charge'];
				}
				
				$peak_time_charge = 0;
				if(isset($checkLoc->row()->fare[$category]['peak_time_charge'])){
					$peak_time_charge = $checkLoc->row()->fare[$category]['peak_time_charge'];
					$surgeX = $checkLoc->row()->fare[$category]['peak_time_charge'];
				}
				
				if($peak_time_charge>0 && $night_charge>0){
					$surgeX = $peak_time_charge+$night_charge;
				}
				
				
				$pickup_datetime = time();
				$pickup_date = date('Y-m-d');
				if ($checkLoc->row()->night_charge == 'Yes') {
					$time1 = strtotime($pickup_date . ' ' . $checkLoc->row()->night_time_frame['from']);
					$time2 = strtotime($pickup_date . ' ' . $checkLoc->row()->night_time_frame['to']);
					$nc = FALSE;
					if ($time1 > $time2) {
						if (date('a', $pickup_datetime) == 'PM') {
							if (($time1 <= $pickup_datetime) && (strtotime('+1 day', $time2) >= $pickup_datetime)) {
								$nc = TRUE;
							}
						} else {
							if ((strtotime('-1 day', $time1) <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
								$nc = TRUE;
							}
						}
					} else if ($time1 < $time2) {
						if (($time1 <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
							$nc = TRUE;
						}
					}
					if ($nc) {
						if($night_charge>0){
							$change_txt = $ci->format_string('Night time charges', 'night_time_charge').' '.$night_charge.'X '. $ci->format_string('will be applied', 'will_be_applied');
							$night_charge_val = $night_charge;
						}
					}
				}
				if ($checkLoc->row()->peak_time == 'Yes') {
					$time1 = strtotime($pickup_date . ' ' . $checkLoc->row()->peak_time_frame['from']);
					$time2 = strtotime($pickup_date . ' ' . $checkLoc->row()->peak_time_frame['to']);
					$ptc = FALSE;
					if ($time1 > $time2) {
						if (date('a', $pickup_datetime) == 'PM') {
							if (($time1 <= $pickup_datetime) && (strtotime('+1 day', $time2) >= $pickup_datetime)) {
								$ptc = TRUE;
							}
						} else {
							if ((strtotime('-1 day', $time1) <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
								$ptc = TRUE;
							}
						}
					} else if ($time1 < $time2) {
						if (($time1 <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
							$ptc = TRUE;
						}
					}
					if ($ptc) {
						if($peak_time_charge>0){
							$change_txt = $ci->format_string('Peak time charges', 'peak_time_charges').' '.$peak_time_charge.'X '. $ci->format_string('will be applied', 'will_be_applied');
							$peak_charge_val = $peak_time_charge;
						}
					}
				}
				
				if($night_charge_val>0 && $peak_charge_val>0){					
					$change_txt = $ci->format_string('Peak and Night time charges', 'peak_and_night_time_charges').' '.($night_charge+$peak_time_charge).'X '. $ci->format_string('will be applied', 'will_be_applied');
				}
				
				
			}
			return $change_txt;
		}
	}
	
	
	/**
	*
	*	This function make a booking process for a user
	*	Param @bookingInfo as Array
	*	Holds all the booking information
	*
	**/	
	if ( ! function_exists('book_a_ride')){ 
		function book_a_ride($bookingInfo) {
			#echo "<pre>"; print_r($bookingInfo); die;
			$ci =& get_instance();			
			$returnArr['status'] = '0';
			$returnArr['response'] = '';
			$acceptance = 'No';
			
			try {
				if(array_key_exists("user_id",$bookingInfo)) $user_id =  trim($bookingInfo['user_id']); else $user_id = "";
				if(array_key_exists("pickup",$bookingInfo)) $pickup =  trim($bookingInfo['pickup']); else $pickup = "";
				if(array_key_exists("pickup_lat",$bookingInfo)) $pickup_lat =  trim($bookingInfo['pickup_lat']); else $pickup_lat = "";
				if(array_key_exists("pickup_lon",$bookingInfo)) $pickup_lon =  trim($bookingInfo['pickup_lon']); else $pickup_lon = "";
				if(array_key_exists("category",$bookingInfo)) $category =  trim($bookingInfo['category']); else $category = "";
				if(array_key_exists("type",$bookingInfo)) $type =  trim($bookingInfo['type']); else $type = "";
				if(array_key_exists("pickup_date",$bookingInfo)) $pickup_date =  trim($bookingInfo['pickup_date']); else $pickup_date = "";
				if(array_key_exists("pickup_time",$bookingInfo)) $pickup_time =  trim($bookingInfo['pickup_time']); else $pickup_time = "";
				if(array_key_exists("code",$bookingInfo)) $code =  trim($bookingInfo['code']); else $code = "";
				if(array_key_exists("try",$bookingInfo)) $try =  trim($bookingInfo['try']); else $try = 1;
				if(array_key_exists("ride_id",$bookingInfo)) $ride_id =  trim($bookingInfo['ride_id']); else $ride_id = "";
				
				if(array_key_exists("platform",$bookingInfo)) $platform =  trim($bookingInfo['platform']); else $platform = "unknown";
				
				if(array_key_exists("drop_loc",$bookingInfo)) $drop_loc =  trim($bookingInfo['drop_loc']); else $drop_loc = "";
				if(array_key_exists("drop_lat",$bookingInfo)) $drop_lat =  trim($bookingInfo['drop_lat']); else $drop_lat = "";
				if(array_key_exists("drop_lon",$bookingInfo)) $drop_lon =  trim($bookingInfo['drop_lon']); else $drop_lon = "";
				if(array_key_exists("estimate_id",$bookingInfo)) $estimate_id =  trim($bookingInfo['estimate_id']); else $estimate_id = "";
				if(array_key_exists("addon_id",$bookingInfo)) $addon_id =  trim($bookingInfo['addon_id']); else $addon_id = "";
				if(array_key_exists("booking_notes",$bookingInfo)) $booking_notes =  trim($bookingInfo['booking_notes']); else $booking_notes = "";				
				if(array_key_exists("drop_title",$bookingInfo)) $drop_title =  trim($bookingInfo['drop_title']); else $drop_title = ""; 
				#echo '<pre>';print_r($bookingInfo);die;
				/* pets/assits/wheelchair */
				
				if($drop_loc==''){
					$drop_lat = 0;
					$drop_lon = 0;
				}
				
				if(array_key_exists("booking_source",$bookingInfo)) $booking_source =  trim($bookingInfo['booking_source']); else $booking_source = "";
				if(array_key_exists("booked_by",$bookingInfo)) $booked_by =  MongoID($bookingInfo['booked_by']); else $booked_by = "";
				
				$riderlocArr = array('lat' => (string) $pickup_lat, 'lon' => (string) $pickup_lon);

				if(array_key_exists("share",$bookingInfo)) $share =  trim($bookingInfo['share']); else $share = ""; #	(Yes/No)
				if(array_key_exists("no_of_seat",$bookingInfo)) $no_of_seat=intval($bookingInfo['no_of_seat']); else $no_of_seat=0; #	(1/2)
                if(array_key_exists("payment_method",$bookingInfo)) $payment_method= trim($bookingInfo['payment_method']); else $payment_method="";    # card_id/wallet/paypal/cash
				
				if(array_key_exists("gift_ride",$bookingInfo)) $gift_ride = trim($bookingInfo['gift_ride']); else $gift_ride=""; #(Yes/No);
				if(array_key_exists("rec_dail_code",$bookingInfo)) $rec_dail_code = trim($bookingInfo['rec_dail_code']); else $rec_dail_code=""; #(Yes/No);
				if(array_key_exists("rec_phone_number",$bookingInfo)) $rec_phone_number = trim($bookingInfo['rec_phone_number']); else $rec_phone_number=""; #(Yes/No);
				if(array_key_exists("dispatcher_info",$bookingInfo)) $dispatcher_info = $bookingInfo['dispatcher_info']; else $dispatcher_info= array();
				
				if($share == 'Yes' && ($no_of_seat == '' || $no_of_seat == 0)) $no_of_seat = 1;
				
				/*-------------For Outstation Area--------------*/
				if(array_key_exists("ride_type",$bookingInfo) && $bookingInfo['ride_type']!='') $trip_type =  $bookingInfo['ride_type']; else $trip_type = "normal";
				if(array_key_exists("outstation_id",$bookingInfo)) $outstation_id =  $bookingInfo['outstation_id']; else $outstation_id = "";
				if(array_key_exists("return_date",$bookingInfo)) $return_date =  $bookingInfo['return_date']; else $return_date = "";
				if(array_key_exists("return_time",$bookingInfo)) $return_time =  $bookingInfo['return_time']; else $return_time = "";
                
                
				/*-------------For Outstation Area--------------*/
				
				/*-------------For Airport Area--------------*/
                
                if(array_key_exists("airport_id",$bookingInfo)) $airport_id =  trim($bookingInfo['airport_id']); else $airport_id = "";
				if(array_key_exists("terminal_id",$bookingInfo)) $terminal_id =  trim($bookingInfo['terminal_id']); else $terminal_id = "";
				if(array_key_exists("parking_id",$bookingInfo)) $parking_id =  trim($bookingInfo['parking_id']); else $parking_id = "";
				 
				$isAirpotPic = TRUE;
				$isAirpotDrop = TRUE;
				if($airport_id!='' && $terminal_id!=''){
					$isAirpotDrop = TRUE;
				}
				if($airport_id!='' && $terminal_id!='' && $parking_id!=''){
					$isAirpotPic = TRUE;
					$get_airport = $ci->app_model->get_selected_fields(AIRPORT, array('status'=>'Active','_id' =>MongoID($airport_id)));
					$get_terminal = $ci->app_model->get_selected_fields(TERMINAL, array('status'=>'Active','_id' =>MongoID($terminal_id)));
					$get_Parking = $ci->app_model->get_selected_fields(PARKING, array('status'=>'Active','_id' =>MongoID($parking_id)));
                    
					if($get_airport->num_rows() == 0 || $get_terminal->num_rows() == 0 || $get_Parking->num_rows() == 0 ){
						$isAirpotPic = FALSE;
					}
				}				
				
				/*-------------For Airport Area--------------*/
				if($share=="") $share = "No";
				if($share=="Yes"){
					$type = 0;
				}
				
				if ($try > 1) {
					$limit = 10 * $try;
				} else {
					$limit = 10;
				}
				$local_pickup_time = '';
					
				if($trip_type == 'outstation') $type = 1;
				 
				if ($type == 1) {  
					$ride_type = 'Later';
					$pickup_datetime = $pickup_date . ' ' . $pickup_time;
					$pickup_timestamp = strtotime($pickup_datetime);
					$local_pickup_time = $pickup_datetime;
					/*** time zone conversion ***/ 
					if($pickup_lon != '' && $pickup_lat != ''){
						$coordinates = array(floatval($pickup_lon), floatval($pickup_lat));
						$location = $ci->app_model->find_location(floatval($pickup_lon), floatval($pickup_lat),"Yes");
						if(isset($location['result'][0]['time_zone']) && $location['result'][0]['time_zone'] != ''){
							$c_time_zone = $location['result'][0]['time_zone'];
							if($c_time_zone != ''){
								$getNewTime = $ci->timeZoneConvert($pickup_datetime, $c_time_zone);
								$pickup_timestamp = strtotime($getNewTime);
							}
						}
					}
					/***********************/
				} else {
					$ride_type = 'Now';
					$pickup_timestamp = time();
				}
				
                if(array_key_exists("stops",$bookingInfo)) $stops =  $bookingInfo['stops']; else $stops = array();
				
				$after_one_hour = strtotime('+15 mins', time());
				#echo $after_one_hour; die;
				
               if($payment_method!='') {
				if( $type == 0 || ($type ==1 && ($pickup_timestamp >= $after_one_hour)) ){	
				 			
					$acceptance = 'No';
					if ($acceptance == 'No') {
						
						$outstation_type = '';   
						if($trip_type == 'outstation'){
							if($return_date != '' && $return_time != ''){
								$outstation_type = 'round';
							} else {
								$outstation_type = 'oneway';
							}
						} 
						
						if ($user_id!="" && $pickup!="" && $pickup_lat!="" && $pickup_lon!="" && $category!="" && ($trip_type == 'normal' || $trip_type == 'hailing' || ($trip_type == 'outstation' && $outstation_type != ''))) {
						
							$uCond = array('_id' => MongoID($user_id));
							$checkUser = $ci->app_model->get_selected_fields(USERS, $uCond, array('email', 'user_name', 'country_code', 'phone_number', 'push_type','wallet_amount','stripe_customer_id','reward_id','reward_amount','time_zone','brain_profile_id','status'));
							if ($checkUser->num_rows() == 1 && $checkUser->row()->status == 'Active') {
								/********  Check and Expire Old Just Booked Jobs *******/
								$getCond = array('ride_status' => 'Booked','type' => 'Now','ride_type' => array('$ne' => 'gift'),'user.id' => $user_id);
								$checkJustBooked = $ci->app_model->get_selected_fields(RIDES,$getCond,array('ride_id', 'type', 'ride_status', 'user','authorization_info'));
								if($checkJustBooked->num_rows() > 0){
										
									$ci->load->helper('payment_helper');
									foreach ($checkJustBooked->result() as $jbRides) { 
										$rid = $jbRides->ride_id;
										if(isset($rides->authorization_info)&& !empty($jbRides->authorization_info)){
											$amount_captured = $jbRides->authorization_info['amount_captured'];
											$charge_id = $jbRides->authorization_info['charge_id'];
											refund_authorized_ride_amount($charge_id);
										}
										/* Saving Unaccepted Ride for future reference */
										save_ride_details_for_stats($rid);
										/* Saving Unaccepted Ride for future reference */
										#$email = $jbRides->user['email']; 
										$user_id =$jbRides->user['id'];
										/* Update the ride information */
										$rideDetails = array('ride_status' => 'Expired', 'booking_information.expired_date' => MongoDATE(time()));
										$ci->app_model->update_details(RIDES, $rideDetails, array('ride_id' => $rid));
										#$ci->mail_to_user($rid,$email,$user_id);
									}
								}
								/*************************************************/
								
								$chkCond = array('has_pending_cancellation_fee' => 'Yes',
																 '$or' => array(array('user.id' => array('$eq'=> $user_id),'gift_ride' => array('$exists' =>false)),
																						array('gifted_user.id' => array('$eq'=> $user_id),'gift_ride' => array('$eq' => 'Yes'))
																				)
													);
								$chkFeeRide = $ci->app_model->get_selected_fields(RIDES,$chkCond,array('_id'));
								if($chkFeeRide->num_rows() == 0){
									
									$allowBooking = TRUE;
									$giftErr = 'No';
									if($user_id != ''){
										$ongoingRides = $ci->app_model->get_ongoing_rides_count($user_id,'',$type);
										if($ongoingRides > 0) $allowBooking = FALSE;
										if($allowBooking && $gift_ride == 'Yes'){
											$ongoingGiftRides = $ci->app_model->get_ongoing_rides_count($user_id,'gift',$type);
											
											if($ongoingGiftRides > 1){
												$giftErr == 'Yes';
												$allowBooking = FALSE;
											}
										}
									}
									
									if($allowBooking && $gift_ride == 'Yes' && $rec_dail_code != '' && $rec_phone_number != ''){
										
										if($payment_method == 'cash'){
											$giftErr = 'Yes';
											$allowBooking = FALSE;
										} else {
											 $condition = array('phone_number' => (string)$rec_phone_number,'country_code' => (string)$rec_dail_code);
											 $giftReceiver = $ci->app_model->get_selected_fields(USERS, $condition, array('_id'));
											 if($giftReceiver->num_rows() > 0){
												$ongoingRides = $ci->app_model->get_ongoing_rides_count((string)$giftReceiver->row()->_id,'',$type);
												if($ongoingRides > 0) {
													$giftErr = 'Yes';
													$allowBooking = FALSE;
												}
											 }
										}
									}
						
									if($allowBooking){ 
										$coordinates = array(floatval($pickup_lon), floatval($pickup_lat));
										if(!isset($location['result'])){
											$location = $ci->app_model->find_location(floatval($pickup_lon), floatval($pickup_lat),"Yes");
										}
										$time_zone = date_default_timezone_get();
										if(isset($location['result'][0]['time_zone']) && $location['result'][0]['time_zone'] != ''){
											$time_zone = $location['result'][0]['time_zone'];
										}
										if(!isset($checkUser->row()->time_zone) || (isset($checkUser->row()->time_zone) && $checkUser->row()->time_zone == '')){
											$timezData = array('time_zone' => $time_zone);
											$ci->app_model->update_details(USERS,$timezData,$uCond);
										}
										/* Surge Multiplier */
										$surge_status ='No';
										$surge_value = 1;
										
										/* Surge Multiplier */
										
										$droploc_Check = TRUE;
										if($drop_loc!=''){ 
											$droploc_Check = FALSE;
											$droploc = $ci->app_model->find_location(floatval($drop_lon), floatval($drop_lat),"Yes");
											if (!empty($droploc['result'])) {
                                                if($trip_type == "outstation"){
                                                        $droploc_Check = TRUE;
                                                } else {
											
                                                    if(isset($location['result'][0]['_id']) && isset($droploc['result'][0]['_id']) && (string)$location['result'][0]['_id'] == (string)$droploc['result'][0]['_id']){
                                                        $droploc_Check = TRUE;
                                                    }
                                                }
											}
										}

										if ($outstation_type != '' || (!empty($location['result']) && $droploc_Check == TRUE)) {
											if($share=="Yes"){
												$has_pool_service = 0;
												$pooling = 0;
												if($ci->data['share_pooling_status'] != ''){
													$pooling = $ci->data['share_pooling_status'];
												}								
												if($pooling==1){
													if(array_key_exists("share_pooling",$location['result'][0])){
														$share_pooling = $location['result'][0]['share_pooling'];
														if($share_pooling=="Enable"){
															$pool_categories = $location['result'][0]['pool_categories'];
															if(!empty($pool_categories)){
																$has_pool_service = 1;
															}										
														}
													}
												}
											}
											
											$loc_category = $location['result'][0]['fare'];
											if (($share=="Yes" && $has_pool_service == 1) || (array_key_exists($category,$loc_category))){
												$location_id = $location['result'][0]['_id'];
												
												$serviceArr = array();
												if($share=="Yes"){
													$serviceArr = array("service_type"=>(string)$ci->config->item('pooling_name'), "service_id"=>(string)POOL_ID);
												}else{
													$categoryResult = $ci->app_model->get_selected_fields(CATEGORY, array('_id' => MongoID($category)), array('name'));
													if($categoryResult->num_rows() > 0){
														$serviceArr = array("service_type"=>(string)$categoryResult->row()->name, "service_id"=>(string)$categoryResult->row()->_id);
													}
												}
												
												if(!empty($serviceArr) && ($share=="No" || ($share=="Yes" && $no_of_seat > 0))){
													
													$requested_drivers = array();
													if($category == POOL_ID){
														$categoryID = array();
														foreach($location['result'][0]['pool_categories'] as $pool_cat){
															$categoryID[] = MongoID($pool_cat);
														}
													}else{
														$categoryID = $category;
													} 
													
													$picDropLoc = array('pickup' => array('lon' => floatval($pickup_lon),'lat' => floatval($pickup_lat)), 'drop' => array('lon' => floatval($drop_lon),'lat' => floatval($drop_lat)));
													if($category == POOL_ID){
														$category_drivers = $ci->app_model->get_nearest_pool_driver($coordinates, $categoryID, $limit,'',$requested_drivers,"",$location_id,$payment_method,$picDropLoc,$type);
														#echo '<pre>'; print_r($category_drivers); die;
													}
													
													//echo'<pre>';print_r($coordinates);die;
													if($gift_ride !='Yes'){
															if (empty($category_drivers['result'])) {
																$category_drivers = $ci->app_model->get_nearest_driver($coordinates, $categoryID, $limit,'',$requested_drivers,"",$location_id,"",$addon_id,$airport_id,$picDropLoc,$type,'','','',$payment_method);
																#echo "<pre>";print_r($category_drivers);die;
																if (empty($category_drivers['result'])) {
																	$category_drivers = $ci->app_model->get_nearest_driver($coordinates, $categoryID, $limit * 2,'',$requested_drivers,"",$location_id,"",$addon_id,$airport_id,$picDropLoc,$type,'','','',$payment_method);
																}
															}
													}
													
													if(!empty($category_drivers['result']) || $type == 1 || $gift_ride =='Yes'){
														$android_driver = array();
														$apple_driver = array();
														$push_and_driver = array();
														$push_ios_driver = array();
														if($gift_ride !='Yes'){
															#echo "<pre>";print_r($category_drivers['result']);
															foreach ($category_drivers['result'] as $driver) {
																if (isset($driver['push_notification'])) {
																#$d_id=MongoID((string)$category_drivers['result'][0]['_id']);
																	$d_id=(string)$driver['_id'];
																	array_push($requested_drivers,$d_id); 
																	
																	if ($driver['push_notification']['type'] == 'ANDROID') {
																		if (isset($driver['push_notification']['key'])) {
																			if ($driver['push_notification']['key'] != '') {
																				$android_driver[] = $driver['push_notification']['key'];
																				$k = $driver['push_notification']['key'];
																				$push_and_driver[$k] = array('id' => $driver['_id'],'loc' =>  $driver['loc'],'mode' => $driver['mode'], 'ride_type' => $driver['ride_type'], 'messaging_status' => $driver['messaging_status']);
																			}
																		}
																	}
																	if ($driver['push_notification']['type'] == 'IOS') {
																		if (isset($driver['push_notification']['key'])) {
																			if ($driver['push_notification']['key'] != '') {
																				$apple_driver[] = $driver['push_notification']['key'];
																				$k = $driver['push_notification']['key'];
																				 $push_ios_driver[$k] = array('id' => $driver['_id'],'loc' =>  $driver['loc'],'mode' => $driver['mode'], 'ride_type' => $driver['ride_type'], 'messaging_status' => $driver['messaging_status']);
																			}
																		}
																	}
																}
															}
														}
															#echo "<pre>";print_r($push_ios_driver);die;
															$checkCode = $ci->app_model->get_all_details(PROMOCODE, array('promo_code' => $code,'location'=>(string)$location_id,'category'=>(string)$serviceArr["service_id"]));
															$code_used = 'No';
															$coupon_type = '';
															$coupon_amount = '';
															$maximum_usage=0;
															if ($checkCode->num_rows() > 0) {
																$code_used = 'Yes';
																$coupon_type = $checkCode->row()->code_type;
																$coupon_amount = $checkCode->row()->promo_value;
																if($coupon_type == 'Percent'){
																	$maximum_usage=$checkCode->row()->maximum_usage;
																}
															}
															$site_commission = 0;
															if (isset($location['result'][0]['site_commission'])) {
																if ($location['result'][0]['site_commission'] > 0) {
																	$site_commission = $location['result'][0]['site_commission'];
																}
															}
															
															$booking_fee = 0;
															if(isset($location['result'][0]['booking_fee'])){
																$booking_fee = $location['result'][0]['booking_fee'];
															}
															
															#$currencyCode=$location['result'][0]['currency'];
															$currencyCode = $ci->data['dcurrencyCode'];
															
															$distance_unit = $ci->data['d_distance_unit'];
															if(isset($location['result'][0]['distance_unit'])){
																$distance_unit = $location['result'][0]['distance_unit'];
															}
										
															$ci->load->helper('pool_helper'); 
															$estimate_amount = get_estimate($bookingInfo,'','appbooking');
															if(isset($bookingInfo['ride_type']) && $bookingInfo['ride_type'] == 'outstation'){
																$ci->load->helper('outstation_helper'); 
																$estimate_amount = check_outstation_fare('booking','','','',$bookingInfo);
															}
															#echo "<pre>"; print_r($bookingInfo); die;
															$est_travel_duration = 0; $est_travel_distance = 0;
															if(isset($estimate_amount['response']['travel_duration']) && $estimate_amount['response']['travel_duration'] != ''){
																$est_travel_duration = $estimate_amount['response']['travel_duration'];
																$est_travel_distance = $estimate_amount['response']['travel_distance'];
															}  
															$min_amount = '';
															/* echo $no_of_seat;die; */
															if($category == POOL_ID){
																if(isset($estimate_amount['status']) && $estimate_amount['status'] == '1' &&  $share=='Yes'){
																		$min_amount = $estimate_amount['response']['pool_eta']['est_amount']; 
																		if($no_of_seat==2){ 
																			$min_amount = $estimate_amount['response']['pool_ratecard'][1]['cost']; 
																		}
																	}	
															}else{ 
																if(isset($estimate_amount['status']) && $estimate_amount['status'] == '1'){
																	if(isset($estimate_amount['response']['eta']['est_amount']) && $estimate_amount['response']['eta']['est_amount'] !=''){ 
																	
																		$min_amount = $estimate_amount['response']['eta']['est_amount']; 
																	} else if(isset($estimate_amount['response']['oneway_fare']['category_fare'][0]['eta_amount'])){
																		
																		$min_amount = $estimate_amount['response']['oneway_fare']['category_fare'][0]['eta_amount'];
																		
																	} else if(isset($estimate_amount['response']['roundtrip_fare']['category_fare'][0]['eta_amount'])) { 
																		$min_amount = $estimate_amount['response']['roundtrip_fare']['category_fare'][0]['eta_amount'];
																		
																	}
																}
															}
															$ride_id = $ci->app_model->get_ride_id();
															$wallet_amount=0;
															if(isset($checkUser->row()->wallet_amount)) 
															$wallet_amount=$checkUser->row()->wallet_amount;
															if($site_commission > 100) $site_commission = 100;
															$isAuth=FALSE;
															$authorization_info=array();
															
															$isCard = 'No';
															$cardChk = @explode('_',$payment_method);
															if(isset($cardChk[0]) && $cardChk[0] == 'card'){
																$isCard = 'Yes';
															}
															$error_message=$ci->format_string("Payment method currently not available","payment_method_unavailable");
															if($payment_method=='cash' && $ci->config->item('pay_by_cash') == 'Enable') {
																	$isAuth=TRUE;
															} else if($payment_method=='wallet' && $ci->config->item('use_wallet_amount') == 'Enable') {
																if($min_amount <= $wallet_amount) {
																	$isAuth=TRUE;
																} else {
																	$error_message=$ci->format_string("Wallet amount is low", "wallet_amount_low");
																}
															}else if($payment_method == 'paypal' && $ci->data['bt_gateway_enabled'] == 'Yes') {
																if(isset($checkUser->row()->brain_profile_id) && $checkUser->row()->brain_profile_id != ''){
																	$isAuth=TRUE;
																} else {
																	$error_message=$ci->format_string("Paypal account is not linked");
																}
															} else if($isCard == 'Yes' && $ci->data['stripe_gateway_enabled'] == 'Yes'){
																 $ci->load->helper('payment_helper'); 
																 $card_id=$payment_method; 
																 $payment_method = 'card';
																$authorized = authorize_ride_amount($min_amount,$card_id,$ride_id,$user_id);
																if(isset($authorized['status']) && $authorized['status'] == '1'){
																	$isAuth = TRUE;
																	$authorize_status = 'Yes';
																	$authorization_info = array('amount_captured' => $authorized['response']['amount'],'charge_id' => $authorized['response']['txn_charge_id'],'payment_status' => 'authorized','card_id' => (string)$card_id,'paid_card_info' => $authorized['response']['paid_card_info'],);
																} else {
																	$error_message = $authorized['response'];
																}   
															} else if($payment_method == ''){
																$error_message = $ci->format_string('Payment authorization failed','payment_auth_failed');
															}			
															
															$maximum_split_cost_count = 0;
															if($ci->data['maximum_split_cost_count'] !=''){
																$maximum_split_cost_count = $ci->data['maximum_split_cost_count'];
															}
														
															$mileage_reward = array('mileage_distance'=>$ci->config->item('mileage_distance'),'mileage_point'=>$ci->config->item('mileage_point'));  	
															#var_dump($requested_drivers); die;					
														if($isAuth== true){
															$requested_drivers_final = array_unique($requested_drivers);
															$search_algorithm = $ci->data['search_algorithm'];
															#echo '<pre>'; print_r($requested_drivers_final); die;
															
															$bookingRecord = array(
																	'ride_id' => (string) $ride_id,
																	'type' => $ride_type,
																	'time_zone' => $time_zone,
																	'booking_ref' =>$platform,
																	'currency' => $currencyCode,
																	'airport_id'=>(string)$airport_id,
																	'terminal_id'=>(string)$terminal_id,
																	'parking_id'=>(string)$parking_id,
																	'payment_method' => $payment_method,
																	'booking_notes' => $booking_notes,
																	'authorization_info' =>$authorization_info,
																	'commission_percent' => $site_commission,
																	'mileage_reward'=>$mileage_reward,
																	'addon_id'=>(string)$addon_id,
																	'location' => array('id' => (string) $location_id,
																		'name' => $location['result'][0]['city']
																	),
																	'user' => array('id' => (string) $checkUser->row()->_id,
																		'name' => $checkUser->row()->user_name,
																		#'email' => $checkUser->row()->email,
																		'phone' => $checkUser->row()->country_code . $checkUser->row()->phone_number
																	),
																	'driver' => array('id' => '',
																	'name' => '',
																	'email' => '',
																	'phone' => ''
																	),
																	'total' => array('fare' => '',
																	'distance' => '',
																	'ride_time' => '',
																	'wait_time' => ''
																	),
																	'fare_breakup' => array('min_km' => '',
																	'min_time' => '',
																	'min_fare' => '',
																	'per_km' => '',
																	'per_minute' => '',
																	'wait_per_minute' => '',
																	'peak_time_charge' => '',
																	'night_charge' => '',
																	'distance_unit' => $distance_unit,
																	'duration_unit' => 'min',
																	),
																	'tax_breakup' => array('service_tax' => ''),
																	'booking_information' => array(
																		'service_type' => $serviceArr["service_type"],
																		'service_id' => (string) $serviceArr["service_id"],
																		'booking_date' => MongoDATE(time()),
																		'pickup_date' => '',
																		'actual_pickup_date' => MongoDATE($pickup_timestamp),
																		'est_pickup_date' => MongoDATE($pickup_timestamp),
																		#'booking_email' => $checkUser->row()->email,
																		'pickup' => array('location' => $pickup,
																					'latlong' => array('lon' => floatval($pickup_lon),
																					  'lat'=> floatval($pickup_lat)
																											)
																				),
																		'drop' => array('location' => (string)$drop_loc,				'org_location' => (string)$drop_loc,				'title' => (string)$drop_title,
																						'latlong' => array('lon' => floatval($drop_lon),
																						 'lat' =>floatval($drop_lat))
																						),
																		'est_amount' => (string) $min_amount,
																		'est_travel_duration' =>  floatval($est_travel_duration),
																		'est_travel_distance' =>  floatval($est_travel_distance)
																	),
																	'surge_status'=>$surge_status,
																	'surge_value'=>$surge_value,
																	'ride_status' => 'Booked',
																	'coupon_used' => $code_used,
																	'coupon' => array('code' => $code,
																								'type' => $coupon_type,
																								'amount' => floatval($coupon_amount),
																								'maximum_usage'=>$maximum_usage,
																						),
																	'requested_drivers'=>$requested_drivers_final,
																	'booking_source'=>$booking_source,
																	'booked_by'=>$booked_by,
																	'maximum_split_cost_count'=>(string)$maximum_split_cost_count,
																	'search_algorithm'=>(string)$search_algorithm
															);
														$ride_est_amt = $min_amount;
														
														if(!empty($dispatcher_info)){
															$bookingRecord['dispatcher'] = $dispatcher_info;
														}
														
														if(isset($checkUser->row()->reward_id) && $checkUser->row()->reward_id!='' && isset($checkUser->row()->reward_amount) && $checkUser->row()->reward_amount > 0){
															$bookingRecord['reward_id'] = $checkUser->row()->reward_id;
															$bookingRecord['reward_amount'] = $checkUser->row()->reward_amount;
														}
														if($gift_ride == 'Yes'){
															$bookingRecord['ride_type'] ='gift';
														}
														if($trip_type == 'hailing'){
															$bookingRecord['ride_type'] ='hailing';
														}
														if($local_pickup_time != ''){
															$bookingRecord['booking_information']['local_pickup_time'] = $local_pickup_time;
														}
													
														/************  Mutiple Drops  **********/
														$stopsArr = array();
														$stopsLocArr = array();
														$stop_code = time();
														if(is_array($stops) && !empty($stops)){
															$stops = array_filter($stops); 
															for ($i = 0; $i < count($stops); $i++) {
																if($stops[$i]['drop_loc'] !='' && $stops[$i]['drop_lon'] !='' && $stops[$i]['drop_lat']!=''){
																	$keyCode = (string)'stop_'.($stop_code+$i);
																	$stopsArr[$keyCode] = array('location'=>$stops[$i]['drop_loc'],
																	'latlong'=>array('lon'=>floatval($stops[$i]['drop_lon']),
																			'lat'=>floatval($stops[$i]['drop_lat'])),
																	'stop_code' => $keyCode
																	);
																	$stopsLocArr[] = $stops[$i]['drop_loc'];
																}
															}
															$stopsArr = array_filter($stopsArr);
															$stopsLocArr = array_filter($stopsLocArr);
														}
														if(count($stopsArr)> 0){
															$bookingRecord['booking_information']['stops'] = $stopsArr;
														}
														/**************************************/
														$pooling_response = array();
														if($share=="Yes"){
															$pool_id = $ride_id;
															
															$from = $pickup_lat . ',' . $pickup_lon;
															$to = $drop_lat . ',' . $drop_lon;
															
															/* $gmap = file_get_contents('https://maps.googleapis.com/maps/api/directions/json?origin=' . $from . '&destination=' . $to . '&alternatives=true&sensor=false&mode=driving'.$ci->data['google_maps_api_key']);
															$map_values = json_decode($gmap);
															$routes = $map_values->routes;
															if(!empty($routes)){
																#usort($routes, create_function('$a,$b', 'return intval($a->legs[0]->distance->value) - intval($b->legs[0]->distance->value);'));
																$pickup = (string) $routes[0]->legs[0]->start_address;
																$drop = (string) $routes[0]->legs[0]->end_address;															
																$min_distance = $routes[0]->legs[0]->distance->text;
																if (preg_match('/km/',$min_distance)){
																	$return_distance = 'km';
																}else if (preg_match('/mi/',$min_distance)){
																	$return_distance = 'mi';
																}else if (preg_match('/m/',$min_distance)){
																	$return_distance = 'm';
																} else {
																	$return_distance = 'km';
																}
																
																$mindistance = floatval(str_replace(',','',$min_distance));
																if($distance_unit!=$return_distance){
																	if($distance_unit=='km' && $return_distance=='mi'){
																		$mindistance = $mindistance * 1.60934;
																	} else if($distance_unit=='mi' && $return_distance=='km'){
																		$mindistance = $mindistance * 0.621371;
																	} else if($distance_unit=='km' && $return_distance=='m'){
																		$mindistance = $mindistance / 1000;
																	} else if($distance_unit=='mi' && $return_distance=='m'){
																		$mindistance = $mindistance * 0.00062137;
																	}
																}
																$mindistance = floatval(round($mindistance,2));
																$minduration = round(($routes[0]->legs[0]->duration->value) / 60);
															} */
															
															$mindistance = $est_travel_distance;
															$minduration = $est_travel_duration;
															
															$ci->load->helper('pool_helper');
															$poolFareResponse = get_pool_fare($mindistance,$minduration,$location_id,'',$type,$pickup_lat,$pickup_lon);
														
															if($poolFareResponse["status"]=="1"){
																$est_amount = 0;
																$tax_amount = 0;
																if($no_of_seat==1){
																	$est_amount = $poolFareResponse["passenger"];
																	$tax_amount = $poolFareResponse["single_tax"];
																}else if($no_of_seat==2){
																	$est_amount = $poolFareResponse["co_passenger"];
																	$tax_amount = $poolFareResponse["double_tax"];
																}
																/* if($est_amount > 0){																	
																	$tax_amount = ($est_amount*0.01*$poolFareResponse["tax_percent"]);
																} */
																$pool_fare = array("est"=>(string)round($est_amount,2),
																							"tax"=>(string)round($tax_amount, 2),
																							"tax_percent"=>(string)$poolFareResponse["tax_percent"],
																							"base_fare"=>(string)$poolFareResponse["base_fare"],
																							"single_percent"=>(string)$poolFareResponse["single_percent"],
																							"double_percent"=>(string)$poolFareResponse["double_percent"],
																							"passanger"=>(string)round($poolFareResponse["passenger"], 2),
																							"co_passanger"=>(string)round($poolFareResponse["co_passenger"], 2),
																							"fare_category"=>$poolFareResponse["fare_category"],
																							"booking_fee"=>$poolFareResponse["booking_fee"]
																						);
																$pooling_with = array();
																$co_rider = array();
																$pool_type = 0;
															}
																
															$poolRecord = array("pool_ride"=>(string)"Yes",
																				"pool_id"=>(string)$pool_id,
																				"no_of_seat"=>(string)$no_of_seat,
																				"pool_fare"=>$pool_fare,
																				"pooling_with"=>$pooling_with,
																				"co_rider"=>$co_rider,
																				"pool_type"=>(string)$pool_type,
																			);
															$ride_est_amt = $est_amount;
															$bookingRecord['booking_information']['est_amount'] = $est_amount;
															$bookingRecord['booking_information']['est_travel_duration'] = floatval($minduration);
															$bookingRecord['booking_information']['est_travel_distance'] = floatval($mindistance);
															
															$bookingRecord = array_merge($bookingRecord,$poolRecord);
															
															$pooling_response = array("share_ride"=>"Yes",
																					"pool_fare"=>(string)number_format($est_amount, 2,'.',''),
																					"currency"=>(string)$currencyCode
																				);
														
														}
														
														$bookingRecord['return_date'] = $return_date; 
                                                        $bookingRecord['return_time'] = $return_time; 
														
														$ci->app_model->simple_insert(RIDES, $bookingRecord);
														$last_insert_id = $ci->mongo_db->insert_id();
														
														if ($estimate_id != '') {
															$condArr = ['_id' => MongoID($estimate_id)];
															$ci->app_model->commonDelete(ESTIMATION_DETAILS,$condArr);
														}
														
														/*********  create and save travel path ******/
														create_and_save_travel_path_in_map($ride_id);
														/*************************************/
														
                                                        if($outstation_type != '' && $trip_type == 'outstation'){
                                                            $ci->load->helper('outstation_helper');
                                                            
                                                            if(isset($droploc['result']) && empty($droploc['result'])){
                                                                $droploc=array('drop_loc'=>$drop_loc,
                                                                               'drop_lat'=>$drop_lat,
                                                                               'drop_lon'=>$drop_lon,
                                                                            );
                                                            }
                                                            $plocation=array();
                                                            if(isset($location['result']) && !empty($location['result'])){
                                                                $plocation=array('pickup'=>$pickup,
                                                                               'pickup_lat'=>$pickup_lat,
                                                                               'pickup_lon'=>$pickup_lon,
                                                                            );
                                                            }
                                                            update_outstation_fare_details($user_id,$ride_id,$outstation_type,$plocation,$droploc);
                                                        }
														if ($type == 0) {
															$response_time = $ci->config->item('respond_timeout');
															
															$options = array($ride_id, $response_time, $pickup,$drop_loc,$ride_est_amt);
															$pickupArr =  array('lon' => $pickup_lon,'lat' => $pickup_lat);
															$userName = $checkUser->row()->user_name;
															
															$rideReqType = 'Normal';
															if($share == 'Yes'){
																$rideReqType = 'Share';
															}
															
															/** Ride Hailing **/
															 if($trip_type== 'hailing'){
																$returnArr['status'] = '1';
                                                                $returnArr['response'] = array('ride_Mid' => $last_insert_id, 'message' => $ci->format_string('Hailing Ride Booked Successfully', 'hailing_ride_booked_successfully'));
																return $returnArr;
                                                            }
															
															#echo "<pre>"; print_r($push_and_driver); 
															#echo "<pre>"; print_r($push_ios_driver); 
															#die;
															
															/** send request to drivers **/
															#echo "<pre>";print_r($push_ios_driver);die;
															sent_ride_request($ride_id,$push_and_driver,$push_ios_driver,$options,$pickupArr,$userName,$addon_id,$distance_unit,$rideReqType,$drop);
															
                                                            if($trip_type== 'outstation'){
                                                                $message = $ci->format_string("Your booking has been accepted, Our driver information will be notified before 30 minutes of your booking time.", "your_booking_has_been_accepted");
                                                            } else {
                                                                $message = $ci->format_string("Searching for a driver", "searching_for_a_driver");
                                                            }
														}else{
															$message = $ci->format_string("Your pre-scheduled ride has been accepted. A driver will be notified 30 minutes before your scheduled ride.","you_later_job_booked_driver_will_notify");
														}
														if (isset($response_time)) {
															if ($response_time <= 0) {
																$response_time = 10;
															}
														} else {
															$response_time = 10;
														}
														if (empty($riderlocArr)) {
															$riderlocArr = json_decode("{}");
														}
														
														$final_response_time = ($response_time*3) +10;
														$retry_time = $response_time + 4;
														$returnArr['status'] = '1';
														
														
														
														if($gift_ride =='Yes' ){
																/* send gift ride */
																	$fields = array(
																			'user_id' => $user_id,
																			'ride_id' => (string)$ride_id,
																			'rec_dail_code'=>(string)$rec_dail_code,
																			'rec_phone_number'=>(string)$rec_phone_number,
																			'response_time' => (string) $final_response_time,
																			'retry_time' => (string)$retry_time,
																			'gift_ride'=>(string)$gift_ride
																		);
																	$url = base_url().'send-gift-ride';
																	$ci->load->library('curl');
																	$output = $ci->curl->simple_post($url, $fields);
																/* send gift ride */
															
																$gift_message = $ci->format_string("Gift ride has been booked", "gift_ride_booked");
																$message ='';
																$returnArr['response'] = array('type' => (string) $type,
																									'gift_ride'=>'Yes',
																									'ride_id' => (string) $ride_id, 
																									'message' => $gift_message, 
																									'rider_location' => $riderlocArr
																								);
															}else{
																$returnArr['response'] = array('type' => (string) $type, 
																										'response_time' => (string) $final_response_time,
																										'retry_time' => (string)$retry_time,
																										'ride_id' => (string) $ride_id, 
																										'message' => $message, 
																										'rider_location' => $riderlocArr
																									);
															}
															
															if($share=="Yes"){
																$returnArr['response'] = array_merge($returnArr['response'],$pooling_response);
															}
														
														}else{
																$returnArr['response'] = $error_message;
														} 
													}else{
														if($payment_method == 'cash'){
															$returnArr['response'] = $ci->format_string('No cabs available nearby', 'cabs_not_available_nearby').'. '.$ci->format_string('Please try with other payment methods instead of cash payment.','try_other_payment_methods_instead_cash');
														} else {
															$returnArr['response'] = $ci->format_string('No cabs available nearby', 'cabs_not_available_nearby');
														}
													}
												}else{ 
													if ($share=="Yes" && $no_of_seat <= 0){
														$returnArr['response'] = $ci->format_string('Choose a number of seating', 'choose_number_of_seatings');
													}else{
														$returnArr['response'] = $ci->format_string('Sorry ! We do not provide services in your city yet.', 'service_unavailable_in_your_city');
													}
												}
											}else{
												$returnArr['response'] = $ci->format_string('Sorry ! We do not provide services in your city yet.', 'service_unavailable_in_your_city');
											}
										} else {
											$returnArr['response'] = $ci->format_string('Sorry ! We do not provide services in your city yet.', 'service_unavailable_in_your_city');
										}
									} else {
										if($gift_ride == 'Yes' && $giftErr == 'Yes'){
											if($payment_method == 'cash'){
												$returnArr['response'] = $ci->format_string('You can not gift the ride by cash payment method','cant_gift_cash_ride');
											} else {
												if(isset($ongoingGiftRides) && $ongoingGiftRides > 1){
													$returnArr['response'] = $ci->format_string('You can not make more than one gift ride at a time','cant_make_many_gift_ride_a_time');
												} else {
													$returnArr['response'] = $ci->format_string('Your friend is on another ride, You can not sent gift ride right now','your_frd_is_another_ride_cant_gift');
												}
											}
										} else {
											if($platform == 'website'){
												$returnArr['response'] = $ci->format_string('This user already is another ride, You can not make more than one ride at a time','this_user_is_on_another_ride');
											} else {
												#$returnArr['status'] = '2';
												$returnArr['response'] = $ci->format_string('You can not make more than one ride at a time','you_cant_make_more_a_time');
											}
										}
									}
								} else {
									if($platform == 'website'){
										$returnArr['response'] = $ci->format_string('This user is having pending cancellation fee','user_have_pending_cancel_fee');
									} else {
										$returnArr['response'] = $ci->format_string('Please pay your pending cancellation fee to continue booking','pay_pending_cancel_fee');
									}
								}
							} else {
								if($checkUser->num_rows() == 0){
									$returnArr['response'] = $ci->format_string("Invalid User", "invalid_user");
								} else {
									$returnArr['response'] = $ci->format_string("Currently your account is not available, Please contact admin","account_not_avil_contact_admin");
								}
							}
							
						} else { 
							$returnArr['response'] = $ci->format_string("Some Parameters Missing", "some_parameters_missing");
						}
					} else {
						$returnArr['status'] = '1';
						$returnArr['acceptance'] = $acceptance;

						$checkDriver = $ci->app_model->get_selected_fields(DRIVERS, array('_id' => MongoID($driver_id)), array('_id', 'driver_name','first_name', 'image', 'avg_review', 'email', 'dail_code', 'mobile_number', 'vehicle_number', 'vehicle_model'));
						/* Preparing driver information to share with user -- Start */
						$driver_image = base_url().USER_PROFILE_IMAGE_DEFAULT;
						if (isset($checkDriver->row()->image)) {
							if ($checkDriver->row()->image != '') {
								if (file_exists(USER_PROFILE_IMAGE.$checkDriver->row()->image)) {
									$driver_image=base_url().USER_PROFILE_IMAGE.$checkDriver->row()->image;
								}else {
									$driver_image=get_filedoc_path(USER_PROFILE_IMAGE.$checkDriver->row()->image);
								}
			
							}
						}
						$driver_review = 0;
						if (isset($checkDriver->row()->avg_review)) {
							$driver_review = $checkDriver->row()->avg_review;
						}
						
						$vehicle_model = $checkDriver->row()->vehicle_model;

						$driver_profile = array('driver_id' => (string) $checkDriver->row()->_id,
							'driver_name' => (string) $checkDriver->row()->first_name,
							'driver_email' => (string) $checkDriver->row()->email,
							'driver_image' => (string) $driver_image,
							'driver_review' => (string) floatval($driver_review),
							'driver_lat' => floatval($driver_lat),
							'driver_lon' => floatval($driver_lon),
							'min_pickup_duration' => $mindurationtext,
							'ride_id' => (string) $ride_id,
							'phone_number' => (string) $checkDriver->row()->dail_code . $checkDriver->row()->mobile_number,
							'vehicle_number' => (string) $checkDriver->row()->vehicle_number,
							'vehicle_model' => (string) $vehicle_model
						);
						/* Preparing driver information to share with user -- End */

						if (empty($driver_profile)) {
							$driver_profile = json_decode("{}");
						}
						if (empty($riderlocArr)) {
							$riderlocArr = json_decode("{}");
						}
						$returnArr['response'] = array('type' => (string) $type, 
																				'ride_id' => (string) $ride_id,
																				'message' => $ci->format_string('ride confirmed', 'ride_confirmed'),
																				'driver_profile' => $driver_profile,
																				'rider_location' => $riderlocArr
																			);
						if(isset($checkRide->row()->pool_ride)){
							if($checkRide->row()->pool_ride=="Yes"){
								$returnArr['response']['currency'] = (string)$checkRide->row()->currency;
								$returnArr['response']['share_ride'] = 'Yes';
								$returnArr['response']['pool_fare'] = (string)$checkRide->row()->pool_fare["est"];
							}
						}
					}
					
				}else{
					$returnArr['response'] = $ci->format_string("You can book ride only after 15 mins from now", "after_fifteen_mins_from_now");
				}
              } else {
                    $returnArr['response'] = $ci->format_string("You does not choosen any payment method, Please add a payment method to continue","donot_have_payment_method");
              }
			} catch (MongoException $ex) {
				$returnArr['response'] = $ci->format_string("Error in connection", "error_in_connection");
			}
			$returnArr['acceptance'] = $acceptance;
			
			if($platform == 'website' || $platform == 'dispatcher'){
               return $returnArr;
			}

			$json_encode = json_encode($returnArr, JSON_PRETTY_PRINT);
			echo $ci->cleanString($json_encode);
		}
	}
	
	/**
	*
	*	This function accept a booking by the driver
	*	Param @bookingInfo as Array
	*	Holds all the acceptance information Eg. Driver Id, ride id, latitude, longtitude and distance
	*
	**/	
	if ( ! function_exists('request_retry')){
		function request_retry($retryInfo= array()) {
			$ci =& get_instance();
			
			$returnArr['status'] = '0';
			$returnArr['response'] = '';
			$returnArr['ride_view'] = 'stay';
			
			try {
				if(array_key_exists("user_id",$retryInfo)) $user_id =  trim($retryInfo['user_id']); else $user_id = "";
				if(array_key_exists("ride_id",$retryInfo)) $ride_id =  trim($retryInfo['ride_id']); else $ride_id = "";
				
				if($user_id!='' && $ride_id!=''){
					
					$checkUser = $ci->app_model->get_selected_fields(USERS, array('_id' => MongoID($user_id)), array('email','user_name'));
					if ($checkUser->num_rows() == 1) {
						$checkRide = $ci->app_model->get_all_details(RIDES, array('user.id' => $user_id,'ride_id' => $ride_id));
						if ($checkRide->num_rows() == 1) {
							if ($checkRide->row()->ride_status != 'Booked') {
								
								if($checkRide->row()->ride_status == 'Cancelled' || $checkRide->row()->ride_status == 'Expired'){
									if($checkRide->row()->ride_status == 'Cancelled'){
										$returnArr['response'] = $ci->format_string('Job has been cancelled', 'job_has_cancelled');
									} else {
										$returnArr['response'] = $ci->format_string('Job has been expired', 'job_has_expired');
									}
									$json_encode = json_encode($returnArr, JSON_PRETTY_PRINT);
									echo $ci->cleanString($json_encode); exit;
								}
								$returnArr['acceptance'] = 'Yes';
								$driver_id = $checkRide->row()->driver['id'];
								$mindurationtext = '';
								if (isset($checkRide->row()->driver['est_eta'])) {
									$mindurationtext = $checkRide->row()->driver['est_eta'] . '';
								}
								$lat_lon = @explode(',', $checkRide->row()->driver['lat_lon']);
								$driver_lat = $lat_lon[0];
								$driver_lon = $lat_lon[1];

								$checkDriver = $ci->app_model->get_selected_fields(DRIVERS, array('_id' => MongoID($driver_id)), array('_id', 'driver_name','first_name', 'image', 'avg_review', 'email', 'dail_code', 'mobile_number', 'vehicle_number', 'vehicle_model'));
								/* Preparing driver information to share with user -- Start */
								$driver_image = base_url().USER_PROFILE_IMAGE_DEFAULT;
								if (isset($checkDriver->row()->image)) {
									if ($checkDriver->row()->image != '') {
										if (file_exists(USER_PROFILE_IMAGE.$checkDriver->row()->image)) {
											$driver_image=base_url().USER_PROFILE_IMAGE.$checkDriver->row()->image;
										}else {
											$driver_image=get_filedoc_path(USER_PROFILE_IMAGE.$checkDriver->row()->image);
										}
					
									}
								}	
								$driver_review = 0;
								if (isset($checkDriver->row()->avg_review)) {
									$driver_review = $checkDriver->row()->avg_review;
								}
								
								$vehicle_model = $checkDriver->row()->vehicle_model;

								$driver_profile = array('driver_id' => (string) $checkDriver->row()->_id,
									'driver_name' => (string) $checkDriver->row()->first_name,
									'driver_email' => (string) $checkDriver->row()->email,
									'driver_image' => (string) $driver_image,
									'driver_review' => (string) floatval($driver_review),
									'driver_lat' => floatval($driver_lat),
									'driver_lon' => floatval($driver_lon),
									'min_pickup_duration' => $mindurationtext,
									'ride_id' => (string) $ride_id,
									'phone_number' => (string) $checkDriver->row()->dail_code . $checkDriver->row()->mobile_number,
									'vehicle_number' => (string) $checkDriver->row()->vehicle_number,
									'vehicle_model' => (string) $vehicle_model
								);
								/* Preparing driver information to share with user -- End */
								if (empty($driver_profile)) {
									$driver_profile = json_decode("{}");
								}
								if (empty($riderlocArr)) {
									$riderlocArr = json_decode("{}");
								}
								$returnArr['status'] = '1';
								$returnArr['response'] = array('type' => (string)0, 
																				'ride_id' => (string) $ride_id, 
																				'message' => $ci->format_string('ride confirmed', 'ride_confirmed'), 
																				'driver_profile' => $driver_profile, 
																				'rider_location' => $riderlocArr,
																				'ride_status' => $checkRide->row()->ride_status
																			);
							}else{
								$limit = 10;
								$pickup_location = $checkRide->row()->booking_information['pickup']['location'];
								$pickup_lat = $checkRide->row()->booking_information['pickup']['latlong']['lat'];
								$pickup_lon = $checkRide->row()->booking_information['pickup']['latlong']['lon'];
								
								$drop_lat = $checkRide->row()->booking_information['drop']['latlong']['lat'];
								$drop_lon = $checkRide->row()->booking_information['drop']['latlong']['lon'];
								
								$drop_location = $checkRide->row()->booking_information['drop']['location'];
								
								$category = $checkRide->row()->booking_information['service_id'];
								
								$location_id = $checkRide->row()->location['id'];
										
								$coordinates = array(floatval($pickup_lon), floatval($pickup_lat));
                                
                               
								$requested_drivers = $checkRide->row()->requested_drivers;
								if(empty($requested_drivers)){
									$requested_drivers = array();
								}
								
								$airport_location = $ci->app_model->find_airport_location(floatval($pickup_lon), floatval($pickup_lat));
								$airport_id = '';
								if(!empty($airport_location['result'])){
										$airport_id = $airport_location['result'][0]['_id'];
								}
                                
								$location = $ci->app_model->find_location(floatval($pickup_lon), floatval($pickup_lat));
								if (!empty($location['result'])) {
									if($category == POOL_ID){
										$categoryID = array();
										foreach($location['result'][0]['pool_categories'] as $pool_cat){
											$categoryID[] = MongoID($pool_cat);
										}
									}else{
										$categoryID = $category;
									}
									
									$addon_id = '';
									if(isset($checkRide->row()->addon_id)){
										$addon_id = $checkRide->row()->addon_id;
									}
									
									$payment_method = '';
									if(isset($checkRide->row()->payment_method)){
										$payment_method = $checkRide->row()->payment_method;
									}
									
									$trip_type = '';
									if(isset($checkRide->row()->ride_type)){
										$trip_type = $checkRide->row()->ride_type;
									}
									
									$picDropLoc = array(
													'pickup' => ['lon' => floatval($pickup_lon),'lat' => floatval($pickup_lat)], 
													'drop' => ['lon' => floatval($drop_lon),'lat' => floatval($drop_lat)]
												);
									
									if($category == POOL_ID){
										$category_drivers = $ci->app_model->get_nearest_pool_driver($coordinates, $categoryID, $limit,'',$requested_drivers,"",$location_id,$payment_method,$picDropLoc,'',$ride_id);
									} #echo '<pre>'; print_r($category_drivers); die;
									if(empty($category_drivers['result'])){
										$category_drivers = $ci->app_model->get_nearest_driver($coordinates, $categoryID, $limit,'',$requested_drivers,"",$location_id,$trip_type,$addon_id,$airport_id,$picDropLoc,'',$ride_id,$payment_method);
                                        
										if (empty($category_drivers['result'])) {
											$requested_drivers=array();
											$category_drivers = $ci->app_model->get_nearest_driver($coordinates, $categoryID, $limit * 2,'',$requested_drivers,"",$location_id,$trip_type,$addon_id,$airport_id,$picDropLoc,'',$ride_id,$payment_method);
																					
										}
									}
								}
								
								
								$android_driver = array();
								$apple_driver = array();
								$push_and_driver = array();
								$push_ios_driver = array();
								
								foreach ($category_drivers['result'] as $driver) {
									if (isset($driver['push_notification'])) {
										#$d_id=MongoID((string)$category_drivers['result'][0]['_id']);
										$d_id=(string)$driver['_id'];
										array_push($requested_drivers,$d_id);
										if ($driver['push_notification']['type'] == 'ANDROID') {
											if (isset($driver['push_notification']['key'])) {
												if ($driver['push_notification']['key'] != '') {
													$android_driver[] = $driver['push_notification']['key'];
													$k = $driver['push_notification']['key'];
													$push_and_driver[$k] = array('id' => $driver['_id'],'loc' =>  $driver['loc'], 'mode' => $driver['mode'], 'ride_type' => $driver['ride_type'], 'messaging_status' => $driver['messaging_status']);
												}
											}
										}
										if ($driver['push_notification']['type'] == 'IOS') {
											if (isset($driver['push_notification']['key'])) {
												if ($driver['push_notification']['key'] != '') {
													$apple_driver[] = $driver['push_notification']['key'];
													$k = $driver['push_notification']['key'];
													$push_ios_driver[$k] = array('id' => $driver['_id'],'loc' =>  $driver['loc'], 'mode' => $driver['mode'],'ride_type' => $driver['ride_type'], 'messaging_status' => $driver['messaging_status']);
												}
											}
										}
									}
								}
								
								$distance_unit = $ci->data['d_distance_unit'];
								if(isset($checkRide->row()->fare_breakup['distance_unit'])){
									$distance_unit = $checkRide->row()->fare_breakup['distance_unit'];
								}
								
								$est_amount = 0;
								if(isset($checkRide->row()->booking_information['est_amount'])){
									$est_amount = $checkRide->row()->booking_information['est_amount'];
								}
								
								$addon_id = ''; if(isset($checkRide->row()->addon_id)) $addon_id = $checkRide->row()->addon_id;
								$pickupArr =  array('lon' => $pickup_lon,'lat' => $pickup_lat);
								
								$ride_type = 'Normal';
								if(isset($checkRide->row()->pool_ride) && $checkRide->row()->pool_ride == 'Yes'){
									$ride_type = 'Share';
								}
								
								$response_time = $ci->config->item('respond_timeout');
								$options = array($ride_id, $response_time, $pickup_location, $drop_location,$est_amount);
								$userName = $checkUser->row()->user_name;	
								
								/** send request to drivers **/
								sent_ride_request($ride_id,$push_and_driver,$push_ios_driver,$options,$pickupArr,$userName,$addon_id,$distance_unit,$ride_type,$drop_location);
								
								$requested_drivers_final = array_unique($requested_drivers);
								// echo $ride_id;
								// echo "<br>";							
								// echo "<pre>"; print_r($requested_drivers); die;
								$ci->app_model->update_details(RIDES, array("requested_drivers"=>$requested_drivers_final), array('ride_id' => $ride_id));
								$returnArr['response'] = $ci->format_string("Searching for a driver", "searching_for_a_driver");
							}
							$returnArr['status'] = '1';
						}else{
							$returnArr['response'] = $ci->format_string("Invalid Ride", "invalid_ride");
						}
					}else{
						$returnArr['response'] = $ci->format_string("Driver not found", "driver_not_found");
					}
				}else{
					$returnArr['response'] = $ci->format_string("Some Parameters Missing", "some_parameters_missing");
				}
			} catch (MongoException $ex) {				
				$returnArr['response'] = $ci->format_string("Error in connection", "error_in_connection");
			}

			$json_encode = json_encode($returnArr, JSON_PRETTY_PRINT);
			echo $ci->cleanString($json_encode);
		}
	}
	
	
	/**
	*
	*	This function delete a booking request by user
	*	Param @deleteInfo as Array
	*	Returns all the acceptance information Eg. Driver Id, ride id, latitude, longtitude and distance
	*
	**/	
	if ( ! function_exists('request_delete')){
		function request_delete($deleteInfo= array()) {
			$ci =& get_instance();
			
			$returnArr['status'] = '0';
			$returnArr['response'] = '';
			$returnArr['acceptance'] = 'No';
			
			try {
				if(array_key_exists("user_id",$deleteInfo)) $user_id =  trim($deleteInfo['user_id']); else $user_id = "";
				if(array_key_exists("ride_id",$deleteInfo)) $ride_id =  trim($deleteInfo['ride_id']); else $ride_id = "";
				
				if(array_key_exists("mode",$deleteInfo)) $mode =  trim($deleteInfo['mode']); else $mode = "";
				
				if($user_id!='' && $ride_id!=''){
					$checkUser = $ci->app_model->get_selected_fields(USERS, array('_id' => MongoID($user_id)), array('email'));
					if ($checkUser->num_rows() == 1) {
						$checkRide = $ci->app_model->get_all_details(RIDES, array('user.id' => $user_id,'ride_id' => $ride_id));
						if ($checkRide->num_rows() == 1) {
							$ride_status = $checkRide->row()->ride_status;
							$driver_id = (string)$checkRide->row()->driver["id"];
							$hasDrivers = array("Confirmed","Arrived","Onride","Finished","Completed","Cancelled");
							if (in_array($ride_status,$hasDrivers) && $driver_id!="") {
								$returnArr['acceptance'] = 'Yes';
								$mindurationtext = '';
								if (isset($checkRide->row()->driver['est_eta'])) {
									$mindurationtext = $checkRide->row()->driver['est_eta'] . '';
								}
								$lat_lon = @explode(',', $checkRide->row()->driver['lat_lon']);
								$driver_lat = $lat_lon[0];
								$driver_lon = $lat_lon[1];

								$checkDriver = $ci->app_model->get_selected_fields(DRIVERS, array('_id' => MongoID($driver_id)), array('_id', 'driver_name', 'first_name','image', 'avg_review', 'email', 'dail_code', 'mobile_number', 'vehicle_number', 'vehicle_model'));
								/* Preparing driver information to share with user -- Start */
								$driver_image = base_url().USER_PROFILE_IMAGE_DEFAULT;
								if (isset($checkDriver->row()->image)) {
									if ($checkDriver->row()->image != '') {
										if (file_exists(USER_PROFILE_IMAGE.$checkDriver->row()->image)) {
											$driver_image=base_url().USER_PROFILE_IMAGE.$checkDriver->row()->image;
										}else {
											$driver_image=get_filedoc_path(USER_PROFILE_IMAGE.$checkDriver->row()->image);
										}
					
									}
								}
								$driver_review = 0;
								if (isset($checkDriver->row()->avg_review)) {
									$driver_review = $checkDriver->row()->avg_review;
								}
								
								$vehicle_model = $checkDriver->row()->vehicle_model;

								$driver_profile = array('driver_id' => (string) $checkDriver->row()->_id,
									'driver_name' => (string) $checkDriver->row()->first_name,
									'driver_email' => (string) $checkDriver->row()->email,
									'driver_image' => (string) $driver_image,
									'driver_review' => (string) floatval($driver_review),
									'driver_lat' => floatval($driver_lat),
									'driver_lon' => floatval($driver_lon),
									'min_pickup_duration' => $mindurationtext,
									'ride_id' => (string) $ride_id,
									'phone_number' => (string) $checkDriver->row()->dail_code . $checkDriver->row()->mobile_number,
									'vehicle_number' => (string) $checkDriver->row()->vehicle_number,
									'vehicle_model' => (string) $vehicle_model
								);
								/* Preparing driver information to share with user -- End */
								if (empty($driver_profile)) {
									$driver_profile = json_decode("{}");
								}
								if (empty($riderlocArr)) {
									$riderlocArr = json_decode("{}");
								}
								$returnArr['response'] = array('type' => (string)0, 
																			'ride_id' => (string) $ride_id, 
																			'message' => $ci->format_string('ride confirmed', 'ride_confirmed'), 
																			'driver_profile' => $driver_profile, 
																			'rider_location' => $riderlocArr
																		);
							}else{
								if(isset($checkRide->row()->authorization_info)&& $checkRide->row()->authorization_info!=''){
                                    $amount_captured = $checkRide->row()->authorization_info['amount_captured'];
                                    $charge_id = $checkRide->row()->authorization_info['charge_id'];
                                    refund_authorized_ride_amount($charge_id);
                                }
								/* Saving Unaccepted Ride for future reference */
								save_ride_details_for_stats($ride_id);
								/* Saving Unaccepted Ride for future reference */
								
								$rideArr = $checkRide->result_array();
								$dataArr = $rideArr[0]; unset($dataArr['_id']);
								$ci->app_model->simple_insert(MISSED_RIDES,$dataArr);
								$ci->app_model->commonDelete(RIDES, array('ride_id' => $ride_id));
								
								$returnArr['response'] = $ci->format_string('Ride request cancelled', 'ride_request_cancelled');
								if($mode=="auto"){
									$returnArr['response'] = $ci->format_string('No drivers in your area please try again');
								}
								$ci->app_model->update_details(PHONE_CALL_LOG,array('status'=>'inactive'),array('ride_id'=>(string)$ride_id));
							}
							$returnArr['status'] = '1';
						}else{
							$returnArr['response'] = $ci->format_string("This ride is unavailable", "ride_unavailable");
						}
					}else{
						$returnArr['response'] = $ci->format_string("Invalid User", "invalid_user");
					}
				}else{
					$returnArr['response'] = $ci->format_string("Some Parameters Missing", "some_parameters_missing");
				}
			} catch (MongoException $ex) {				
				$returnArr['response'] = $ci->format_string("Error in connection", "error_in_connection");
			}

			$json_encode = json_encode($returnArr, JSON_PRETTY_PRINT);
			echo $ci->cleanString($json_encode);
		}
	}
	
	/**
	*
	*	This function accept a booking by the driver
	*	Param @bookingInfo as Array
	*	Holds all the acceptance information Eg. Driver Id, ride id, latitude, longtitude and distance
	*
	**/	
	if ( ! function_exists('accepting_ride')){
		function accepting_ride($acceptanceInfo= array()) {
			$ci =& get_instance();
			$returnArr['status'] = '0';
			$returnArr['response'] = '';
			$returnArr['ride_view'] = 'stay';
			
			try {
				if(array_key_exists("driver_id",$acceptanceInfo)) $driver_id =  trim($acceptanceInfo['driver_id']); else $driver_id = "";
				if(array_key_exists("ride_id",$acceptanceInfo)) $ride_id =  trim($acceptanceInfo['ride_id']); else $ride_id = "";
				if(array_key_exists("driver_lat",$acceptanceInfo)) $driver_lat =  trim($acceptanceInfo['driver_lat']); else $driver_lat = "";
				if(array_key_exists("driver_lon",$acceptanceInfo)) $driver_lon =  trim($acceptanceInfo['driver_lon']); else $driver_lon = "";
				if(array_key_exists("distance",$acceptanceInfo)) $distance =  trim($acceptanceInfo['distance']); else $distance = 0;
				if(array_key_exists("ack_id",$acceptanceInfo)) $ack_id =  trim($acceptanceInfo['ack_id']); else $ack_id = '';
				
				if ($driver_id!="" && $ride_id!="" && $driver_lat!="" && $driver_lon!="" && $distance!="") {
					$checkDriver = $ci->app_model->get_selected_fields(DRIVERS, array('_id' => MongoID($driver_id)), array('_id', 'driver_name','first_name', 'image', 'avg_review', 'email', 'dail_code', 'mobile_number', 'vehicle_number', 'vehicle_model', 'driver_commission','company_id', 'dispatcher_id','last_online_time','ride_type','mode','duty_ride','category'));
					if ($checkDriver->num_rows() == 1) {
						$company_id = '';
						if(isset($checkDriver->row()->company_id) && $checkDriver->row()->company_id != ''){
							$company_id = $checkDriver->row()->company_id;
						}
						$dispatcher_id = '';
						if(isset($checkDriver->row()->dispatcher_id) && $checkDriver->row()->dispatcher_id != ''){
							$dispatcher_id = $checkDriver->row()->dispatcher_id;
						}
						$checkRide = $ci->app_model->get_all_details(RIDES, array('ride_id' => $ride_id));
						if ($checkRide->num_rows() == 1) {
							$isGo = FALSE;
							if ($checkDriver->row()->ride_type== 'Normal' && $checkDriver->row()->mode== 'Available'){
									$isGo = TRUE;
							}
							if ($checkDriver->row()->ride_type== 'Share'){
									$isGo = TRUE;
							}
							if ($checkRide->row()->ride_status == 'Booked' || $checkRide->row()->driver['id'] == $driver_id) {
								if ($checkRide->row()->ride_status == 'Booked') {
									$userVal = $ci->app_model->get_selected_fields(USERS, array('_id' => MongoID($checkRide->row()->user['id'])), array('_id', 'user_name', 'email', 'image', 'avg_review', 'phone_number', 'country_code', 'push_type', 'push_notification_key'));
									if ($userVal->num_rows() > 0) {
										$service_id = $checkRide->row()->booking_information['service_id'];
										/* Update the ride information with fare and driver details -- Start */
										$pickup_lon = $checkRide->row()->booking_information['pickup']['latlong']['lon'];
										$pickup_lat = $checkRide->row()->booking_information['pickup']['latlong']['lat'];
										$service_type=$checkRide->row()->booking_information['service_type'];
										$from = $driver_lat . ',' . $driver_lon;
										$to = $pickup_lat . ',' . $pickup_lon;

										$waypoints_str='';
										if($checkDriver->row()->duty_ride!='' && $checkDriver->row()->upcoming_ride=='' && $ride_id!=''){ 
											$currenct_ride=$ci->app_model->get_selected_fields(RIDES,array('ride_id'=>$checkDriver->row()->duty_ride),array('booking_information','ride_id')); 
											if($currenct_ride->num_rows() > 0){
												if(isset($currenct_ride->row()->booking_information['drop']['latlong']['lat']) && isset($currenct_ride->row()->booking_information['drop']['latlong']['lon'])){
													$waypoints_str='&waypoints=';
													$waypoints_str.='via:'.$currenct_ride->row()->booking_information['drop']['latlong']['lat'].','.$currenct_ride->row()->booking_information['drop']['latlong']['lon'].'|';  
												}
											}  
										}
										
										
										$urls = 'https://maps.googleapis.com/maps/api/directions/json?origin=' . $from . '&destination=' . $to . '&alternatives=true'.$waypoints_str.'&sensor=false&mode=driving'.$ci->data['google_maps_api_key'];
										#echo $urls; die;
										$gmap = file_get_contents($urls);
										$map_values = json_decode($gmap);
										$routes = $map_values->routes;
										#echo "<pre>"; print_r($gmap); die;
										if(!empty($routes)){
											#usort($routes, create_function('$a,$b', 'return intval($a->legs[0]->distance->value) - intval($b->legs[0]->distance->value);'));
											
											$distance_unit = $ci->data['d_distance_unit'];
											$duration_unit = 'min';
											if(isset($checkRide->row()->fare_breakup)){
												if($checkRide->row()->fare_breakup['distance_unit']!=''){
													$distance_unit = $checkRide->row()->fare_breakup['distance_unit'];
													$duration_unit = $checkRide->row()->fare_breakup['duration_unit'];
												} 
											}

											$mindistance = 1;
											$minduration = 1;
											$mindurationtext = '';
											if (!empty($routes[0])) {
												#$mindistance = ($routes[0]->legs[0]->distance->value) / 1000;
												$min_distance = $routes[0]->legs[0]->distance->text;
												if (preg_match('/km/',$min_distance)){
													$return_distance = 'km';
												}else if (preg_match('/mi/',$min_distance)){
													$return_distance = 'mi';
												}else if (preg_match('/m/',$min_distance)){
													$return_distance = 'm';
												} else {
													$return_distance = 'km';
												}
												
												$mindistance = floatval(str_replace(',','',$min_distance));
												if($distance_unit!=$return_distance){
													if($distance_unit=='km' && $return_distance=='mi'){
														$mindistance = $mindistance * 1.60934;
													} else if($distance_unit=='mi' && $return_distance=='km'){
														$mindistance = $mindistance * 0.621371;
													} else if($distance_unit=='km' && $return_distance=='m'){
														$mindistance = $mindistance / 1000;
													} else if($distance_unit=='mi' && $return_distance=='m'){
														$mindistance = $mindistance * 0.00062137;
													}
												}
												$mindistance = floatval(round($mindistance,2));
										
										
												$minduration = ($routes[0]->legs[0]->duration->value) / 60;
												$est_pickup_time = (time()) + $routes[0]->legs[0]->duration->value;
												#$est_pickup_time=(MongoEPOCH($checkRide->row()->booking_information['est_pickup_date']))+$routes[0]->legs[0]->duration->value;
												$mindurationtext = $routes[0]->legs[0]->duration->text;
											}

											$fareDetails = $ci->app_model->get_all_details(LOCATIONS, array('_id' => MongoID($checkRide->row()->location['id'])));
											if ($fareDetails->num_rows() > 0) {
												$service_tax = 0.00;
												if (isset($fareDetails->row()->service_tax)) {
													if ($fareDetails->row()->service_tax > 0) {
														$service_tax = $fareDetails->row()->service_tax;
													}
												}
												if (isset($fareDetails->row()->fare[$service_id]) && $service_id!=POOL_ID) {
													$peak_time = '';
													$night_charge = '';
													$peak_time_amount = '';
													$night_charge_amount = '';
													$min_amount = 0.00;
													$max_amount = 0.00;
													$pickup_datetime = MongoEPOCH($checkRide->row()->booking_information['est_pickup_date']);
													$pickup_date = date('Y-m-d', MongoEPOCH($checkRide->row()->booking_information['est_pickup_date']));
													
													/*** time zone conversion ***/
													if(isset($checkRide->row()->time_zone) && $checkRide->row()->time_zone != ''){
														$from_timezone = date_default_timezone_get(); 
														$to_timezone = $checkRide->row()->time_zone; 
														$pickup_datetime = strtotime($ci->timeZoneConvert(date('Y-m-d H:i',$pickup_datetime),$from_timezone, $to_timezone,'Y-m-d H:i:s'));
														$pickup_date = date('Y-m-d',$pickup_datetime);
													} 
													/***********************/
													
													
													if ($fareDetails->row()->peak_time == 'Yes') {
														$time1 = strtotime($pickup_date . ' ' . $fareDetails->row()->peak_time_frame['from']);
														$time2 = strtotime($pickup_date . ' ' . $fareDetails->row()->peak_time_frame['to']);
														
														$ptc = FALSE;
														if ($time1 > $time2) {
															if (date('A', $pickup_datetime) == 'PM') {
																if (($time1 <= $pickup_datetime) && (strtotime('+1 day', $time2) >= $pickup_datetime)) {
																	$ptc = TRUE;
																}
															} else {
																if ((strtotime('-1 day', $time1) <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
																	$ptc = TRUE;
																}
															}
														} else if ($time1 < $time2) {
															if (($time1 <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
																$ptc = TRUE;
															}
														}
														if ($ptc) {
															$peak_time_amount = $fareDetails->row()->fare[$service_id]['peak_time_charge'];
														}
													}
													if ($fareDetails->row()->night_charge == 'Yes') {
														$time1 = strtotime($pickup_date . ' ' . $fareDetails->row()->night_time_frame['from']);
														$time2 = strtotime($pickup_date . ' ' . $fareDetails->row()->night_time_frame['to']);
														
														$nc = FALSE;
														if ($time1 > $time2) {
															if (date('A', $pickup_datetime) == 'PM') {
																if (($time1 <= $pickup_datetime) && (strtotime('+1 day', $time2) >= $pickup_datetime)) {
																	$nc = TRUE;
																}
															} else {
																if ((strtotime('-1 day', $time1) <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
																	$nc = TRUE;
																}
															}
														} else if ($time1 < $time2) {
															if (($time1 <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
																$nc = TRUE;
															}
														}
														if ($nc) {
															$night_charge_amount = $fareDetails->row()->fare[$service_id]['night_charge'];
														}
													}
													
													
                                                    $stops_free_wait_time = 0;
                                                    if(isset($fareDetails->row()->fare[$service_id]['stops_free_wait_time'])){
                                                        $stops_free_wait_time = $fareDetails->row()->fare[$service_id]['stops_free_wait_time'];
                                                    }
													if(isset($fareDetails->row()->surge_status)  && ($fareDetails->row()->surge_status =='Yes')){
														$peak_time_amount ='';
														$night_charge_amount ='';
													}
													
													$booking_fee = $minimum_fare = 0;
													if(isset($fareDetails->row()->fare[$service_id]['booking_fee'])){
														$booking_fee = $fareDetails->row()->fare[$service_id]['booking_fee'];
													}
													if(isset($fareDetails->row()->fare[$service_id]['minimum_fare'])){
														$minimum_fare = $fareDetails->row()->fare[$service_id]['minimum_fare'];
													}
													
													$fare_breakup = array('min_km' => (string) $fareDetails->row()->fare[$service_id]['min_km'],
														'min_time' => (string) $fareDetails->row()->fare[$service_id]['min_time'],
														'min_fare' => (string) $fareDetails->row()->fare[$service_id]['min_fare'],
														'per_km' => (string) $fareDetails->row()->fare[$service_id]['per_km'],
														'per_minute' => (string) $fareDetails->row()->fare[$service_id]['per_minute'],
                                                        'cancellation_charge'=>(string) $fareDetails->row()->fare[$service_id]['cancellation_charge'],
														'wait_per_minute' => (string) $fareDetails->row()->fare[$service_id]['wait_per_minute'],
														'peak_time_charge' => (string) $peak_time_amount,
														'night_charge' => (string) $night_charge_amount,
                                                        'stops_free_wait_time' => (string) $stops_free_wait_time,
														'distance_unit' => (string) $distance_unit,
														'duration_unit' => (string) $duration_unit,
														'booking_fee' => (string) $booking_fee,
														'minimum_fare' => (string) $minimum_fare
													);
												}
											}
											
											$vehicle_model = $checkDriver->row()->vehicle_model;
											
											$driverInfo = array('id' => (string) $checkDriver->row()->_id,
												'name' => (string) $checkDriver->row()->first_name,
												'email' => (string) $checkDriver->row()->email,
												'phone' => (string) $checkDriver->row()->dail_code . $checkDriver->row()->mobile_number,
												'vehicle_model' => (string) $vehicle_model,
												'vehicle_no' => (string) $checkDriver->row()->vehicle_number,
												'lat_lon' => (string) $driver_lat . ',' . $driver_lon,
												'est_eta' => (string) $mindurationtext
											);
											$history = array('booking_time' => $checkRide->row()->booking_information['booking_date'],
												'estimate_pickup_time' => MongoDATE($est_pickup_time),
												'driver_assigned' => MongoDATE(time())
											);
											$driver_commission = $checkRide->row()->commission_percent;
											if (isset($checkDriver->row()->driver_commission)) {
												$driver_commission = $checkDriver->row()->driver_commission;
											}
											$curr_duty_ride = $ride_id;
											if(isset($checkDriver->row()->duty_ride)){
												if($checkDriver->row()->duty_ride!="") $curr_duty_ride = $checkDriver->row()->duty_ride;
											}
											
											if($driver_commission>100) $driver_commission = 100;

											$rideDetails = array('ride_status' => 'Confirmed',
												'commission_percent' => floatval($driver_commission),
												'driver' => $driverInfo,
												'company_id'=>$company_id,
												'dispatcher_id'=>$dispatcher_id,
												'tax_breakup' => array('service_tax' => $service_tax),
												'booking_information.est_pickup_date' => MongoDATE($est_pickup_time),
												'history' => $history
											);
											
											if($company_id != ''){
												$getCompany = $ci->app_model->get_selected_fields(COMPANY,array('_id' => $company_id),array('company_name','email','mobile_number','dail_code'));
												$companyInfo = array('id' => (string)$company_id,
																	 'name' => $getCompany->row()->company_name,
																	 'email' => $getCompany->row()->email,
																	 'phone' => $getCompany->row()->dail_code.$getCompany->row()->mobile_number);
												$rideDetails['company'] = $companyInfo;
											}
											
											$active_trips = 0;
											if($service_id!=POOL_ID){
												$active_trips = 1;
												$rideDetails['fare_breakup'] = $fare_breakup;
											}else if($service_id==POOL_ID){
												
												if(!isset($rideDetails['fare_breakup'])){
													$driverCat = (string)$checkDriver->row()->category;
													$rideDetails['fare_breakup.cancellation_charge'] = $fareDetails->row()->fare[$driverCat]['cancellation_charge'];
												}
												
												$pooling_with = array(); $co_rider = array(); $pool_type = 0;
												
												$checkAvailRide = $ci->app_model->get_driver_active_trips($driver_id,$curr_duty_ride,"Share");
												if($checkAvailRide->num_rows()>0){
													$active_trips = intval($checkAvailRide->num_rows());
												}
												if($active_trips>=1){
													$pool_type = 1;
													$active_trips++;
													$pooling_with = array("name"=>$checkAvailRide->row()->user["name"],
																					"id"=>$checkAvailRide->row()->user["id"]
																				);
													$co_rider = array("name"=>$checkAvailRide->row()->user["name"],
																					"id"=>$checkAvailRide->row()->user["id"]
																				);
													$ext_pooling_with = array("name"=>$checkRide->row()->user["name"],
																					"id"=>$checkRide->row()->user["id"]
																				);
													$ext_co_rider = array("name"=>$checkRide->row()->user["name"],
																					"id"=>$checkRide->row()->user["id"]
																				);
													if(isset($checkAvailRide->row()->ride_id)){
														$ext_ride_id = (string)$checkAvailRide->row()->ride_id;
														if(isset($checkAvailRide->row()->pooling_with)){
															$ext_pooling_with = $ext_pooling_with;
														}
														if(isset($checkAvailRide->row()->co_rider)){
															$ext_co_rider_val = $checkAvailRide->row()->co_rider;
															$ext_co_rider_val[] = $ext_co_rider;
															$ext_co_rider = $ext_co_rider_val;
														}
														$ext_ride_Arr = array("pooling_with"=>$ext_pooling_with,"co_ride"=>$ext_co_rider);
														
														
														/** Discounted Pool fare update to first rider  **/
														$mindistance = $checkAvailRide->row()->booking_information['est_travel_distance'];
														$minduration = $checkAvailRide->row()->booking_information['est_travel_duration'];
														$location_id = $checkAvailRide->row()->location['id'];
														$ci->load->helper('pool_helper');
														$poolFareResponse = get_pool_fare($mindistance,$minduration,$location_id,'discount',0,$pickup_lat,$pickup_lon);
														if($poolFareResponse["status"]=="1"){
															$est_amount = 0; $tax_amount = 0;
															if($no_of_seat==1){
																$est_amount = $poolFareResponse["passenger"];
																$tax_amount = $poolFareResponse["single_tax"];
															}else if($no_of_seat==2){
																$est_amount = $poolFareResponse["co_passenger"];
																$tax_amount = $poolFareResponse["double_tax"];
															}
															$pool_fare = array("est"=>(string)round($est_amount,2),
																						"tax"=>(string)round($tax_amount, 2),
																						"tax_percent"=>(string)$poolFareResponse["tax_percent"],
																						"base_fare"=>(string)$poolFareResponse["base_fare"],
																						"single_percent"=>(string)$poolFareResponse["single_percent"],
																						"double_percent"=>(string)$poolFareResponse["double_percent"],
																						"passanger"=>(string)round($poolFareResponse["passenger"], 2),
																						"co_passanger"=>(string)round($poolFareResponse["co_passenger"], 2),
																						"fare_category"=>$poolFareResponse["fare_category"],
																						"booking_fee"=>$poolFareResponse["booking_fee"]
																					);
														}
														
														$ext_ride_Arr['pool_discount_applied'] = 'Yes';
														$ext_ride_Arr['pool_fare'] = $pool_fare;
														$ext_ride_Arr['booking_information.est_amount'] = $est_amount;
														/*************/
														
														
														$ci->app_model->update_details(RIDES, $ext_ride_Arr, array('ride_id' => $ext_ride_id));
														
														/*	Sending the notification regarding the matching */
														$ext_user_id = $checkAvailRide->row()->user["id"];
														if($ext_user_id!=""){
															$extUserVal = $ci->app_model->get_selected_fields(USERS, array('_id' => MongoID($ext_user_id)), array('_id','push_type','push_notification_key'));
															if ($extUserVal->num_rows() > 0) {
																if (isset($extUserVal->row()->push_type)) {
																	if ($extUserVal->row()->push_type != '') {
																		$message = $ci->format_string('Ride has been matched and you got extra discount', 'ride_matched','','user',(string)$extUserVal->row()->_id);						
																		$optionsFExt = array('ride_id' => $ext_ride_id);
																		if ($extUserVal->row()->push_type == 'ANDROID') {
																			if (isset($extUserVal->row()->push_notification_key['gcm_id'])) {
																				if ($extUserVal->row()->push_notification_key['gcm_id'] != '') {
																					$ci->sendPushNotification($extUserVal->row()->push_notification_key['gcm_id'], $message, 'track_reload', 'ANDROID', $optionsFExt, 'USER');
																				}
																			}
																		}
																		if ($extUserVal->row()->push_type == 'IOS') {
																			if (isset($extUserVal->row()->push_notification_key['ios_token'])) {
																				if ($extUserVal->row()->push_notification_key['ios_token'] != '') {
																					$ci->sendPushNotification($extUserVal->row()->push_notification_key['ios_token'], $message, 'track_reload', 'IOS', $optionsFExt, 'USER');
																				}
																			}
																		}
																	}
																}
															}
														}
														/*	Sending the notification regarding the matching	*/
													}
												}else{
													$active_trips = 1;
												}
												
												$rideDetails['pooling_with'] = $pooling_with;
												$rideDetails['co_rider'] = $co_rider;
												$rideDetails['pool_type'] = (string)$pool_type;
												$rideDetails['pool_id'] = (string)$curr_duty_ride;
											}
											$driverSRls = array("ride_type"=>"Normal",
																		"duty_ride"=>(string)$ride_id,
																		"share_drop"=>array()
																	);
											if(isset($checkRide->row()->pool_ride)){
												if($checkRide->row()->pool_ride=="Yes"){
													$share_drop = $checkRide->row()->booking_information['drop']['latlong'];
													$driverSRls = array("ride_type"=>"Share",
																				"duty_ride"=>(string)$curr_duty_ride,
																				"share_drop"=>$share_drop
																			);
													#echo "<pre>"; print_r($driverSRls);die						
																			
													/*******  Set share pool route info *****/
													$ci->load->helper('pool_helper');
													if(isset($routes[0]->legs[0]) && !empty($routes[0]->legs[0])){
														$legs = $routes[0]->legs[0];
													}
													$legs = base64_encode(json_encode($legs));
													$poolRouteData = [
														'driver_id' => (string) $checkDriver->row()->_id,
														'ride_id' => $ride_id,
														'action' => 'set',
														'legs' => ''
													]; 
													$routeRes = update_share_pool_route_info($poolRouteData,'auto');
													#echo '<pre>'; print_r($routeRes); die;
												}
											}
											
											
											$rideDetails['masked_number'] = '';
											if($ci->data['phone_masking_status']=='Yes'){
												$maskednumberArr = $ci->data['masked_numbers'];
												$masked_number = $maskednumberArr[array_rand($ci->data['masked_numbers'])];
												$rideDetails['masked_number'] = $masked_number;
											}
											
											$user_phone = $checkRide->row()->user['phone'];
											$driver_phone = $checkDriver->row()->dail_code . ltrim($checkDriver->row()->mobile_number,0);
											$phonecallArr = array('user_phone'=>(string)$user_phone,
																'driver_phone'=>(string)$driver_phone,
																'caller_id'=>(string)$masked_number,
																'ride_id'=>(string)$ride_id,
																'status'=>'active'
															);
											$ci->app_model->update_details(PHONE_CALL_LOG,array('status'=>'inactive'),array('ride_id'=>(string)$ride_id));
											$ci->app_model->simple_insert(PHONE_CALL_LOG,$phonecallArr);
											#	Connecting to phone masking  #
											$checkBooked = $ci->app_model->get_selected_fields(RIDES, array('ride_id' => $ride_id, 'ride_status' => 'Booked'), array('ride_id', 'ride_status'));
											$checkAvailable = $ci->app_model->get_selected_fields(DRIVERS, array('_id' => MongoID($driver_id)), array('mode','duty_ride','ride_type','upcoming_ride'));
											$availablity = false;
											if ($checkAvailable->row()->mode == 'Available') {
												$availablity = true;
											}else{
												$hasPool_ride = false;
												if(isset($checkAvailable->row()->duty_ride)){
													if ($checkAvailable->row()->duty_ride != '' && $checkAvailable->row()->ride_type == 'Share') {
														$hasPool_ride = true;
													}
												}
											}
											#echo "<pre>"; print_r('a'); die;
											
											$ispool_ride = 'No';
											 if(isset($checkRide->row()->pool_ride) && $checkRide->row()->pool_ride == 'Yes'){
												$ispool_ride = 'Yes';
											 }
											
											if($ispool_ride == 'No'){
												$upcoming_ride_status='No';
												if(!$hasPool_ride){
													if(!$availablity){
														if ((isset($checkAvailable->row()->duty_ride) && $checkAvailable->row()->duty_ride!='') && (!isset($checkAvailable->row()->upcoming_ride) || $checkAvailable->row()->upcoming_ride=='')) {
															$availablity = true;
															$active_trips = 2;
															if($ispool_ride == 'No'){
																$driverSRls['upcoming_ride'] =$ride_id;
															}
															$upcoming_ride_status ='Yes';
															unset($driverSRls['duty_ride']);
														}
													}
												}
											}
												if($upcoming_ride_status=='No' && $ispool_ride == 'No'){
													$driverSRls['upcoming_ride'] =$ride_id;
												}
												
												if(isset($checkAvailable->row()->duty_ride) && $checkAvailable->row()->duty_ride!=''){
													unset($driverSRls['duty_ride']);
													$active_trips = 2;
												}
											
											
											if ($checkBooked->num_rows() > 0 && ($availablity === true || $hasPool_ride == true)) {
												$ci->app_model->update_details(RIDES, $rideDetails, array('ride_id' => $ride_id));
												/* Update the ride information with fare and driver details -- End */

												
												/* Update the driver status to Booked */
												$driverSRls['mode'] = "Booked";
												$driverSRls['active_trips'] = intval($active_trips);
												$driverSRls['loc'] = array('lon' => floatval($driver_lon),'lat' => floatval($driver_lat));
												#echo "<pre>"; print_r($driverSRls); die;
												$ci->app_model->update_details(DRIVERS, $driverSRls, array('_id' => MongoID($driver_id)));
												
												/* Update the no of rides  */
												$ci->app_model->update_user_rides_count('no_of_rides', $userVal->row()->_id);
												//$ci->app_model->update_driver_rides_count('no_of_rides', $driver_id);

												/* Update Stats Starts */
												$current_date = MongoDATE(strtotime(date("Y-m-d 00:00:00")));
												$field = array('ride_booked.hour_' . date('H') => 1, 'ride_booked.count' => 1);
												$ci->app_model->update_stats(array('day_hour' => $current_date), $field, 1);
												/* Update Stats End */


												/* Preparing driver information to share with user -- Start */
												$driver_image = base_url().USER_PROFILE_IMAGE_DEFAULT;
												if (isset($checkDriver->row()->image)) {
													if ($checkDriver->row()->image != '') {
														if (file_exists(USER_PROFILE_IMAGE.$checkDriver->row()->image)) {
															$driver_image=base_url().USER_PROFILE_IMAGE.$checkDriver->row()->image;
														}else {
															$driver_image=get_filedoc_path(USER_PROFILE_IMAGE.$checkDriver->row()->image);
														}
									
													}
												}
												$driver_review = 0;
												if (isset($checkDriver->row()->avg_review)) {
													$driver_review = $checkDriver->row()->avg_review;
												}
												$driver_profile = array('driver_id' => (string) $checkDriver->row()->_id,
													'driver_name' => (string) $checkDriver->row()->first_name,
													'driver_email' => (string) $checkDriver->row()->email,
													'driver_image' => (string)$driver_image,
													'driver_review' => (string) floatval($driver_review),
													'driver_lat' => floatval($driver_lat),
													'driver_lon' => floatval($driver_lon),
													'min_pickup_duration' => $mindurationtext,
													'ride_id' => $ride_id,
													'phone_number' => (string) $checkDriver->row()->dail_code . $checkDriver->row()->mobile_number,
													'vehicle_number' => (string) $checkDriver->row()->vehicle_number,
													'vehicle_model' => (string) $vehicle_model,
													'pickup_location' => (string) $checkRide->row()->booking_information['pickup']['location'],
													'pickup_lat' => (string) $pickup_lat,
													'pickup_lon' => (string) $pickup_lon
												);
												/* Preparing driver information to share with user -- End */


												/* Preparing user information to share with driver -- Start */
											
												$user_image = base_url().USER_PROFILE_IMAGE_DEFAULT;
												if ($userVal->row()->image != '') {
													if (file_exists(USER_PROFILE_IMAGE.$userVal->row()->image)) {
														$user_image=base_url().USER_PROFILE_IMAGE.$userVal->row()->image;
													}else {
														$user_image=get_filedoc_path(USER_PROFILE_IMAGE.$userVal->row()->image);
													}
												}
												$user_review = 0;
												if (isset($userVal->row()->avg_review)) {
													$user_review = $userVal->row()->avg_review;
												}
												$user_profile = array('user_id' => (string)$userVal->row()->_id,
													'user_name' => $userVal->row()->user_name,
													'user_email' => $userVal->row()->email,
													'phone_number' => (string) $userVal->row()->country_code . $userVal->row()->phone_number,
													'user_image' => $user_image,
													'user_review' => floatval($user_review),
													'ride_id' => $ride_id,
													'pickup_location' => $checkRide->row()->booking_information['pickup']['location'],
													'pickup_lat' => $pickup_lat,
													'pickup_lon' => $pickup_lon,
													'pickup_time' => date("H:i jS M, Y", MongoEPOCH($checkRide->row()->booking_information['est_pickup_date']))
												);
												/* Preparing user information to share with driver -- End */

												/* Sending notification to user regarding booking confirmation -- Start */
												# Push notification
												if (isset($userVal->row()->push_type)) {
													if ($userVal->row()->push_type != '') {
														$dr_name = ucfirst($checkDriver->row()->first_name).' ('.number_format($checkDriver->row()->avg_review,1).' stars)';
														$etaTime = str_replace('min','minute',$mindurationtext);
														$message = $dr_name.' '.$ci->format_string('will arrive in','will_arrive_in', '', 'user', (string)$userVal->row()->_id).' '.$etaTime.'. '.$ci->format_string('Confirm their license plate','confirm_license_plate', '', 'user', (string)$userVal->row()->_id).': ('.$checkDriver->row()->vehicle_number.').';		
														$options = $driver_profile;
														
														//print_r($options);
														if ($userVal->row()->push_type == 'ANDROID') {
															if (isset($userVal->row()->push_notification_key['gcm_id'])) {
																if ($userVal->row()->push_notification_key['gcm_id'] != '') {
																	$ci->sendPushNotification($userVal->row()->push_notification_key['gcm_id'], $message, 'ride_confirmed', 'ANDROID', $driver_profile, 'USER');
																}
															}
														}
														if ($userVal->row()->push_type == 'IOS') {
															if (isset($userVal->row()->push_notification_key['ios_token'])) {
																if ($userVal->row()->push_notification_key['ios_token'] != '') {
																	$ci->sendPushNotification($userVal->row()->push_notification_key['ios_token'], $message, 'ride_confirmed', 'IOS', $driver_profile, 'USER');
																}
															}
														}
													}
												}
												/* Sending notification to user regarding booking confirmation -- End */
												
												$drop_location = 0;
												$drop_loc = '';$drop_lat = '';$drop_lon = '';
												if($checkRide->row()->booking_information['drop']['location']!=''){
													$drop_location = 1;
													$drop_loc = $checkRide->row()->booking_information['drop']['location'];
													$drop_lat = $checkRide->row()->booking_information['drop']['latlong']['lat'];
													$drop_lon = $checkRide->row()->booking_information['drop']['latlong']['lon'];
												}
												$user_profile['drop_location'] = (string)$drop_location;
												$user_profile['drop_loc'] = (string)$drop_loc;
												$user_profile['drop_lat'] = (string)$drop_lat;
												$user_profile['drop_lon'] = (string)$drop_lon;
												
												
												/* if ($ride_id != '') {
													$checkInfo = $ci->app_model->get_all_details(TRACKING, array('ride_id' => $ride_id));
												
													$latlng = $driver_lat . ',' . $driver_lon;
													$gmap = file_get_contents("https://maps.googleapis.com/maps/api/geocode/json?latlng=" . $latlng . "&sensor=false".$ci->data['google_maps_api_key']);
													$mapValues = json_decode($gmap)->results;
													if(!empty($mapValues)){
														$formatted_address = $mapValues[0]->formatted_address;
														$cuurentLoc = array('timestamp' => MongoDATE(time()),
															'locality' => (string) $formatted_address,
															'location' => array('lat' => floatval($driver_lat), 'lon' => floatval($driver_lon))
														);
														
														if ($checkInfo->num_rows() > 0) {
															$ci->app_model->simple_push(TRACKING, array('ride_id' => (string) $ride_id), array('steps' => $cuurentLoc));
														} else {
															$ci->app_model->simple_insert(TRACKING, array('ride_id' => (string) $ride_id));
															$ci->app_model->simple_push(TRACKING, array('ride_id' => (string) $ride_id), array('steps' => $cuurentLoc));
														}
													}
												} */
												
												
												if (empty($user_profile)) {
													$user_profile = json_decode("{}");
												}
												
												$returnArr['status'] = '1';
												$returnArr['response'] = array('upcoming_ride_status' => $upcoming_ride_status,'user_profile' => $user_profile, 'message' => $ci->format_string("Ride Accepted", "ride_accepted"));
												
												/** req history update  **/
												if($ack_id != ''){
													$dataArr = array('status' => 'accepted','accepted_time' => MongoDATE(time()));
													$ci->app_model->update_details(RIDE_REQ_HISTORY,$dataArr,array('_id' => MongoID($ack_id)));
												}
												/*******************/

												$returnArr['status'] = '1';
												$returnArr['response'] = array('upcoming_ride_status' => $upcoming_ride_status,'user_profile' => $user_profile, 'message' => $ci->format_string("Ride Accepted", "ride_accepted"));
												
												if(isset($checkDriver->row()->last_online_time)){
													$dataArr = array('last_accept_time' => MongoDATE(time()));
													$ci->app_model->update_details(DRIVERS, $dataArr, array('_id' => MongoID($driver_id)));
													update_mileage_system($driver_id,MongoEPOCH($checkDriver->row()->last_online_time),'free-roaming',$distance,$ci->data['d_distance_unit'],$ride_id);
												}
												
											} else {
												$returnArr['ride_view'] = 'home';
												$returnArr['response'] = $ci->format_string('you are too late, this ride is booked.', 'you_are_too_late_to_book_this_ride');
											}
										} else {
											$returnArr['ride_view'] = 'home';
											$returnArr['response'] = $ci->format_string('Sorry ! We can not fetch information', 'cannot_fetch_location_information_in_map');								
										}
									}else{
										
										$returnArr['ride_view'] = 'home';
										$returnArr['response'] = $ci->format_string('You cannot accept this ride.', 'you_cannot_accept_this_ride');
									}
								}else{
									$ride_status = $checkRide->row()->ride_status;
									if($ride_status=="Cancelled"){
										$returnArr['ride_view'] = 'home';
										$returnArr['response'] = $ci->format_string('Already this ride has been cancelled', 'already_ride_cancelled');
									}else if($checkRide->row()->driver['id'] == $driver_id){
										$returnArr['ride_view'] = 'detail';
										 $returnArr['response'] = $ci->format_string('Ride Accepted', 'ride_accepted');
									}else{
										$returnArr['ride_view'] = 'home';
										$returnArr['response'] = $ci->format_string('You cannot accept this ride.', 'you_cannot_accept_this_ride');
									}
								}
							} else {
								$returnArr['ride_view'] = 'home';
								$returnArr['response'] = $ci->format_string('you are too late, this ride is booked.', 'you_are_too_late_to_book_this_ride');
							}
						} else {
							$returnArr['ride_view'] = 'home';
							$returnArr['response'] = $ci->format_string("This ride is unavailable", "ride_unavailable");
						}
					} else {
						$returnArr['response'] = $ci->format_string("Driver not found", "driver_not_found");
					}
				}else{
					$returnArr['response'] = $ci->format_string("Some Parameters Missing", "some_parameters_missing");
				}				
			} catch (MongoException $ex) {
				$returnArr['response'] = $ci->format_string("Error in connection", "error_in_connection");
			}

			$json_encode = json_encode($returnArr, JSON_PRETTY_PRINT);
			echo $ci->cleanString($json_encode);
		}
	}
	
	/**
	*
	*	This function will update the pickup location reached state
	*	Param @locationInfo as Array
	*	Holds all the location arrived information Eg. Driver Id, ride id, latitude, longtitude
	*
	**/	
	if ( ! function_exists('pickup_location_arrived')){
		function pickup_location_arrived($locationInfo= array()) {
			$ci =& get_instance();
			#echo "<pre>"; print_r($locationInfo); die;
			$returnArr['status'] = '0';
			$returnArr['response'] = '';
			$returnArr['ride_view'] = 'stay';
			
			try {
				if(array_key_exists("driver_id",$locationInfo)) $driver_id =  trim($locationInfo['driver_id']); else $driver_id = "";
				if(array_key_exists("ride_id",$locationInfo)) $ride_id =  trim($locationInfo['ride_id']); else $ride_id = "";
				if(array_key_exists("driver_lat",$locationInfo)) $driver_lat =  trim($locationInfo['driver_lat']); else $driver_lat = "";
				if(array_key_exists("driver_lon",$locationInfo)) $driver_lon =  trim($locationInfo['driver_lon']); else $driver_lon = "";
				#echo "<pre>"; print_r($locationInfo); die;
				if ($driver_id!="" && $ride_id!="" && $driver_lat!="" && $driver_lon!="") {
					$checkDriver = $ci->app_model->get_selected_fields(DRIVERS, array('_id' => MongoID($driver_id)), array('_id', 'driver_name','first_name', 'image', 'avg_review', 'email', 'dail_code', 'mobile_number', 'driver_commission','company_id', 'dispatcher_id','last_online_time','ride_type','mode','vehicle_color','vehicle_maker','vehicle_number','vehicle_model','upcoming_ride'));
					#echo "<pre>"; print_r($checkDriver->row()); die;
					if ($checkDriver->num_rows() == 1) {
						$checkRide = $ci->app_model->get_all_details(RIDES, array('ride_id' => $ride_id));
						if ($checkRide->num_rows() == 1) {
							if ($checkRide->row()->ride_status == 'Confirmed') {
								
								$allowAccept=TRUE;
                                if (!isset($checkRide->row()->pool_ride) && (!isset($checkDriver->row()->upcoming_ride) || (isset($checkDriver->row()->upcoming_ride) || $checkDriver->row()->upcoming_ride!=''))){
                                    if ( $checkDriver->row()->upcoming_ride==$ride_id) { 
                                        if (isset($checkDriver->row()->duty_ride) && $checkDriver->row()->duty_ride!='' && ($checkDriver->row()->duty_ride!=$ride_id)) {
                                            $con = array('ride_id' => $checkDriver->row()->duty_ride, 
                                                        '$or' => array(array("ride_status" => 'Confirmed'),
                                                                            array("ride_status" => 'Arrived'),
                                                                            array("ride_status" => 'Onride'),
                                                                            array("ride_status" => 'Finished','payment_method' => 'cash'),
                                                                            array('drs'=>'0'),
                                                                            #array("ride_status" => 'Completed','drs'=>'0'),
                                                                        )
                                            );
                                            $checkdutyRide = $ci->app_model->get_all_details(RIDES,$con);
                                            if($checkdutyRide->num_rows() > 0){
                                                $allowAccept=FALSE;
                                            }
                                        }
                                    }
                                }
							if($allowAccept){
								
								/* Update the ride information */
								$rideDetails = array('ride_status' => 'Arrived',
									'booking_information.arrived_location' => array('lon' => floatval($driver_lon), 'lat' => floatval($driver_lat)),
									'history.arrived_time' => MongoDATE(time())
								);
								$ci->app_model->update_details(RIDES, $rideDetails, array('ride_id' => $ride_id));
								
								$driver_lat = 0;
								$driver_lon = 0;
								if(isset($checkDriver->row()->loc)){
									if(is_array($checkDriver->row()->loc)){
										$driver_lat = floatval($checkDriver->row()->loc['lat']);
										$driver_lon = floatval($checkDriver->row()->loc['lon']);
									}
								}
								
								/* Notification to user about driver reached his location */
								$user_id = $checkRide->row()->user['id'];
								$userVal = $ci->app_model->get_selected_fields(USERS, array('_id' => MongoID($user_id)), array('_id', 'user_name', 'email', 'image', 'avg_review', 'phone_number', 'country_code', 'push_type', 'push_notification_key'));
								if (isset($userVal->row()->push_type)) {
									if ($userVal->row()->push_type != '') {
										$dr_name = ucfirst($checkDriver->row()->first_name);
										$message = $dr_name.' has arrived at your location';
										
										$veh_info = $checkDriver->row()->vehicle_color.' '.$checkDriver->row()->vehicle_maker.' '.$checkDriver->row()->vehicle_model.' ('.$checkDriver->row()->vehicle_number.').';
										$push_title = 'Driver Is In A '.$veh_info;
										
										$message = $dr_name." ".$ci->format_string("is at your location. Driver will wait for 4 minutes. Don't forget to verify the license plate","driver_arrived_confirm_vehcile_no", '', 'user', (string)$userVal->row()->_id);
										
										$options = array('ride_id' => (string) $ride_id, 'user_id' => (string) $user_id, 'driver_lat' => (string) $driver_lat, 'driver_lon' => (string) $driver_lon);
										if ($userVal->row()->push_type == 'ANDROID') {
											if (isset($userVal->row()->push_notification_key['gcm_id'])) {
												if ($userVal->row()->push_notification_key['gcm_id'] != '') {
													$ci->sendPushNotification(array($userVal->row()->push_notification_key['gcm_id']), $message, 'cab_arrived', 'ANDROID', $options, 'USER',$push_title);
												}
											}
										}
										if ($userVal->row()->push_type == 'IOS') {
											if (isset($userVal->row()->push_notification_key['ios_token'])) {
												if ($userVal->row()->push_notification_key['ios_token'] != '') {
													$ci->sendPushNotification(array($userVal->row()->push_notification_key['ios_token']), $message, 'cab_arrived', 'IOS', $options, 'USER',$push_title);
												}
											}
										}
									}
								}
								$ci->sms_model->sms_on_driver_arraival($ride_id);
								$returnArr['status'] = '1';
								get_trip_information($driver_id,$ride_id); exit;
							  } else {
								$returnArr['response'] = $ci->format_string("Complete previous ride,then continue this ride.", "complete_previous_ride");
							}
							} else {
								if($checkRide->row()->ride_status == 'Arrived'){
									$returnArr['status'] = '1';
									get_trip_information($driver_id,$ride_id); exit;
								}else{
									$returnArr['ride_view'] = 'detail';
									$returnArr['response'] = $ci->format_string('Ride Cancelled', 'ride_cancelled');
								}
							}
						} else {
							$returnArr['ride_view'] = 'home';
							$returnArr['response'] = $ci->format_string("This ride is unavailable", "ride_unavailable");
						}
					} else {
						$returnArr['response'] = $ci->format_string("Driver not found", "driver_not_found");
					}
				}else{
					$returnArr['response'] = $ci->format_string("Some Parameters Missing", "some_parameters_missing");
				}				
			} catch (MongoException $ex) {
				$returnArr['response'] = $ci->format_string("Error in connection", "error_in_connection");
			}

			$json_encode = json_encode($returnArr, JSON_PRETTY_PRINT);
			echo $ci->cleanString($json_encode);
		}
	}
		
	/**
	*
	*	This function will update the begin trip details
	*	Param @locationInfo as Array
	*	Holds all the location arrived information Eg. Driver Id, ride id, latitude, longtitude
	*
	**/	
	if ( ! function_exists('begin_the_trip')){
		function begin_the_trip($beginInfo= array()) {
			$ci =& get_instance();
			
			$returnArr['status'] = '0';
			$returnArr['response'] = '';
			$returnArr['ride_view'] = 'stay';
			
			try {
				if(array_key_exists("driver_id",$beginInfo)) $driver_id =  trim($beginInfo['driver_id']); else $driver_id = "";
				if(array_key_exists("ride_id",$beginInfo)) $ride_id =  trim($beginInfo['ride_id']); else $ride_id = "";
				if(array_key_exists("pickup_lat",$beginInfo)) $pickup_lat =  trim($beginInfo['pickup_lat']); else $pickup_lat = "";
				if(array_key_exists("pickup_lon",$beginInfo)) $pickup_lon =  trim($beginInfo['pickup_lon']); else $pickup_lon = "";
				
				if(array_key_exists("drop_lat",$beginInfo)) $drop_lat =  trim($beginInfo['drop_lat']); else $drop_lat = "";
				if(array_key_exists("drop_lon",$beginInfo)) $drop_lon =  trim($beginInfo['drop_lon']); else $drop_lon = "";
				
				if(array_key_exists("distance",$beginInfo)) $distance =  floatval($beginInfo['distance']); else $distance = 0;
				
				if(array_key_exists("no_of_seat",$beginInfo)) $no_of_seat =  floatval($beginInfo['no_of_seat']); else $no_of_seat = "";	
				
				if(array_key_exists("begin_meter",$beginInfo)) $begin_meter =  floatval($beginInfo['begin_meter']); else $begin_meter = "";		
				
				if ($driver_id!="" && $ride_id!="" && $pickup_lat!="" && $pickup_lon!="" && $drop_lat!="" && $drop_lon!="" && $distance>=0){
					$checkDriver = $ci->app_model->get_selected_fields(DRIVERS, array('_id' => MongoID($driver_id)), array('email','last_accept_time','upcoming_ride'));
					if ($checkDriver->num_rows() == 1) { 
						$checkRide = $ci->app_model->get_all_details(RIDES, array('ride_id' => $ride_id));
						if ($checkRide->num_rows() == 1) {
							$meterChk = TRUE;
							if(isset($checkRide->row()->outstation_type) && $checkRide->row()->outstation_type != ''){
								if($begin_meter == '') $meterChk = FALSE;
							}
							if($meterChk){
								#echo "asdf"; die;
									$service_id = $checkRide->row()->booking_information['service_id'];
									$est_travel_duration = $checkRide->row()->booking_information['est_travel_duration'];
									$doBegin = TRUE;
									if ($service_id==POOL_ID) {
										$doBegin = FALSE;
										
										if($no_of_seat == ''){
											$no_of_seat = 1;
											if(isset($checkRide->row()->no_of_seat)){
												$no_of_seat = $checkRide->row()->no_of_seat;
											}
										}
										
										if($no_of_seat==1 || $no_of_seat==2) $doBegin = TRUE;
									}
									if($doBegin == FALSE){
										$returnArr['response'] = $ci->format_string("Confirm number of seats", "confirm_seats");
									}else{
										#echo $checkRide->row()->ride_status; die;
										if ($checkRide->row()->ride_status == 'Arrived') {
											$latlng = $pickup_lat . ',' . $pickup_lon;
											$gmap = file_get_contents("https://maps.googleapis.com/maps/api/geocode/json?latlng=" . $latlng . "&sensor=false".$ci->data['google_maps_api_key']);
											$map_result = json_decode($gmap);
											$mapValues = $map_result->results;
											
											$drop_latlng = $drop_lat . ',' . $drop_lon;
											$urldrop = "https://maps.googleapis.com/maps/api/geocode/json?latlng=" . $drop_latlng . "&sensor=false".$ci->data['google_maps_api_key'];
											$gmap_drop = file_get_contents($urldrop);
											$drop_result = json_decode($gmap_drop);
											$mapValues_drop = $drop_result->results;
											if(!empty($mapValues) && !empty($mapValues_drop)){
												$formatted_address = $mapValues[0]->formatted_address;
												$drop_address = $mapValues_drop[0]->formatted_address;
												
												/* Update the ride information */
												$curr_time = time();
												$rideDetails = array('ride_status' => 'Onride',
													'booking_information.pickup_date' => MongoDATE($curr_time),
													'booking_information.pickup.location' => (string) $formatted_address,
													'booking_information.pickup.latlong' => array('lon' => floatval($pickup_lon),
														'lat' => floatval($pickup_lat)
													),
													// 'booking_information.drop.location' => (string) $drop_address,
													// 'booking_information.drop.latlong' => array('lon' => floatval($drop_lon),
														// 'lat' => floatval($drop_lat)
													// ),
													'booking_information.begin_meter' => (string) $begin_meter,
													'history.begin_ride' => MongoDATE($curr_time)
												);
												#echo "<pre>"; print_r($rideDetails); die;
												if ($service_id==POOL_ID) {
													$est_amount = 0;
													if($no_of_seat==1){
														$est_amount = $checkRide->row()->pool_fare["passanger"];
													}else if($no_of_seat==2){
														$est_amount = $checkRide->row()->pool_fare["co_passanger"];
													}
													#$rideDetails["pool_fare.est"] = (string)$est_amount;
													$rideDetails["no_of_seat"] = (string)$no_of_seat;
												}
												$ci->app_model->update_details(RIDES, $rideDetails, array('ride_id' => $ride_id));
												
												$user_id = $checkRide->row()->user['id'];
												
												/* Ride Hailing Begin Send SMS to User*/
												if(isset($checkRide->row()->ride_type) && $checkRide->row()->ride_type == 'hailing'){
												  $ci->sms_model->ride_hailing_sms_to_user($ride_id,'begin');
												}
												
												/* Notification to user about begin trip  */
												$userVal = $ci->app_model->get_selected_fields(USERS, array('_id' => MongoID($user_id)), array('_id', 'user_name', 'email', 'image', 'avg_review', 'phone_number', 'country_code', 'push_type', 'push_notification_key'));
												if (isset($userVal->row()->push_type)) {
													if ($userVal->row()->push_type != '') {
														$message = $ci->format_string("Your ".$ci->config->item('email_title')." trip has started", "your_trip_has_been_started",FALSE, 'user', (string)$userVal->row()->_id);
														$options = array('ride_id' => (string) $ride_id, 'user_id' => (string) $user_id, 'drop_lat' => (string) $drop_lat, 'drop_lon' => (string) $drop_lon, 'pickup_lat' => (string) $pickup_lat, 'pickup_lon' => (string) $pickup_lon,'drop_address'=>(string) $drop_address);
														if ($userVal->row()->push_type == 'ANDROID') {
															if (isset($userVal->row()->push_notification_key['gcm_id'])) {
																if ($userVal->row()->push_notification_key['gcm_id'] != '') {
																	$ci->sendPushNotification(array($userVal->row()->push_notification_key['gcm_id']), $message, 'trip_begin', 'ANDROID', $options, 'USER');
																}
															}
														}
														if ($userVal->row()->push_type == 'IOS') {
															if (isset($userVal->row()->push_notification_key['ios_token'])) {
																if ($userVal->row()->push_notification_key['ios_token'] != '') {
																	$ci->sendPushNotification(array($userVal->row()->push_notification_key['ios_token']), $message, 'trip_begin', 'IOS', $options, 'USER');
																}
															}
														}
													}
												}
												
												 $dataArr = array('loc' => array('lon' => floatval($pickup_lon),
																											'lat' => floatval($pickup_lat)),
																				 'last_active_time'=>MongoDATE(time()),
												);
												$est_end_time = time()+($est_travel_duration*60);
												$dataArr['est_end_time'] = MongoDATE($est_end_time);
												if(isset($checkDriver->row()->last_accept_time)){
													$dataArr['last_begin_time'] = MongoDATE(time());
													$ci->app_model->update_details(DRIVERS, $dataArr, array('_id' => MongoID($driver_id)));
													update_mileage_system($driver_id,MongoEPOCH($checkDriver->row()->last_accept_time),'customer-pickup',$distance,$ci->data['d_distance_unit'],$ride_id);
												} else {
													$ci->app_model->update_details(DRIVERS, $dataArr, array('_id' => MongoID($driver_id)));
												}
												
												/* Tailling Ride Updates */
													if (isset($checkDriver->row()->upcoming_ride) || $checkDriver->row()->upcoming_ride!='') {
														if ( $checkDriver->row()->upcoming_ride==$ride_id) { 
																$ci->app_model->update_details(DRIVERS, array('upcoming_ride'=>''),array('_id' => MongoID($driver_id)));
														}
													}
											    /* Tailling Ride Updates */
												
												get_trip_information($driver_id,$ride_id); exit;
												
											}else{
												$returnArr['response'] = $ci->format_string('Sorry ! We can not fetch information', 'cannot_fetch_location_information_in_map');
											}
										} else {
											if($checkRide->row()->ride_status == 'Onride'){
												/* $returnArr['ride_view'] = 'next';
												$returnArr['response'] = $ci->format_string('Already Ride Started', 'already_ride_started'); */
												get_trip_information($driver_id,$ride_id); exit;
											}else{
												/* $returnArr['ride_view'] = 'detail';
												$returnArr['response'] = $ci->format_string('Ride Cancelled', 'ride_cancelled'); */
												get_trip_information($driver_id,$ride_id); exit;
											}
										}
									} 
								} else {
                                    $returnArr['response'] = $ci->format_string("Please enter the starting meter of the vehicle");
                                }
						} else {
							$returnArr['response'] = $ci->format_string("Invalid Ride", "invalid_ride");
						}
					} else {
						$returnArr['response'] = $ci->format_string("Driver not found", "driver_not_found");
					}					
				}else{
					$returnArr['response'] = $ci->format_string("Some Parameters Missing", "some_parameters_missing");
				}				
			} catch (MongoException $ex) {
				$returnArr['response'] = $ci->format_string("Error in connection", "error_in_connection");
			}
			$json_encode = json_encode($returnArr, JSON_PRETTY_PRINT);
			echo $ci->cleanString($json_encode);
		}
	}
	
	/**
	*
	*	This function will update the begin trip details
	*	Param @locationInfo as Array
	*	Holds all the location arrived information Eg. Driver Id, ride id, latitude, longtitude
	*
	**/	
	if ( ! function_exists('finish_the_trip')){
		function finish_the_trip($endInfo= array()) {
			$ci =& get_instance();
			$sour = "";
			
			$returnArr['status'] = '0';
			$returnArr['response'] = '';
			$returnArr['ride_view'] = 'stay';
			
			try {				
				#echo "<pre>"; print_r($endInfo); die;
				if(array_key_exists("sour",$endInfo)) $sour =  trim($endInfo['sour']); else $sour = "";
				if(array_key_exists("driver_id",$endInfo)) $driver_id =  trim($endInfo['driver_id']); else $driver_id = "";
				if(array_key_exists("ride_id",$endInfo)) $ride_id =  trim($endInfo['ride_id']); else $ride_id = "";
				if(array_key_exists("drop_lat",$endInfo)) $drop_lat =  trim($endInfo['drop_lat']); else $drop_lat = "";
				if(array_key_exists("drop_lon",$endInfo)) $drop_lon =  trim($endInfo['drop_lon']); else $drop_lon = "";
				if(array_key_exists("drop_time",$endInfo)) $drop_time =  trim($endInfo['drop_time']); else $drop_time = "";
				
				if(array_key_exists("distance",$endInfo)) $distance =  floatval($endInfo['distance']); else $distance = "";
				$device_distance = $distance;
				
				if(array_key_exists("end_meter",$endInfo)) $end_meter =  trim($endInfo['end_meter']); else $end_meter = "";
				
				if(array_key_exists("wait_time_frame",$endInfo)) $wait_time_frame =  trim($endInfo['wait_time_frame']); else $wait_time_frame = "";
								
				if(array_key_exists("travel_history",$endInfo)) $travel_history =  trim($endInfo['travel_history']); else $travel_history = "";
				
				if(array_key_exists("hailing_fare",$endInfo)) $hailing_total_fare =  trim($endInfo['hailing_fare']); else $hailing_total_fare = "";
				
				if(array_key_exists("hailing_distance",$endInfo)) $hailing_distance =  round(trim($endInfo['hailing_distance']),2); else $hailing_distance = "";
								
				if(array_key_exists("hailing_ride_time",$endInfo)) $hailing_ride_time =  trim($endInfo['hailing_ride_time']); else $hailing_ride_time = "";
				if(array_key_exists("end_status",$endInfo)) $end_status =  trim($endInfo['end_status']); else $end_status = "";
				
				# string lat;log;time,lat;lon;time,...
				
				$wait_time = 0;
                if ($wait_time_frame != '') {
                    $wt = @explode(':', $wait_time_frame);
                    $h = 0; $m = 0; $s = 0;
					if(isset($wt[0])) $h = intval($wt[0]);
                    if(isset($wt[1])) $m = intval($wt[1]);
                    if(isset($wt[2])) $s = intval($wt[2]);
					 
                    if ($h > 0) {
                        $wait_time = $h * 60;
                    }
                    if ($m > 0) {
                        $wait_time = $wait_time + ($m);
                    }
					if ($s > 0) {
                        $wait_time = $wait_time + 1;
                    }
                }
				if(!is_numeric($wait_time)){
					$wait_time = 0;
				}
				
				if ($driver_id!="" && $ride_id!="" && $drop_lat!="" && $drop_lon!=""){
					$checkDriver = $ci->app_model->get_selected_fields(DRIVERS, array('_id' => MongoID($driver_id)), array('email','last_begin_time','push_notification','has_destination','duty_ride','upcoming_ride'));
					if ($checkDriver->num_rows() == 1) {
						$checkRide = $ci->app_model->get_all_details(RIDES, array('ride_id' => $ride_id));
						
						if ($checkRide->num_rows() == 1) {
							$meterChk = TRUE;
							if(isset($checkRide->row()->outstation_type) && $checkRide->row()->outstation_type != ''){
								if($end_meter == '') $meterChk = FALSE;
							}
							if($meterChk){
								
								$start_meter = '';
								if(isset($checkRide->row()->booking_information['begin_meter']) && $checkRide->row()->booking_information['begin_meter'] != ''){
									$start_meter = $checkRide->row()->booking_information['begin_meter'];
								}
								$meterValueChk = TRUE;
								if(isset($checkRide->row()->ride_type) && $checkRide->row()->ride_type == 'outstation' && (isset($start_meter) && $start_meter !='') && (isset($end_meter) && $end_meter !='') && $end_meter <$start_meter){
									$meterValueChk = FALSE;
								}
								if($meterValueChk){
									$user_id = (string)$checkRide->row()->user['id'];
									/*********** Calculate Stop's Waiting Time Customization  ********/
									$stops_avail_status = 'No';
									$stops_count = 0;
									$stops_wait_time = 0;
									$stops = array();
									if(isset($checkRide->row()->booking_information['stops']) && !empty($checkRide->row()->booking_information['stops'])){
										$stops = $checkRide->row()->booking_information['stops'];
										foreach($stops as $vals){
											if(isset($vals['status']) && $vals['status'] == 'Departed'){
												if(isset($vals['arrived_time']) && $vals['departed_time']){
													$stops_count++; 
													$stops_wait_time+= ceil((MongoEPOCH($vals['departed_time']) - MongoEPOCH($vals['arrived_time']))/60);
												}
											}
										}
									}
									
									$free_stops_wait_time = 0;
									if(isset($checkRide->row()->fare_breakup['stops_free_wait_time'])){
										$free_stops_wait_time = $checkRide->row()->fare_breakup['stops_free_wait_time'];
									}
									
									if($stops_count > 0){
										$stops_avail_status = 'Yes';
										$stops_wait_time = $stops_wait_time - ($free_stops_wait_time * $stops_count);
										if($stops_wait_time < 0) $stops_wait_time = 0;
									}
									$wait_time = $wait_time + $stops_wait_time;
									/*****************************************************************/
									
									$pickup_time = MongoEPOCH($checkRide->row()->booking_information['pickup_date']);
									#echo "a"; die;
									#echo 'AFSD'.$sour; die;
									if($sour == "w" && $drop_time != ''){
										$drop_time = strtotime($drop_time);
									} else {
										$drop_time = time();
									}
									$interval  = abs($drop_time - $pickup_time);
									$ride_time_min = abs($interval / 60);
									$ride_end_time = $drop_time; // Trip Timestamp
									$ride_wait_time = abs($wait_time*60);	// in Seconds
									$ride_total_time_min = ceil($ride_time_min - $wait_time);
									if($ride_total_time_min<1) $ride_total_time_min = 1;
									$duration = $ride_total_time_min;
									
									$distance_unit = $ci->data['d_distance_unit'];
									if(isset($checkRide->row()->fare_breakup['distance_unit'])){
										$distance_unit = $checkRide->row()->fare_breakup['distance_unit'];
									}
									
                                $range_id='';
								if(isset($checkRide->row()->ride_type) && $checkRide->row()->ride_type == 'outstation' && $end_meter != ''){
									
									$distance = $end_meter - $start_meter;
									if($distance < 0) $distance = 0;
									$math_ext_distance = $distance;
									$distanceKM = $distance;
								} else {
									#echo "as"; die;
									$math_ext_distance = 0;
									if($travel_history!="") $math_ext_distance = get_distance_from_latlong($travel_history,$ride_id);	
									$change_distance='No';
									
									$distance_unit = $ci->data['d_distance_unit'];
									if(isset($checkRide->row()->fare_breakup['distance_unit'])){
										$distance_unit = $checkRide->row()->fare_breakup['distance_unit'];
									}
									
									$math_ext_distance = floatval($math_ext_distance);
									$googleDistance = 0;
									$pickup_lat=$checkRide->row()->booking_information["pickup"]["latlong"]["lat"];
									$pickup_lon=$checkRide->row()->booking_information["pickup"]["latlong"]["lon"];
									$pickuplatlng = $pickup_lat.','.$pickup_lon;
									$drlatlng = $drop_lat.','.$drop_lon;
									$d_source='';
									
									if($distance_unit == 'mi'){
										$math_ext_distance = round(($math_ext_distance / 1.609344),2);
										$device_distance = round(($device_distance / 1.609344),2);
									}
									
									if($sour != "w"){
										
										if($device_distance > 0) {
											$diff = 0;
											if($diff >2) {
												$gURL = 'https://maps.googleapis.com/maps/api/directions/json?origin='.$pickuplatlng.'&destination='.$drlatlng. '&alternatives=true&sensor=false&mode=driving'.$ci->data['google_maps_api_key'];
												#echo "in<pre>"; print_r($gURL); die;
												$gmap = file_get_contents($gURL); 
												$map_values = json_decode($gmap);
												$routes = $map_values->routes;
												if(!empty($routes)){
													#usort($routes, create_function('$a,$b', 'return intval($a->legs[0]->distance->value) - intval($b->legs[0]->distance->value);'));
													$min_distance = $routes[0]->legs[0]->distance->text;
													$min_duration = round($routes[0]->legs[0]->duration->value/60);
													if (preg_match('/km/',$min_distance)){
														$return_distance = 'km';
													}else if (preg_match('/mi/',$min_distance)){
														$return_distance = 'mi';
													}else if (preg_match('/m/',$min_distance)){
														$return_distance = 'm';
													}else if (preg_match('/ft/',$min_distance)){
														 $return_distance = 'ft';
													} else {
														$return_distance = 'km';
													}
													
													$apxdistance = floatval(str_replace(',','',$min_distance));
													if($distance_unit!=$return_distance){
														if($distance_unit=='km' && $return_distance=='mi'){
															$apxdistance = $apxdistance * 1.60934;
														} else if($distance_unit=='mi' && $return_distance=='km'){
															$apxdistance = $apxdistance * 0.621371;
														} else if($distance_unit=='km' && $return_distance=='m'){
															$apxdistance = $apxdistance / 1000;
														} else if($distance_unit=='mi' && $return_distance=='m'){
															$apxdistance = $apxdistance * 0.00062137;
														} else if($distance_unit=='km' && $return_distance=='ft'){
															$apxdistance = $apxdistance * 0.0003048;
														} else if($distance_unit=='mi' && $return_distance=='ft'){
															$apxdistance = $apxdistance * 0.00018939;
														}
													}
													$googleDistance = floatval(round($apxdistance,2));
													$distance=$googleDistance;
													$d_source='google';
												}
											} else {
												if($math_ext_distance > $device_distance) {
													$distance = $math_ext_distance;
													$d_source = 'gps';
												} else {
													$distance = $device_distance;
													$d_source = 'gps';
												}
											}
										} else {
											$gURL = 'https://maps.googleapis.com/maps/api/directions/json?origin='.$pickuplatlng.'&destination='.$drlatlng. '&alternatives=true&sensor=false&mode=driving'.$ci->data['google_maps_api_key'];
											#echo "out<pre>"; print_r($gURL); die;
												$gmap = file_get_contents($gURL); 
												$map_values = json_decode($gmap);
												$routes = $map_values->routes;
												if(!empty($routes)){
													#usort($routes, create_function('$a,$b', 'return intval($a->legs[0]->distance->value) - intval($b->legs[0]->distance->value);'));
													$min_distance = $routes[0]->legs[0]->distance->text;
													$min_duration = round($routes[0]->legs[0]->duration->value/60);
													if (preg_match('/km/',$min_distance)){
														$return_distance = 'km';
													}else if (preg_match('/mi/',$min_distance)){
														$return_distance = 'mi';
													}else if (preg_match('/m/',$min_distance)){
														$return_distance = 'm';
													}else if (preg_match('/ft/',$min_distance)){
														 $return_distance = 'ft';
													} else {
														$return_distance = 'km';
													}
													
													$apxdistance = floatval(str_replace(',','',$min_distance));
													if($distance_unit!=$return_distance){
														if($distance_unit=='km' && $return_distance=='mi'){
															$apxdistance = $apxdistance * 1.60934;
														} else if($distance_unit=='mi' && $return_distance=='km'){
															$apxdistance = $apxdistance * 0.621371;
														} else if($distance_unit=='km' && $return_distance=='m'){
															$apxdistance = $apxdistance / 1000;
														} else if($distance_unit=='mi' && $return_distance=='m'){
															$apxdistance = $apxdistance * 0.00062137;
														} else if($distance_unit=='km' && $return_distance=='ft'){
															$apxdistance = $apxdistance * 0.0003048;
														} else if($distance_unit=='mi' && $return_distance=='ft'){
															$apxdistance = $apxdistance * 0.00018939;
														}
													}
													$googleDistance = floatval(round($apxdistance,2));
													$distance=$googleDistance;
												}
												$distance=$distance;
												$d_source='google';
										}
									}	
									
									$distanceKM = $math_ext_distance;
									
									if(isset($checkRide->row()->ride_type) && $checkRide->row()->ride_type == 'hailing' && $hailing_distance != ''){
										if($hailing_distance > 0){
											$distance = $distanceKM = $hailing_distance;
										} else {
											$distance = $distanceKM = 0.0;
										}
									}
									
										/*********Distance Fare Details******/
										$category=$checkRide->row()->booking_information['service_id'];
										$location_details=$ci->app_model->get_selected_fields(LOCATIONS,array('_id'=>MongoID($checkRide->row()->location['id'])),array('distance_range','fare'));
										if($location_details->num_rows() > 0){
											if(isset($location_details->row()->distance_range) && !empty($location_details->row()->distance_range)){
												foreach($location_details->row()->distance_range as $row){
													if($distance>=$row['from'] && $distance <=$row['to']){
														$range_id=$row['id'];
													}
												}
											}
										}
										/*********Distance Fare Details******/
									}
										/* Mileage reward to user */	
										$mileage_distance = $mileage_point = '';
										if(!empty($checkRide->row()->mileage_reward)){
											$mileage_distance = $checkRide->row()->mileage_reward['mileage_distance'];
											$mileage_point = $checkRide->row()->mileage_reward['mileage_point'];
										}
										$checkUser = $ci->app_model->get_all_details(USERS, array('_id' => MongoID($user_id)));
										$available_points=$checkUser->row()->reward_points;
										$reward_points = 0;
										$txn_time = time();
										if($mileage_distance != '' && $mileage_point != ''){
											$reward_points = round(($distance/$mileage_distance)*$mileage_point);
											$available_points =$available_points+$reward_points;
											# insert credit history
											$initialAmt = array('type' => 'CREDIT',
												'user_id' => MongoID($user_id),
												'ride_id' => $ride_id,
												'credit_type' => 'mileage_reward',
												'ref_id' => 'user',
												'distance' => $distance ,
												'trans_date' => MongoDATE($txn_time),
												'trans_id' => (string)$txn_time,
												'reward_points'=> $reward_points,
												'available_points'=> $available_points,
												
											);
											$ci->app_model->simple_insert(USER_REWARD_POINTS,$initialAmt);
										
											$ci->app_model->update_reward_points((string)$checkRide->row()->user['id'],floatval($reward_points),'CREDIT');
												
										}
									/* Mileage reward to user */
									if($checkRide->row()->ride_status=='Onride'){
										$currency = $checkRide->row()->currency;
										$grand_fare = 0;
										$total_fare = 0;
										$free_ride_time = 0;
										$total_base_fare = 0;
										$total_distance_charge = 0;
										$total_ride_charge = 0;
										$total_waiting_charge = 0;
										$total_peak_time_charge = 0;
										$total_night_time_charge = 0;
										$total_tax = 0;
										$coupon_discount = 0;
										
										$latlng = $drop_lat . ',' . $drop_lon;
										$gmap = file_get_contents("https://maps.googleapis.com/maps/api/geocode/json?latlng=" . $latlng . "&sensor=false".$ci->data['google_maps_api_key']);
										$map_values = json_decode($gmap);
										$mapValues = $map_values->results;
										#echo "<pre>"; print_r($mapValues); die;
										if(!empty($mapValues)){
											$dropping_address = $mapValues[0]->formatted_address;
											if(($pickup_time+$ride_wait_time)<=($ride_end_time+100)){
												$trip_type = "Normal";
												if(isset($checkRide->row()->pool_ride)){
													if($checkRide->row()->pool_ride=="Yes"){
														$trip_type = "Share";
													}
												}
												
												$share_booking_fee = 0;
												
												$parking_charge = 0;
												$parking_id = $checkRide->row()->parking_id;
												if($parking_id!=''){
													$parkingDetails = $ci->app_model->get_all_details(PARKING,array('_id' => MongoID($parking_id)));
													if($parkingDetails->num_rows()>0){
														if(isset($parkingDetails->row()->parking_fee) && $parkingDetails->row()->parking_fee!=''){
															$parking_charge = floatval($parkingDetails->row()->parking_fee);
															if($parking_charge<=0) $parking_charge = 0;
														}
													}
												}
												/*********  outstation fare calculation *********/
													$driverAllowance = 0; $nightAllowance = 0; 
													$isOutstation = FALSE;
													
													if(isset($checkRide->row()->ride_type) && $checkRide->row()->ride_type == 'outstation'){
														
														$ci->load->helper('outstation_helper');
														$outstationFare = get_outstation_fare($ride_id,$checkRide);
														
														#echo "<pre>"; print_r($outstationFare['result']); die;
														if(isset($outstationFare['status']) && $outstationFare['status'] == '1'){
															$isOutstation = TRUE;
															$outstation_type = $checkRide->row()->outstation_type;
															$checkRide = $ci->app_model->get_all_details(RIDES, array('ride_id' => $ride_id));
															$bookingInfo = $checkRide->row()->booking_information;
															$breakupInfo = $checkRide->row()->fare_breakup;
															$pDateTime = date('Y-m-d h:i A',MongoEPOCH($bookingInfo['pickup_date']));
															$rDateTime = date('Y-m-d h:i A',$drop_time);
															$ride_total_time_min = $duration = $ride_total_time_min / 60; # in hours
															
															#echo '<pre>'; print_r($breakupInfo); die;
															
															if($checkRide->row()->outstation_type == 'round'){
																$roundtrip_hours = round($duration - $breakupInfo['min_time']);
																if($roundtrip_hours < 0) $roundtrip_hours = 0;
																$timeTrDistance = $roundtrip_hours * $breakupInfo['km_per_hour'];
																if($distance <= $timeTrDistance) $distance = $timeTrDistance;
															}
															$dAllowanceC = ceil($duration / $ci->config->item('driver_allowance_duration'));
															#echo $rDateTime; die;
															$nAllowanceC = find_night_allowance_count(0,$pDateTime,$outstation_type,$rDateTime);
															if($dAllowanceC > 0) $driverAllowance = ($dAllowanceC*$breakupInfo['driver_allowance']);
															if($nAllowanceC > 0) $nightAllowance = ($nAllowanceC*$breakupInfo['night_allowance']);
														}
													}
											/*********************************************/
												if($trip_type == "Normal"){
													$total_base_fare = $checkRide->row()->fare_breakup['min_fare'];
													$min_time = $ride_total_time_min - $checkRide->row()->fare_breakup['min_time'];
													if ($min_time > 0) {
														$total_ride_charge = ($ride_total_time_min - $checkRide->row()->fare_breakup['min_time']) * $checkRide->row()->fare_breakup['per_minute'];
													}
													$min_distance = $distance - $checkRide->row()->fare_breakup['min_km'];
													if ($min_distance > 0) {
														$total_distance_charge = ($distance - $checkRide->row()->fare_breakup['min_km']) * $checkRide->row()->fare_breakup['per_km'];
													}
													if ($wait_time > 0) {
														$total_waiting_charge = $wait_time * $checkRide->row()->fare_breakup['wait_per_minute'];
													}
													$total_fare = $total_base_fare + $total_distance_charge + $total_ride_charge + $total_waiting_charge;
													$grand_fare = $total_fare;
													
													if ($checkRide->row()->fare_breakup['peak_time_charge'] != '') {
														if($checkRide->row()->fare_breakup['peak_time_charge']>0){
															$total_peak_time_charge = $total_fare * $checkRide->row()->fare_breakup['peak_time_charge'];
															$grand_fare =$total_peak_time_charge;
														}
													}
													if ($checkRide->row()->fare_breakup['night_charge'] != '') {
														if($checkRide->row()->fare_breakup['night_charge']>0){
															$total_night_time_charge = $total_fare * $checkRide->row()->fare_breakup['night_charge'];
															if($total_peak_time_charge==0){
																$grand_fare = $total_night_time_charge;
															}else{
																$grand_fare = $grand_fare + $total_night_time_charge;
															}
														}
													}
													
													if($grand_fare != $total_fare){
														$grand_fare = $total_peak_time_charge + $total_night_time_charge;
													}else{
														$grand_fare = $total_fare;
													}
													if($total_peak_time_charge>0 && $total_night_time_charge>0){
														$total_surge = $total_peak_time_charge + $total_night_time_charge;
														$surge_val = $checkRide->row()->fare_breakup['peak_time_charge'] + $checkRide->row()->fare_breakup['night_charge'];
														$unit_surge = ($total_surge-$total_fare) / $surge_val;
														$total_peak_time_charge = $unit_surge * $checkRide->row()->fare_breakup['peak_time_charge'];
														$total_night_time_charge = $unit_surge * $checkRide->row()->fare_breakup['night_charge'];
													}else{
														if($total_peak_time_charge>0){
															$total_peak_time_charge = $grand_fare - $total_fare;
														}
														if($total_night_time_charge>0){
															$total_night_time_charge = $grand_fare - $total_fare;
														}
													}
												}else if($trip_type == "Share"){
													$no_of_seat = 1; $tax = 0;
													$tax_percent = 0;
													if(isset($checkRide->row()->pool_fare)){
														if(!empty($checkRide->row()->pool_fare)){
															$tax_percent = $checkRide->row()->pool_fare["tax_percent"];
														}
													}
													if(isset($checkRide->row()->no_of_seat)){
														if($checkRide->row()->no_of_seat!=""){
															$no_of_seat = $checkRide->row()->no_of_seat;
														}
													}
													if($no_of_seat==1){
														if(isset($checkRide->row()->pool_fare)){
															if(!empty($checkRide->row()->pool_fare)){
																$grand_fare = $checkRide->row()->pool_fare["passanger"];
																$final_tax_deduction = (($checkRide->row()->pool_fare["base_fare"]*0.01*$checkRide->row()->pool_fare["single_percent"])*0.01*$tax_percent);														
																$tax = $final_tax_deduction;
															}
														}
													}
													if($no_of_seat==2){
														if(isset($checkRide->row()->pool_fare)){
															if(!empty($checkRide->row()->pool_fare)){
																$grand_fare = $checkRide->row()->pool_fare["co_passanger"];
																$final_tax_deduction = ((($checkRide->row()->pool_fare["base_fare"]*0.01*$checkRide->row()->pool_fare["single_percent"])+((($checkRide->row()->pool_fare["base_fare"]*0.01*$checkRide->row()->pool_fare["single_percent"]))*0.01*$checkRide->row()->pool_fare["double_percent"]))*0.01*$tax_percent);														
																$tax = $final_tax_deduction;
															}
														}
													}
													if(isset($checkRide->row()->pool_fare["booking_fee"])){
														$share_booking_fee = $checkRide->row()->pool_fare["booking_fee"];
													}
													
													$total_fare = $grand_fare - ($tax+$share_booking_fee);
													$grand_fare = $total_fare + $share_booking_fee;
												}
												
												/** Surge Fare Multiplier **/
												$surge_amount = 0;
												if(isset($checkRide->row()->surge_status) && $checkRide->row()->surge_status =='Yes'){
													$grand_fare = $grand_fare * $checkRide->row()->surge_value;
													$surge_amount = $grand_fare - $total_fare;
												}	
												/** Surge Fare Multiplier **/
												
												/** fare update based on minimum fare **/
												$minimum_fare = 0; $booking_fee = 0;
												if (isset($checkRide->row()->fare_breakup['minimum_fare'])) {
													$t_minimum_fare = $checkRide->row()->fare_breakup['minimum_fare'];
													if($t_minimum_fare > $grand_fare){
														$minimum_fare = $t_minimum_fare - $grand_fare;
													}
												}
												if (isset($checkRide->row()->fare_breakup['booking_fee'])) {
													$booking_fee = $checkRide->row()->fare_breakup['booking_fee'];
												}
												if($trip_type == "Share"){
													$booking_fee = $share_booking_fee;
												} else {
													$grand_fare = $grand_fare + $minimum_fare + $booking_fee;
												}
												
												$grand_fare=$grand_fare+$parking_charge;
												
												/*********************************/
												
												/*	Normal trip fare calculation end	*/
												$user_id=$checkRide->row()->user['id'];
												$coupon_valid='No';
												if ($checkRide->row()->coupon_used == 'Yes') {
													$checkCode = $ci->app_model->get_all_details(PROMOCODE, array('promo_code' => $checkRide->row()->coupon['code'],'location'=>$checkRide->row()->location['id'],'category'=>$checkRide->row()->booking_information['service_id']));
													
													if ($checkCode->row()->status == 'Active') {
														$valid_from = strtotime($checkCode->row()->validity['valid_from'] . ' 00:00:00');
														$valid_to = strtotime($checkCode->row()->validity['valid_to'] . ' 23:59:59');
														$date_time = MongoEPOCH($checkRide->row()->booking_information['est_pickup_date']);
														
														/*** time zone conversion ***/
														if(isset($checkRide->row()->time_zone) && $checkRide->row()->time_zone != ''){
															$from_timezone = date_default_timezone_get(); 
															$to_timezone = $checkRide->row()->time_zone; 
															$date_time = strtotime($ci->timeZoneConvert(date('Y-m-d H:i',$date_time),$from_timezone, $to_timezone,'Y-m-d H:i:s'));  
														} 
														/***********************/
														
														if (($valid_from <= $date_time) && ($valid_to >= $date_time)) {
															if ($checkCode->row()->usage_allowed > $checkCode->row()->no_of_usage) {
																 $coupon_usage = array();
																if (isset($checkCode->row()->usage)) {
																	$coupon_usage = $checkCode->row()->usage;
																}
																$user_id=$checkRide->row()->user['id'];
																$usage = $ci->app_model->check_user_usage($coupon_usage, $user_id);
																if ($usage < $checkCode->row()->user_usage) {
																	$coupon_valid='Yes';
																}
															}
														}
													}
													if($coupon_valid=='No') {
														$coupon_update=array('coupon_used' =>'No',
																'coupon' => array('code' =>'',
																	'type' => '',
																	'amount' =>''
																));
														$ci->app_model->update_details(RIDES, $coupon_update, array('ride_id' => $ride_id));
													}
												}
																													
												if ($checkRide->row()->coupon_used == 'Yes' && $coupon_valid == 'Yes') {
												
													if ($checkRide->row()->coupon['type'] == 'Percent') {
														$coupon_discount = ($grand_fare * 0.01) * $checkRide->row()->coupon['amount'];
														if($coupon_discount >= $checkRide->row()->coupon['maximum_usage']){
															$coupon_discount=$checkRide->row()->coupon['maximum_usage'];
														}
													} else if ($checkRide->row()->coupon['type'] == 'Flat') {
														if ($checkRide->row()->coupon['amount'] <= $grand_fare) {
															$coupon_discount = $checkRide->row()->coupon['amount'];
														} else if ($checkRide->row()->coupon['amount'] > $grand_fare) {
															$coupon_discount = $grand_fare;
														}
													}
													
													$grand_fare = $grand_fare - $coupon_discount;
													if ($grand_fare < 0) { $grand_fare = 0; }
													$coupon_condition = array('promo_code' => $checkRide->row()->coupon['code']);
													$ci->mongo_db->where($coupon_condition)->inc('no_of_usage', 1)->update(PROMOCODE);
													/* Update the coupon usage details */
													if ($checkRide->row()->coupon_used == 'Yes') {
														$usage = array("user_id" => (string) $user_id, "ride_id" => $ride_id);
														$promo_code = (string) $checkRide->row()->coupon['code'];
														$ci->app_model->simple_push(PROMOCODE, array('promo_code' => $promo_code), array('usage' => $usage));
													}
												}
												
												/** Mileage reward for users **/
												$reward_discount = 0; $reward_remains = 0;
												if(isset($checkRide->row()->reward_id) && $checkRide->row()->reward_id!=''){
													$rwAmount = $checkRide->row()->reward_amount;
													if($grand_fare <= $rwAmount){
														$reward_discount = $grand_fare;
														$reward_remains = $rwAmount - $reward_discount;
														$grand_fare = 0;
													}	else {
														$reward_discount = $rwAmount;
														$grand_fare = $grand_fare-$rwAmount;
													}											
												}
												$ruData = array('reward_id'=>'','reward_amount'=>'');
												if($reward_remains > 0) $ruData = array('reward_amount' => (string)$reward_remains);
												$ci->user_model->update_details(USERS,$ruData,array('_id'=>MongoID($user_id)));
												$coupon_discount = $coupon_discount + $reward_discount;
												/** Mileage reward for users **/
												
											
												if($change_distance =='Yes'){
													$math_ext_distance = $math_ext_distance[0];
												}
												
												if ($checkRide->row()->tax_breakup['service_tax'] != '') {
													$total_tax = $grand_fare * 0.01 * $checkRide->row()->tax_breakup['service_tax'];
													$grand_fare = $grand_fare + $total_tax;
												}
												#echo $nightAllowance;die;
												$grand_fare = $grand_fare + $driverAllowance + $nightAllowance;
												$original_grand_fare=$grand_fare;
												$grand_fare = round($grand_fare,2);
												$hailing = array();
												if(isset($checkRide->row()->ride_type) && $checkRide->row()->ride_type == 'hailing'){
													$hailing = array("fare" => $hailing_total_fare, "distance" => $hailing_distance, "ride_time" => $hailing_ride_time);
													
												}
												#echo "<pre>"; print_r($hailing); die;
												
												$total_fare = array('base_fare' => round($total_base_fare, 2),
																			'distance' => round($total_distance_charge, 2),
																			'free_ride_time' => round($free_ride_time, 2),
																			'ride_time' => round($total_ride_charge, 2),
																			'wait_time' => round($total_waiting_charge, 2),
																			'peak_time_charge' => round($total_peak_time_charge, 2),
																			'night_time_charge' => round($total_night_time_charge, 2),
																			'driver_allowance' => round($driverAllowance, 2),
																			'night_allowance' => round($nightAllowance, 2),
																			'surge_amount' => round($surge_amount, 2),
																			'total_fare' => round($total_fare, 2),
																			'parking_charge' => round($parking_charge, 2),
																			'coupon_discount' => round($coupon_discount, 2),
																			'reward_discount' => round($reward_discount, 2),
																			'service_tax' => round($total_tax, 2),
																			'minimum_fare' =>  round($minimum_fare, 2),
																			'booking_fee' =>  round($booking_fee, 2),
																			'grand_fare' => $grand_fare,
																			'original_grand_fare' =>floatval($original_grand_fare),
																			'wallet_usage' => 0,
																			'paid_amount' => 0
																		);
												$summary = array('ride_distance' => round($distance, 2),
																			'device_distance' => round($device_distance, 2),
																			'math_distance' => round($math_ext_distance, 2),
																			'ride_duration' => round(ceil($ride_total_time_min), 2),
																			'stops_waiting_duration' => round(ceil($stops_wait_time), 2),
																			'waiting_duration' => round(ceil($wait_time), 2)
																		);
												
												$need_payment = 'YES';
												$ride_status = 'Finished';
												$pay_status = 'Pending';
												$isFree = 'NO';
												if ($grand_fare <= 0) {
													$need_payment = 'NO';
													$ride_status = 'Completed';
													$pay_status = 'Paid';
													$isFree = 'Yes';
												}	
												$mins = $ci->format_string('mins', 'mins');
												
												$min_short = $ci->format_string('min', 'min_short');
												$mins_short = $ci->format_string('mins', 'mins_short');
												if($ride_total_time_min>1){
													$ride_time_unit = $mins_short;
												}else{
													$ride_time_unit = $min_short;
												}
												if($wait_time>1){
													$wait_time_unit = $mins_short;
												}else{
													$wait_time_unit = $min_short;
												}
												
												$distance_unit = $ci->data['d_distance_unit'];
												if(isset($checkRide->row()->fare_breakup['distance_unit'])){
													$distance_unit = $checkRide->row()->fare_breakup['distance_unit'];
												}
												if($distance_unit == 'km'){
													$disp_distance_unit = $ci->format_string('km', 'km');
												}else if($distance_unit == 'mi'){
													$disp_distance_unit = $ci->format_string('mi', 'mi');
												}
												$fare_details = array('currency' => $currency,
													'ride_fare' => floatval(round($grand_fare, 2)),
													'ride_distance' => floatval(round($distance, 2)) . '  ' . $disp_distance_unit,
													'ride_duration' => round(ceil($ride_total_time_min), 2) . '  ' . $ride_time_unit,
													'waiting_duration' => round(ceil($wait_time), 2) . '  ' . $wait_time_unit,
													'need_payment' => $need_payment
												);


												$amount_commission = 0;
												$driver_revenue = 0;

												$total_grand_fare = $coupon_discount + $grand_fare;
												//$total_grand_fare_without_tax = $total_grand_fare - ($total_tax + $booking_fee);
												$total_grand_fare_without_tax = $total_grand_fare - ($total_tax + $booking_fee + $parking_charge);
												$admin_commission_percent = $checkRide->row()->commission_percent;
												$amount_commission = (($total_grand_fare_without_tax * 0.01) * $admin_commission_percent)+$total_tax + $booking_fee;
												$driver_revenue = $total_grand_fare - $amount_commission;
												#$rideDetails = array('hailing' => $hailing);
												/* Update the ride information */
												if($end_status!='' && $end_status=='Manual'){
													$payment_method=$checkRide->row()->payment_method;
													if($payment_method=='cash'){
														//$pay_status = 'Paid';
														//$ride_status='Completed';
													}
												}
												$rideDetails = array('ride_status' => (string)$ride_status,
												'hailing' => $hailing,
													'pay_status' => (string)$pay_status,
													//'ride_status' => (string)$ride_status,
													'amount_commission' => floatval(round($amount_commission, 2)),
													'driver_revenue' => floatval(round($driver_revenue, 2)),
													'booking_information.drop_date' => MongoDATE($drop_time),
													'booking_information.drop.location' => (string) $dropping_address,
													'booking_information.drop.latlong' => array('lon' => floatval($drop_lon),
														'lat' => floatval($drop_lat)
													),
													'booking_information.end_meter' => (string) $end_meter,
													'history.end_ride' => MongoDATE($drop_time),
													'total' => $total_fare,
													'summary' => $summary,
													'd_source'=>$d_source,
													'drs' => "0",
													'urs' => "0",
													'mileage_reward.trip_reward_points' => floatval($reward_points)
												);
												if(isset($checkRide->row()->ride_type) && $checkRide->row()->ride_type=='outstation' && $checkRide->row()->outstation_type=='round'){
													$rideDetails['booking_information.outstation']['location']=$checkRide->row()->booking_information['drop']['location'];
													$rideDetails['booking_information.outstation']['latlong']=$checkRide->row()->booking_information['drop']['latlong'];
												}
												$ci->app_model->update_details(RIDES, $rideDetails, array('ride_id' => $ride_id));
												
												if(isset($checkRide->row()->ride_type) && $checkRide->row()->ride_type=='hailing'){
													 $ci->sms_model->ride_hailing_sms_to_user($ride_id,'end');
												}
												$ci->app_model->update_details(PHONE_CALL_LOG,array('status'=>'inactive'),array('ride_id'=>(string)$ride_id));
												$ci->app_model->simple_insert(PAYMENTS, array('ride_id' => (string) $ride_id, 'total' => round($grand_fare, 2), 'transactions' => array()));
												/* update the driver completed count */
												$ci->app_model->update_driver_rides_count('no_of_rides', $driver_id);
												
												/*******  Set share pool route info *****/
												$ci->app_model->commonDelete(SHARE_POOL_ROUTE_INFO,['ride_id' => $ride_id]);
												/****************************************/
												
												
												/****** Set destination ride Update start *****/

												if(isset($checkDriver->row()->has_destination) && $checkDriver->row()->has_destination == 'Yes'){
													$dataArr = array('is_destination_ride' => 'Yes');
													$ci->app_model->update_details(RIDES, $dataArr,array('ride_id' => $ride_id));
													
													$cond = array('is_destination_ride' => 'Yes',
																  'booking_information.booking_date' => array('$gte' => MongoDate(strtotime(date('Y-m-d 00:00:00'))),'$lte' => MongoDate(strtotime(date('Y-m-d 23:59:59')))),
																  'driver.id' => $driver_id
															   );
													$todayDR = $ci->app_model->get_all_counts(RIDES,$cond);
													$dataArr = array(
																	'destination_ride_updated_date' => (string)date('Y-m-d'),
																	'today_destination_rides' => floatval($todayDR),
																	'destination_expiry_alert_ride' => (string)$ride_id,
																	'has_destination' => 'No'
																);
													$ci->app_model->update_details(DRIVERS, $dataArr,array('_id' => MongoID($driver_id)));
													$conditionDes = array('driver_id' => MongoID($driver_id));
													$ci->app_model->commonDelete(DRIVERS_DESTINATION_POINTS,$conditionDes);
													$ci->app_model->commonDelete(DRIVERS_DESTINATION,$conditionDes);
												}

												/********* Set destination ride Update end *******/
													
												/* First ride money credit for referrer */
												 $get_referVal=$ci->app_model->get_all_details(REFER_HISTORY,array('used'=>'false','reference_id'=>$checkRide->row()->user['id']));
												if ($get_referVal->num_rows() > 0) {
													$referer_user_id = (string)$get_referVal->row()->user_id;
													$trans_amount = $get_referVal->row()->amount_earns;
													$condition = array('reference_id' => $checkRide->row()->user['id'],
														'user_id' => MongoID($get_referVal->row()->user_id));
													$referrDataArr = array('used' => 'true');
													$ci->app_model->update_details(REFER_HISTORY, $referrDataArr, $condition);
													if($trans_amount > 0){
													   $ci->app_model->update_wallet($referer_user_id, 'CREDIT', floatval($trans_amount));
														$walletDetail = $ci->app_model->get_selected_fields(USERS, array('_id' => MongoID($referer_user_id)), array('wallet_amount'));
														$avail_amount = 0;
														if (isset($walletDetail->row()->wallet_amount)) {
															$avail_amount = $walletDetail->row()->wallet_amount;
														}
														$trans_id = time() . rand(0, 2578);
														$walletArr = array('type' => 'CREDIT',
															'credit_type' => 'referral',
															'ref_id' => (string) $checkRide->row()->user['id'],
															'trans_amount' => floatval($trans_amount),
															'avail_amount' => floatval($avail_amount),
															'trans_date' => MongoDATE(time()),
															'trans_id' => $trans_id,
															 'user_id' =>MongoID($referer_user_id),
														);
														$ci->app_model->simple_insert(WALLET_TRANSACTION,$walletArr);
													}
												}
												
												$makeInvoice = 'No';
												/* Sending notification to user regarding booking confirmation -- Start */
												$userVal = $ci->app_model->get_selected_fields(USERS, array('_id' => MongoID($checkRide->row()->user['id'])), array('_id', 'user_name', 'email', 'image', 'avg_review', 'phone_number', 'country_code', 'push_type', 'push_notification_key'));
												if (isset($userVal->row()->push_type) && $userVal->row()->push_type != '') {
													$push_title = 'Thanks For Riding';
													if($checkRide->row()->payment_method == 'cash'){
														$message = $ci->format_string("Please pay your trip payment by cash.", "pay_payment_by_cash", '', 'user', (string)$userVal->row()->_id);
													} else {
														$message = $ci->format_string("Please rate your driver and let them know how it went.\nYou can also add a tip to show the {SITENAME} Love!", "ride_completed_msg", '', 'user', (string)$userVal->row()->_id);
														$message = str_replace('{SITENAME}',$ci->config->item('email_title'),$message);
													}
													if ($need_payment == 'NO') {
														$user_id = $checkRide->row()->user['id'];
														$options = array('ride_id' => (string) $ride_id, 'user_id' => (string) $user_id);
														if ($userVal->row()->push_type == 'ANDROID') {
															if (isset($userVal->row()->push_notification_key['gcm_id'])) {
																if ($userVal->row()->push_notification_key['gcm_id'] != '') {
																	$ci->sendPushNotification($userVal->row()->push_notification_key['gcm_id'], $message, 'payment_paid', 'ANDROID', $options, 'USER',$push_title);
																}
															}
														}
														if ($userVal->row()->push_type == 'IOS') {
															if (isset($userVal->row()->push_notification_key['ios_token'])) {
																if ($userVal->row()->push_notification_key['ios_token'] != '') {
																	$ci->sendPushNotification($userVal->row()->push_notification_key['ios_token'], $message, 'payment_paid', 'IOS', $options, 'USER',$push_title);
																}
															}
														}
													}else{ 
														$options = array('ride_id' => (string) $ride_id,
																				 'grand_fare' => (string)number_format($grand_fare,2),
																				 'payment_method' => $checkRide->row()->payment_method,
																				 'currency' => $checkRide->row()->currency
																		);
														if ($userVal->row()->push_type == 'ANDROID') {
															if (isset($userVal->row()->push_notification_key['gcm_id'])) {
																if ($userVal->row()->push_notification_key['gcm_id'] != '') {
																	$ci->sendPushNotification($userVal->row()->push_notification_key['gcm_id'], $message, 'make_payment', 'ANDROID', $options, 'USER',$push_title);
																}
															}
														}
														if ($userVal->row()->push_type == 'IOS') {
															if (isset($userVal->row()->push_notification_key['ios_token'])) {
																if ($userVal->row()->push_notification_key['ios_token'] != '') {
																	$ci->sendPushNotification($userVal->row()->push_notification_key['ios_token'], $message, 'make_payment', 'IOS', $options, 'USER',$push_title);
																}
															}
														}
													}
												}
												
												//echo $need_payment; echo'<br>'; echo $isFree; die;
												
												if ($need_payment == 'NO' && $isFree == 'Yes') { 
													$pay_summary = array('type' => 'FREE');
													$paymentInfo = array('pay_summary' => $pay_summary);
													$ci->app_model->update_details(RIDES, $paymentInfo, array('ride_id' => $ride_id));
													/* Update Stats Starts */
													$current_date = MongoDATE(strtotime(date("Y-m-d 00:00:00")));
													$field = array('ride_completed.hour_' . date('H') => 1, 'ride_completed.count' => 1);
													$ci->app_model->update_stats(array('day_hour' => $current_date), $field, 1);
													/* Update Stats End */
													
													if(isset($checkDriver->row()->upcoming_ride) && $checkDriver->row()->upcoming_ride!=''){
														$avail_data = array('availability' => 'Yes');
													}else{
														$avail_data = array('mode' => 'Available', 'availability' => 'Yes');
													}
													 $ci->app_model->update_details(DRIVERS, $avail_data, array('_id' => MongoID($driver_id)));
												
													if(isset($checkDriver->row()->duty_ride) && $checkDriver->row()->duty_ride!='' && ($checkDriver->row()->duty_ride==$ride_id)){
														$ci->app_model->update_details(DRIVERS,array('duty_ride'=>''), array('_id' => MongoID($driver_id)));
													}
													
													$trans_id = time() . rand(0, 2578);
													$transactionArr = array('type' => 'Coupon',
														'amount' => floatval($grand_fare),
														'trans_id' => $trans_id,
														'trans_date' => MongoDATE(time())
													);
													$ci->app_model->simple_push(PAYMENTS, array('ride_id' => $ride_id), array('transactions' => $transactionArr));
													$makeInvoice = 'Yes';
													
													if(isset($checkRide->row()->authorization_info)&& $checkRide->row()->authorization_info!=''){
														$amount_captured = $checkRide->row()->authorization_info['amount_captured'];
														$charge_id = $checkRide->row()->authorization_info['charge_id'];
														refund_authorized_ride_amount($charge_id);
													}
												} else {
													if($checkRide->row()->payment_method != 'cash'){ 
														if(isset($checkRide->row()->split_cost) && $checkRide->row()->split_cost == 'Yes'){
															process_split_cost_ride_payment($ride_id,$driver_id);
														}
														$ci->load->helper('payment_helper');
														$payRes = process_ride_payment($ride_id);
														if($payRes['status'] == '0'){
															if (isset($userVal->row()->push_type) && $userVal->row()->push_type != '') {
																$push_title = 'Payment failed';
																$message = $ci->format_string("Please retry payment", "please_retry_payment", '', 'user', (string)$userVal->row()->_id);
																
																if(isset($payRes['msg']) && $payRes['msg'] != ''){
																	$message = $payRes['msg'];
																}
																$user_id = $checkRide->row()->user['id'];
																$options = array('ride_id' => (string) $ride_id, 'user_id' => (string) $user_id);
																if ($userVal->row()->push_type == 'ANDROID') {
																	if (isset($userVal->row()->push_notification_key['gcm_id'])) {
																		if ($userVal->row()->push_notification_key['gcm_id'] != '') {
																			$ci->sendPushNotification($userVal->row()->push_notification_key['gcm_id'], $message, 'payment_failed', 'ANDROID', $options, 'USER',$push_title);
																		}
																	}
																}
																if ($userVal->row()->push_type == 'IOS') {
																	if (isset($userVal->row()->push_notification_key['ios_token'])) {
																		if ($userVal->row()->push_notification_key['ios_token'] != '') {
																			$ci->sendPushNotification($userVal->row()->push_notification_key['ios_token'], $message, 'payment_failed', 'IOS', $options, 'USER',$push_title);
																		}
																	}
																}
															}
															$dataArr = array('payment_error' => $payRes['msg']);
															$ci->app_model->update_details(RIDES,$dataArr,array('ride_id' => $ride_id));
														}
													} else if($checkRide->row()->payment_method == 'cash'){
														cash_payment_received($driver_id,$ride_id,'auto',$end_status);
													}
													
													/*else{
														if(isset($checkDriver->row()->upcoming_ride) && $checkDriver->row()->upcoming_ride!=''){
															$avail_data = array('availability' => 'Yes');
														}else{
															$avail_data = array('mode' => 'Available', 'availability' => 'Yes');
														}
														 $ci->app_model->update_details(DRIVERS, $avail_data, array('_id' => MongoID($driver_id)));
													    #echo $checkDriver->row()->duty_ride.'-----'.$ride_id; die;
														if(isset($checkDriver->row()->duty_ride) && $checkDriver->row()->duty_ride!='' && ($checkDriver->row()->duty_ride==$ride_id)){
															$ci->app_model->update_details(DRIVERS,array('duty_ride'=>''), array('_id' => MongoID($driver_id)));
														}
													}  */
												}
												
												//$avail_data = array('mode' => 'Available', 'availability' => 'Yes');
												//$ci->app_model->update_details(DRIVERS, $avail_data, array('_id' => MongoID($driver_id)));
												
												create_and_save_travel_path_in_map($ride_id);
												if($makeInvoice == 'Yes'){
													$ci->app_model->update_ride_amounts($ride_id);
													#	make and sending invoice to the rider 	#
													$fields = array(
														'ride_id' => (string) $ride_id
													);
													$url = base_url().'prepare-invoice';
													$ci->load->library('curl');
													$output = $ci->curl->simple_post($url, $fields);
												}
												
												if(isset($checkDriver->row()->last_begin_time)){
													$dataArr = array('last_online_time' => MongoDATE(time()));
													$ci->app_model->update_details(DRIVERS, $dataArr, array('_id' => MongoID($driver_id)));
													update_mileage_system($driver_id,MongoEPOCH($checkDriver->row()->last_begin_time),'customer-drop',$distanceKM,$ci->data['d_distance_unit'],$ride_id);
												}
												
												
												$ratting_content = '1';
												$returnArr['status'] = '1';
												if($sour == "w"){
													/********* Send push notification driver if ride ends from admin ********/
													if (isset($checkDriver->row()->push_notification)) {
														if ($checkDriver->row()->push_notification != '') {
															$message = $ci->format_string('Your Trip has been completed', 'your_trip_has_been_completed', '', 'driver', (string)$driver_id);
															$options = array('ride_id' => (string) $ride_id, 'driver_id' => $driver_id);
															if (isset($checkDriver->row()->push_notification['type'])) {
																if ($checkDriver->row()->push_notification['type'] == 'ANDROID') {
																	if (isset($checkDriver->row()->push_notification['key'])) {
																		if ($checkDriver->row()->push_notification['key'] != '') {
																			$ci->sendPushNotification($checkDriver->row()->push_notification['key'], $message, 'ride_completed', 'ANDROID', $options, 'DRIVER');
																		}
																	}
																}
																if ($checkDriver->row()->push_notification['type'] == 'IOS') {
																	if (isset($checkDriver->row()->push_notification['key'])) {
																		if ($checkDriver->row()->push_notification['key'] != '') {
																			$ci->sendPushNotification($checkDriver->row()->push_notification['key'], $message, 'ride_completed', 'IOS', $options, 'DRIVER');
																		}
																	}
																}
															}
														}
													}
													$returnArr['response'] = $ci->format_string("Ride Completed", "manual_ride_completed");
													return $returnArr;
												}else{  
														get_trip_information($driver_id,$ride_id); exit;
												}
												
											}else{ 
												$returnArr['response'] = $ci->format_string("Entered inputs are incorrect", "invalid_trip_end_inputs");
											}
										}else{
											$returnArr['response'] = $ci->format_string('Sorry ! We can not fetch information', 'cannot_fetch_location_information_in_map');
										}
									} else {
										if($sour == "w"){
											$returnArr['status'] = '1';
											$returnArr['response'] = $ci->format_string("Ride Completed", "manual_ride_completed");
											return $returnArr;
										}else{
											get_trip_information($driver_id,$ride_id); exit;
										}
									}
									} else {
									$returnArr['response'] = $ci->format_string("Start meter value is ".$start_meter.", Enter current odometer value greater than start meter Value.", "enter_end_odometer_value_greater_than_begin_odometer_value");
								}
							} else {
							$returnArr['response'] = $ci->format_string("Please enter the vehicle  meter reading");
						}
					   } else {
							$returnArr['response'] = $ci->format_string("Invalid Ride", "invalid_ride");
						}
					} else {
						$returnArr['response'] = $ci->format_string("Driver not found", "driver_not_found");
					}
				}else{
					$returnArr['response'] = $ci->format_string("Some Parameters Missing", "some_parameters_missing");
				}				
			} catch (MongoException $ex) {
				$returnArr['response'] = $ci->format_string("Error in connection", "error_in_connection");
			}
			
			if($sour == "w"){
				return $returnArr;
			}else{
				$json_encode = json_encode($returnArr, JSON_PRETTY_PRINT);
				echo $ci->cleanString($json_encode);
			}
			
		}
	}
		
	
	/**
	*
	* This Function returns the trip information to drivers
	*
	**/
	if ( ! function_exists('get_trip_information')){
		function get_trip_information($driver_id= "",$cur_ride_id='') {
			
			$ci =& get_instance();
			$responseArr['status'] = '0';
			$responseArr['ride_view'] = 'home';
			$responseArr['response'] = '';
			try {
		        #echo "asdfg"; die;
				if($driver_id!="") $driver_id = $ci->input->post('driver_id');
				if ($driver_id != '') {
					$checkDriver = $ci->app_model->get_all_details(DRIVERS, array('_id' => MongoID($driver_id)));
					#echo "<pre>"; print_r($checkDriver->row()); die;
					if ($checkDriver->num_rows() == 1) {
						
						/****timezone****/
						if(isset($checkDriver->row()->time_zone) && $checkDriver->row()->time_zone != ''){
							date_default_timezone_set($checkDriver->row()->time_zone);
						}
						/**************/
						
						$ride_type = "Normal";	#(Normal / Share)
						if(isset($checkDriver->row()->ride_type)){
							if($checkDriver->row()->ride_type != ""){
								$ride_type = $checkDriver->row()->ride_type;
							}
						}
						#$responseArr['response'] = $checkDriver->row();
						#echo "<pre>"; print_r($checkDriver->row()); die;
						if($ride_type!=""){
							
							$duty_ride = "";
							if(isset($checkDriver->row()->duty_ride)){
								if($checkDriver->row()->duty_ride!=""){
									$duty_ride = $checkDriver->row()->duty_ride;
								}
							}
							$upcoming_ride = "";
							if(isset($checkDriver->row()->upcoming_ride)){
								if($checkDriver->row()->upcoming_ride!=""){
									$upcoming_ride = $checkDriver->row()->upcoming_ride;
								}
							}
							$mode_bre = "";
							if(isset($checkDriver->row()->mode)){
								if($checkDriver->row()->mode!=""){
									$mode_bre = $checkDriver->row()->mode;
								}
							}
							 #echo $duty_ride;die;
							//Available */
							if($duty_ride!="" || $mode_bre=="Booked"){
								if($upcoming_ride!=''){
                                    $checkRide = $ci->app_model->get_driver_active_trips($driver_id,'','');
                                }else{
                                    $checkRide = $ci->app_model->get_driver_active_trips($driver_id,$duty_ride,$ride_type);
                                }
								#echo "<pre>"; print_r($checkRide->row()); die;
								if($checkRide->num_rows() >0) {
									$ridesArr = array();
									$locArr = array();
									foreach($checkRide->result() as $rides_key => $rides){
										$ride_info = array();
										$user_id = $rides->user['id'];
										
										$userVal = $ci->app_model->get_selected_fields(USERS, array('_id' => MongoID($user_id)), array('_id', 'user_name', 'email', 'image', 'avg_review', 'phone_number', 'country_code'));
										if ($userVal->num_rows() > 0) {
											
											
											$user_image = base_url().USER_PROFILE_IMAGE_DEFAULT;
											if (isset($userVal->row()->image) && $userVal->row()->image != '') {
												if (file_exists(USER_PROFILE_IMAGE.$userVal->row()->image)) {
													$user_image=base_url().USER_PROFILE_IMAGE.$userVal->row()->image;
												}else {
													$user_image=get_filedoc_path(USER_PROFILE_IMAGE.$userVal->row()->image);
												}
											}
											
											$user_review = 0;
											if (isset($userVal->row()->avg_review)) {
												$user_review = $userVal->row()->avg_review;
											}
											
											$pickup_time = date("M jS, Y H:i", MongoEPOCH($rides->booking_information['est_pickup_date']));
											if($rides->ride_status=="Onride" || $rides->ride_status=="Finished" || $rides->ride_status=="Completed")
											$pickup_time = date("M jS, Y H:i", MongoEPOCH($rides->booking_information['pickup_date']));
											$begin_ride_time = $current_time = time();
											if(isset($rides->history['begin_ride']) && $rides->history['begin_ride'] !=''){
												$begin_ride_time = MongoEPOCH($rides->history['begin_ride']);
											}
											$begin_time = abs($current_time-$begin_ride_time);
											$phone_number = $userVal->row()->country_code . $userVal->row()->phone_number;
											if(isset($rides->masked_number) && $rides->masked_number!='') $phone_number = $rides->masked_number;
											
											$ride_info = array('user_id' => (string)$userVal->row()->_id,
												'user_name' => (string)$userVal->row()->user_name,
												#'user_email' => (string)$userVal->row()->email,
												'phone_number' => (string) $phone_number,
												'user_image' => (string) $user_image,
												'user_review' => (string)floatval($user_review),
												'pickup_location' => (string)$rides->booking_information['pickup']['location'],
												'pickup_lat' => (string)$rides->booking_information['pickup']['latlong']['lat'],
												'pickup_lon' => (string)$rides->booking_information['pickup']['latlong']['lon'],
												'pickup_time' => $pickup_time,
												'drop_location' => "0",
												'drop_loc' => "",
												'drop_lat' => "",
												'drop_lon' => ""
											);
											$ride_info['hailing_begin_time'] = "0";
											if($rides->ride_type == 'hailing'){
													$ride_info['hailing_begin_time'] = (string)$begin_time;
												}
											if(array_key_exists("drop",$rides->booking_information)){
												if($rides->booking_information['drop']['location']!=""){
													$ride_info["drop_location"] = (string)1;
													$ride_info["drop_loc"] = (string)$rides->booking_information['drop']['location'];
													$ride_info["drop_lat"] = (string)$rides->booking_information['drop']['latlong']['lat'];
													$ride_info["drop_lon"] = (string)$rides->booking_information['drop']['latlong']['lon'];
												}
											}
										}
										
										if(isset($rides->pool_ride)){
											if($rides->pool_ride=="Yes"){
												$ride_info["no_of_seat"] = (string)$rides->no_of_seat;
												$ride_info["max_no_of_seat"] = (string)2;
												
												if($rides->ride_status=="Confirmed" && ($cur_ride_id == $rides->ride_id || $cur_ride_id == '')){
													if(isset($checkDriver->row()->loc)){
														if($checkDriver->row()->loc!=""){
															$lA = array_reverse($checkDriver->row()->loc);
														}
													}
													$lA['txt'] = "Your Location";
													$locArr[] = $lA;
													$lA = array_reverse($rides->booking_information['pickup']['latlong']);
													$lA['txt'] = "Pickup ".$rides->user["name"];
													$locArr[] = $lA;
												} else if($rides->ride_status=="Arrived" && ($cur_ride_id == $rides->ride_id || $cur_ride_id == '')){
													$locArr = array();
													$lA = array_reverse($rides->booking_information['pickup']['latlong']);
													$lA['txt'] = "Pickup ".$rides->user["name"];
													$locArr[] = $lA;
													if(array_key_exists("drop",$rides->booking_information)){
														if($rides->booking_information['drop']['location']!=""){
															$lA = array_reverse($rides->booking_information['drop']['latlong']);
															$lA['txt'] = "Drop ".$rides->user["name"];
															$locArr[] = $lA;
														}
													}
												} else if($cur_ride_id == $rides->ride_id || $cur_ride_id == ''){
													$lA = array_reverse($rides->booking_information['pickup']['latlong']);
													$lA['txt'] = "Pickup ".$rides->user["name"];
													$locArr[] = $lA;
													if(array_key_exists("drop",$rides->booking_information)){
														if($rides->booking_information['drop']['location']!=""){
															$lA = array_reverse($rides->booking_information['drop']['latlong']);
															$lA['txt'] = "Drop ".$rides->user["name"];
															$locArr[] = $lA;
														}
													}
												}
												
											}else{
												$lA = array_reverse($rides->booking_information['pickup']['latlong']);
												$lA['txt'] = "Pickup ".$rides->user["name"];
												$locArr[] = $lA;
												if(array_key_exists("drop",$rides->booking_information)){
													if($rides->booking_information['drop']['location']!=""){
														$lA = array_reverse($rides->booking_information['drop']['latlong']);
														$lA['txt'] = "Drop ".$rides->user["name"];
														$locArr[] = $lA;
													}
												}
											}
										}else{											
											if($rides->ride_status=="Confirmed"){
												if(isset($checkDriver->row()->loc)){
													if($checkDriver->row()->loc!=""){
														$lA = array_reverse($checkDriver->row()->loc);
													}
												}
												$lA['txt'] = "Your Location";
												$locArr[] = $lA;
												$lA = array_reverse($rides->booking_information['pickup']['latlong']);
												$lA['txt'] = "Pickup ".$rides->user["name"];
												$locArr[] = $lA;
											}else if($rides->ride_status=="Arrived"){
												$locArr = array();
												$lA = array_reverse($rides->booking_information['pickup']['latlong']);
												$lA['txt'] = "Pickup ".$rides->user["name"];
												$locArr[] = $lA;
												if(array_key_exists("drop",$rides->booking_information)){
													if($rides->booking_information['drop']['location']!=""){
														$lA = array_reverse($rides->booking_information['drop']['latlong']);
														$lA['txt'] = "Drop ".$rides->user["name"];
														$locArr[] = $lA;
													}
												}
											}else{
												$lA = array_reverse($rides->booking_information['pickup']['latlong']);
												$lA['txt'] = "Pickup ".$rides->user["name"];
												$locArr[] = $lA;
												if(array_key_exists("drop",$rides->booking_information)){
													if($rides->booking_information['drop']['location']!=""){
														$lA = array_reverse($rides->booking_information['drop']['latlong']);
														$lA['txt'] = "Drop ".$rides->user["name"];
														$locArr[] = $lA;
													}
												}
											}											
										}
										$trip_type = "Normal";
										if(isset($rides->pool_ride)){
											if($rides->pool_ride=="Yes"){
												$trip_type = "Share";
											}
										}
										
										$btn_group = 0;
										$ride_status = $rides->ride_status;
										
										$ride_status_case = $rides->ride_status;										
										if($rides->ride_status=="Completed"){
											if(isset($rides->drs) && $rides->drs=="0"){
												$ride_status_case = "Ratting";
											}
										}
										
										switch($ride_status_case){
											case "Booked": $btn_group = 1; break;		# No Buttons
											case "Confirmed": $btn_group = 2; break;	# Arrived and Cancelled
											case "Arrived": $btn_group = 3; break;		# Begin and Cancelled
											case "Onride": $btn_group = 4; break;		# End
											case "Finished": $btn_group = 5; break;		# Payment
											case "Completed": $btn_group = 6; break;	# No Buttons
											case "Cancelled": $btn_group = 7; break;	# No Buttons
											case "Ratting": $btn_group = 8; break;		# Ratting
										}
										
										$invoice_src = '';
										if($ride_status == "Completed"){
											$invoice_path=$ride_id.'_large.jpg';
				                            if (file_exists(base_url().'/trip_invoice/'.$invoice_path)) {
				                                $invoice_src=base_url().'/trip_invoice/'.$invoice_path;
				                            }else {
				                                $invoice_src=get_filedoc_path('trip_invoice/'.$invoice_path);
				                            }
										}
										
										$fare_summary = array();
										if($ride_status == "Finished" || $ride_status == "Completed"){
											$fare_summary = array();
											if($trip_type == "Normal"){
												if (isset($rides->total['base_fare'])) {
													if ($rides->total['base_fare'] >= 0) {
														$fare_summary[] = array("title"=>(string)$ci->format_string("Base fare", "fare_summary_base_fare"),
																							"value"=>(string)round($rides->total['base_fare'],2)
																							);
													}
												}
												if (isset($rides->total['peak_time_charge'])) {
													if ($rides->total['peak_time_charge'] > 0) {
														$fare_summary[] = array("title"=>(string)$ci->format_string("Peak time fare", "fare_summary_peak_time_fare").' ('.floatval($rides->fare_breakup['peak_time_charge']).'X)',
																							"value"=>(string)round($rides->total['peak_time_charge'],2)
																							);
													}
												}
												if (isset($rides->total['night_time_charge'])) {
													if ($rides->total['night_time_charge'] > 0) {
														$fare_summary[] = array("title"=>(string)$ci->format_string("Night time fare", "fare_summary_night_time_fare").' ('.floatval($rides->fare_breakup['night_charge']).'X)',
																							"value"=>(string)round($rides->total['night_time_charge'],2)
																							);
													}
												}
												if (isset($rides->total['surge_amount'])) {
													if ($rides->total['surge_amount'] > 0) {
														$fare_summary[] = array("title"=>(string)$ci->format_string("Surge fare", "fare_summary_surge_fare").' ('.floatval($rides->surge_value).'X)',
																							"value"=>(string)round($rides->total['surge_amount'],2)
																							);
													}
												}
											}
											if (isset($rides->total['total_fare'])) {
												if ($rides->total['total_fare'] >= 0) {
													$fare_summary[] = array("title"=>(string)$ci->format_string("Subtotal", "fare_summary_total"),
																						"value"=>(string)round($rides->total['total_fare'],2)
																						);
												}
											}
											
											if (isset($rides->total['coupon_discount'])) {
												if ($rides->total['coupon_discount'] > 0) {
													$fare_summary[] = array("title"=>(string)$ci->format_string("Discount amount", "fare_summary_coupon_discount"),
																						"value"=>(string)round($rides->total['coupon_discount'],2)
																						);
												}
											}
											
											if (isset($rides->total['service_tax'])) {
												if ($rides->total['service_tax'] > 0) {
													$fare_summary[] = array("title"=>(string)$ci->format_string("Service tax", "fare_summary_service_tax"),
																						"value"=>(string)round($rides->total['service_tax'],2)
																						);
												}
											}
																						
											if (isset($rides->total['grand_fare'])) {
												if ($rides->total['grand_fare'] >= 0) {
													$fare_summary[] = array("title"=>(string)$ci->format_string("Grand Total", "fare_summary_grand_fare"),
																						"value"=>(string)round($rides->total['grand_fare'],2)
																						);
												}
											}
											
											if (isset($rides->total['tips_amount'])) {
												if ($rides->total['tips_amount'] > 0) {
													$fare_summary[] = array("title"=>(string)$ci->format_string("Tips amount", "fare_summary_tips"),
																						"value"=>(string)round($rides->total['tips_amount'],2)
																						);
												}
											}
											if (isset($rides->total['wallet_usage'])) {
												if ($rides->total['wallet_usage'] > 0) {
													$fare_summary[] = array("title"=>(string)$ci->format_string("Wallet used amount", "fare_summary_wallet_used"),
																						"value"=>(string)round($rides->total['wallet_usage'],2)
																						);
												}
											}
											
											if (isset($rides->total['paid_amount'])) {
												if ($rides->total['paid_amount'] > 0) {
													$fare_summary[] = array("title"=>(string)$ci->format_string("Paid Amount", "fare_summary_paid_amount"),
																						"value"=>(string)round($rides->total['paid_amount'],2)
																						);
												}
											}
											
											
											$distance_unit = $ci->data['d_distance_unit'];
											if(isset($rides->fare_breakup['distance_unit'])){
												$distance_unit = strtolower($rides->fare_breakup['distance_unit']);
											}
                                            
                                            $display_distance_unit = $ci->format_string($distance_unit, $distance_unit);
                                            
											$summaryArr = array();
											$min_short = $ci->format_string('min', 'min_short');
											$mins_short = $ci->format_string('mins', 'mins_short');
											
											if (isset($rides->summary)) {
												if (is_array($rides->summary)) {
													foreach ($rides->summary as $key => $values) {
														if($key=="ride_distance"){
															$summaryArr[] = array("title"=>(string)$ci->format_string("Trip Distance", "trip_summary_trip_distance"),
																						"value"=>(string) $values,
																						"unit"=>(string) $display_distance_unit
																						);
														}else if($key=="ride_duration"){
															if($values<=1){
																$unit = $min_short;
															}else{
																$unit = $mins_short;
															}
															$summaryArr[] = array("title"=>(string)$ci->format_string("Trip Duration", "trip_summary_trip_duration"),
																						"value"=>(string) $values,
																						"unit"=>(string) $unit
																						);
														} /*else if($key=="waiting_duration"){
															if($values>0){
																if($values<=1){
																	$unit = $min_short;
																}else{
																	$unit = $mins_short;
																}
																$summaryArr[] = array("title"=>(string)$ci->format_string("Waiting Duration", "trip_summary_waiting_duration"),
																							"value"=>(string) $values,
																							"unit"=>(string) $unit
																							);
															}
														}   */                              
													}
												}
											}
																	
											$need_payment = "1";
											$receive_cash = "0";
											$req_payment = "0";
											if ($ci->config->item('pay_by_cash') != '' && $ci->config->item('pay_by_cash') != 'Disable') {
												$receive_cash = "1";
											}
											
											$payArr = $ci->app_model->get_all_details(PAYMENT_GATEWAY,array("status"=>"Enable"));
											if($payArr->num_rows()>0 && $rides->payment_method != 'cash'){
												$req_payment = "1";
												$need_payment = "0";
											}
											$pay_status = '';
											if (isset($rides->pay_status)) {
												$pay_status = $rides->pay_status;
												if($pay_status == 'Paid'){
													$need_payment = "0";
												}
											}
											$payable_amount = 0;$grand_fare = 0;$total_paid = 0;$wallet_usage = 0;$tips_amount = 0;
											if (isset($rides->total['grand_fare'])) {
												$grand_fare = $rides->total['grand_fare'];
											}
											if (isset($rides->total['tips_amount'])) {
												$tips_amount = $rides->total['tips_amount'];
											}
											if (isset($rides->total['wallet_usage'])) {
												$wallet_usage = $rides->total['wallet_usage'];
											}
											if (isset($rides->total['paid_amount'])) {
												$total_paid = $rides->total['paid_amount'];
											}
											
											if(($rides->ride_status == 'Finished'  || $rides->ride_status == 'Completed') && $need_payment == '1' && $receive_cash == '1'){
												$payable_amount = ($grand_fare+$tips_amount)-($wallet_usage+$total_paid);
											} else {
												$payable_amount = $rides->driver_revenue;
											}
											
											$payment_timeout = $ci->data['user_timeout'];
											$fare_info["need_payment"] = $need_payment;
											$fare_info["receive_cash"] = $receive_cash;
											$fare_info["req_payment"] = $req_payment;
											$fare_info["payment_timeout"] = $payment_timeout;
											$fare_info["total_payable_amount"] = number_format($payable_amount,2);
											$fare_info["trip_summary"] = $summaryArr;
										}
										$fare_info["payment_method"] = $rides->payment_method;
										
										$car_icon = base_url().ICON_MAP_CAR_IMAGE;
										if($trip_type!="Share"){
											$cat_id = ""; 
											if(isset($rides->booking_information['service_id'])) $cat_id = $rides->booking_information['service_id'];
											if($cat_id!=""){
												$categoryInfo = $ci->app_model->get_selected_fields(CATEGORY, array('_id' => MongoID($cat_id)), array('_id','icon_car_image'));
												if ($categoryInfo->num_rows() > 0) {
													if(isset($categoryInfo->row()->icon_car_image)){
														if (file_exists(ICON_IMAGE.$categoryInfo->row()->icon_car_image)) {
															$car_icon=base_url() . ICON_IMAGE . $categoryInfo->row()->icon_car_image;
														}else {
															$car_icon=get_filedoc_path(ICON_IMAGE.$categoryInfo->row()->icon_car_image);
														}
													}											
												}
											}
										}else{
											if ($ci->config->item('pool_map_car_image')!=""){
												if (file_exists(ICON_IMAGE.$ci->config->item('pool_map_car_image'))) {
													$car_icon=base_url() . ICON_IMAGE . $ci->config->item('pool_map_car_image');
												}else {
													$car_icon=get_filedoc_path(ICON_IMAGE.$ci->config->item('pool_map_car_image'));
												}
											}
										}
										
										if(isset($rides->ride_type)){
                                            $ride_type = $rides->ride_type;
                                        }
										
								/*	if($checkDriver->row()->ride_type!='Share'){
											if($checkDriver->row()->duty_ride == $rides->ride_id){
												if(isset($rides->pool_ride) && $rides->pool_ride == 'Yes') {
													$driver_ride_type = 'Share';
												} else {
													$driver_ride_type = 'Normal';
													if(isset($rides->ride_type) && $rides->ride_type !='' ){
														$driver_ride_type = $rides->ride_type;
													}
												}
											} 
										}else{
											if(isset($rides->pool_ride) && $rides->pool_ride == 'Yes') {
												$driver_ride_type = 'Share';
											} else {
												$driver_ride_type = 'Normal';
												if(isset($rides->ride_type) && $rides->ride_type !='' ){
													$driver_ride_type = $rides->ride_type;
												}
											}
										} */
								    if(isset($checkDriver->row()->ride_type) && $checkDriver->row()->ride_type != ''){
										$driver_ride_type = $checkDriver->row()->ride_type;
									}
									if($checkDriver->row()->duty_ride == $rides->ride_id){
											if(isset($rides->pool_ride) && $rides->pool_ride == 'Yes') {
													$driver_ride_type = 'Share';
												}else{
													$driver_ride_type = 'Normal';
													if(isset($rides->ride_type) && $rides->ride_type !='' ){
														$driver_ride_type = $rides->ride_type;
													}
												}
										} 	
										
									#echo "asfdf".$checkDriver->row()->duty_ride .'=='. $rides->ride_id.'=='.$driver_ride_type;die;
									
                                        $start_reading = 0;
                                        if(isset($rides->booking_information['begin_meter'])){
                                            $start_reading = $rides->booking_information['begin_meter'];
                                        }
										
										$booking_notes='';
										if(isset($rides->booking_notes)){
											if($rides->booking_notes!=''){
												$booking_notes = $rides->booking_notes;
											}
										}
										
										$rArr = array_merge(array("ride_id"=>(string)$rides->ride_id,
																				"show_fare"=>'Yes',
																				"currency" => $rides->currency,
																				"booking_notes" => (string)$booking_notes,
																				"ride_status"=>(string)$ride_status,
																				"btn_group"=>(string)$btn_group,
																				"car_icon"=>(string)$car_icon,
																				"invoice_src"=>(string)$invoice_src,
                                                                                "start_reading" =>(string)$start_reading
																		),$ride_info
																);
										
										$fare_info["fare_summary"] = $fare_summary;
										$rArr = array_merge($rArr,$fare_info);
										
										$ridesArr[] = $rArr;
										
									}
									
									if($ride_type=="Share"){
										if($cur_ride_id != ''){
											$active_ride = $cur_ride_id;
										} else {
											$active_ride = $duty_ride;
											if(!empty($ridesArr)){
												$active_ride = $ridesArr[0]['ride_id'];
												if(count($ridesArr)>1){
													if($ridesArr[1]['ride_status']=='Finished' || $ridesArr[1]['btn_group']=='8'){
														$active_ride = $ridesArr[1]['ride_id'];
													}
												}
											}
										}										
									}else{
										$active_ride = $duty_ride;
									}
									
									
									
                                    $stops = array();
                                    $stops_status = '0';
                                    $stops_reached_count = 0;
                                    if(isset($checkRide->row()->booking_information['stops'])){
                                        $stopsArr = array_values($checkRide->row()->booking_information['stops']);
                                        foreach($stopsArr as $vals){
                                            if(isset($vals['status']) && $vals['status'] == 'Departed'){
                                                $stops_reached_count++;
                                            }
                                            $status = '';
                                            if(isset($vals['status'])){
                                                $status = $vals['status'];
                                            }
                                            $stops[] = array('location' => $vals['location'],
                                                             'latlong' => $vals['latlong'],
                                                             'stop_code' => $vals['stop_code'],
                                                             'status' => $status
                                                            );
											/*  if($rides->ride_status=="Onride"){
												 $locArr = array();
												 $src = $stops_reached_count;
												 $next_src = $src+1; 
												 if($src == 0){
													 $next_src = 0;
													$lA = array_reverse($rides->booking_information['pickup']['latlong']);
													$lA['txt'] = "Pickup ".$rides->user["name"]; 
													$locArr[] = $lA; 
												 } else {
													$lA[] = array(array_reverse($stopsArr[$src]['latlong']));
													$lA['txt'] = "Stop ".$src;
													$locArr[] = $lA;
												 }
												 if($src == count($stopsArr)){
													if(array_key_exists("drop",$rides->booking_information)){
														if($rides->booking_information['drop']['location']!=""){
															$lA = array_reverse($rides->booking_information['drop']['latlong']);
															$lA['txt'] = "Drop ".$rides->user["name"];
															$locArr[] = $lA;
														}
													}
												 } else if(isset($stopsArr[$next_src]['latlong'])){
													$lA = array_reverse($stopsArr[$next_src]['latlong']); 
													$stpNum = $src+1;
													$lA['txt'] = "Stop $stpNum";
													$locArr[] = $lA; 
												 }
											 } */			
                                        }
                                        if(!empty($stops)) $stops_status = '1';
                                    }
									$stFreeWtime = 0;
                                    if(isset($checkRide->row()->fare_breakup['stops_free_wait_time'])){
                                        $stFreeWtime = $checkRide->row()->fare_breakup['stops_free_wait_time'];
                                    }
                                    
                                    $driverLoc = array();
                                    if(isset($checkDriver->row()->loc['lon']) && $checkDriver->row()->loc['lat']){
                                         $driverLoc = array('lon' => $checkDriver->row()->loc['lon'],
                                                            'lat' => $checkDriver->row()->loc['lat']
                                                      );
                                    }
									
									$share_route_update = 'No';
									if($ride_type == "Share" && count($ridesArr) == 1){
										$share_route_update = 'Yes';
									}
									
									$upcoming_ride='';
									if(isset($checkDriver->row()->upcoming_ride)){
										$upcoming_ride=$checkDriver->row()->upcoming_ride;
									}
									
									$add_new_stop_status = 'No'; #&& count($stops) == $stops_reached_count
									if($ci->data['no_of_stops_allowed'] > count($stops) && $checkRide->row()->ride_status == 'Onride' && $upcoming_ride=='' && !isset($checkRide->row()->pool_ride)){
										$add_new_stop_status = 'Yes';
									}
									//if($upcoming_ride!=""){
									//	$stops_status = '0';
									//}
									
									$responseArr['status'] = '1';
									$responseArr['ride_view'] = 'stay';
									$responseArr['response'] = array(
                                                    #'ride_type'=>(string)$ride_type,
                                                    'ride_type'=>(string)$driver_ride_type,
													'share_route_update' => $share_route_update,
                                                    'duty_id'=>(string)$duty_ride,
                                                    'active_ride'=>(string)$active_ride,
                                                    'rides'=>$ridesArr,
                                                    'map_locations'=>$locArr,
                                                    'stops_status' => (string) $stops_status,
													'add_new_stop_status' => (string) $add_new_stop_status,
                                                    'stops' => $stops,
                                                    'stopover_free_wait_time' => $stFreeWtime,
                                                    'stops_reached_count' => (string) $stops_reached_count,
                                                    'driver_loc' => $driverLoc
												);
												#echo "<pre>"; print_r($checkRide->row()->fare_breakup['min_fare']); die;
												$responseArr['response']['fare'] = array('base_fare'=> '',  'minimum_fare' => '', 'per_km' => '', 'per_minute' => '');
												$min_fare = $checkRide->row()->fare_breakup['min_fare'];
												$booking_fee = $checkRide->row()->fare_breakup['booking_fee'];
												$per_km = $checkRide->row()->fare_breakup['per_km'];
												$minimum_fare = $checkRide->row()->fare_breakup['minimum_fare'];
												$per_minute = $checkRide->row()->fare_breakup['per_minute'];
												$base_bare = floatval($min_fare) + floatval($booking_fee);
												if($driver_ride_type == 'hailing'){
													$responseArr['response']['fare'] = array('base_fare'=> (string)$base_bare,'booking_fee' => $booking_fee, 'minimum_fare' => $minimum_fare, 'per_km' => $per_km, 'per_minute' => $per_minute);
												}
								}else{
									$responseArr['response'] = $ci->format_string('Currently no rides are available','currently_no_rides_are_available');
								}
							}else{
								$responseArr['response'] = $ci->format_string('Currently no rides are available','currently_no_rides_are_available');
							}
						}else{ 
							$responseArr['response'] = $ci->format_string('Currently no rides are available','currently_no_rides_are_available');
						}
					} else {
						$responseArr['response'] = $ci->format_string('Authentication Failed','authentication_failed');
					}
				} else {
					$responseArr['response'] = $ci->format_string("Some Parameters are missing","some_parameters_missing");
				}
			} catch (MongoException $ex) {
				$responseArr['response'] = $ci->format_string('Error in connection','error_in_connection');
			}
			$json_encode = json_encode($responseArr, JSON_PRETTY_PRINT);
			echo $ci->cleanString($json_encode);
		}
	}
	
	/**
	*
	* This Function returns the trip information to drivers
	*
	**/
	if ( ! function_exists('update_driver_avail_rides')){
		function update_driver_avail_rides($driver_id= "",$checkDriver='') {
			$ci =& get_instance();
			
			try {
				if($driver_id!="") $driver_id = $ci->input->post('driver_id');
				if ($driver_id != '') {
					if(empty($checkDriver) && $checkDriver == ''){
						$checkDriver = $ci->app_model->get_all_details(DRIVERS, array('_id' => MongoID($driver_id)));
					}
					if ($checkDriver->num_rows() == 1) {
						$active_trips = 0;
						$upcoming_ride="";
						if(isset($checkDriver->row()->upcoming_ride) && $checkDriver->row()->upcoming_ride!=''){
							$upcoming_ride=$checkDriver->row()->upcoming_ride;
						}
						if(isset($checkDriver->row()->ride_type)){
							$curr_duty_ride = "";
							if(isset($checkDriver->row()->duty_ride)){
								$curr_duty_ride = $checkDriver->row()->duty_ride;
							}
							$ride_type = $checkDriver->row()->ride_type;
							$checkAvailRide = $ci->app_model->get_driver_active_trips($driver_id,$curr_duty_ride,$ride_type);
							if($checkAvailRide->num_rows()>0){
								$active_trips = intval($checkAvailRide->num_rows());
							}
						}
						if($upcoming_ride==""){
							$ci->app_model->update_details(DRIVERS, array("active_trips"=>intval($active_trips)), array('_id' => MongoID($driver_id)));
						}
						
					}
				}
			} catch (MongoException $ex) { }
		}
	}
	
	
	/**
	*
	*	This function accept a booking by the driver
	*	Param @bookingInfo as Array
	*	Holds all the acceptance information Eg. Driver Id, ride id, latitude, longtitude and distance
	*
	**/	
	if ( ! function_exists('assign_ride')){
		function assign_ride($assignInfo= array()) {
			$ci =& get_instance();
			
			$returnArr['status'] = '0';
			$returnArr['response'] = '';
			$returnArr['ride_view'] = 'stay';
			#echo "<pre>"; print_r($assignInfo); die;
			try {
				if(array_key_exists("driver_id",$assignInfo)) $driver_id =  trim($assignInfo['driver_id']); else $driver_id = "";
				if(array_key_exists("ride_id",$assignInfo)) $ride_id =  trim($assignInfo['ride_id']); else $ride_id = "";
				if(array_key_exists("hailing_ride",$assignInfo)) $hailing_ride =  trim($assignInfo['hailing_ride']); else $hailing_ride = "";
				#echo ""; print_r($assignInfo); die;
				 $ref="auto"; if(array_key_exists("ref",$assignInfo)) $ref="manual";
				 
				 $minimum_balance_to_go_online = 0;
					if($ci->config->item('driver_minimum_balance_to_ride') != ''){
						$minimum_balance_to_go_online = $ci->config->item('driver_minimum_balance_to_ride');
					}
				
				if ($driver_id!="" && $ride_id!="") {
					$checkDriver = $ci->app_model->get_all_details(DRIVERS, array('_id' => MongoID($driver_id)));
					if ($checkDriver->num_rows() == 1) { 
					   $driver_wallet_amount = $checkDriver->row()->wallet_amount;
					   $driver_outstation_settings = '';
					   if(isset($checkDriver->row()->driver_outstation_settings) && $checkDriver->row()->driver_outstation_settings !=''){
						   $driver_outstation_settings = $checkDriver->row()->driver_outstation_settings;
					   }
					   
						// if ($checkDriver->row()->mode == 'Available' && $checkDriver->row()->availability == 'Yes' ) {
							
							$driver_lat = '';
							if(isset($checkDriver->row()->loc) && $checkDriver->row()->loc['lat'] != ''){
								$driver_lat = $checkDriver->row()->loc['lat'];
							}
							$driver_lon = '';
							if(isset($checkDriver->row()->loc) && $checkDriver->row()->loc['lon'] != ''){
								$driver_lon = $checkDriver->row()->loc['lon'];
							}
							$distance = 0;
							
							
							$company_id = '';
							if(isset($checkDriver->row()->company_id) && $checkDriver->row()->company_id != ''){
								$company_id = $checkDriver->row()->company_id;
							}
							$dispatcher_id = '';
							if(isset($checkDriver->row()->dispatcher_id) && $checkDriver->row()->dispatcher_id != ''){
								$dispatcher_id = $checkDriver->row()->dispatcher_id;
							}
							$checkRide = $ci->app_model->get_all_details(RIDES, array('ride_id' => $ride_id));
							if ($checkRide->num_rows() == 1) {
								$ride_payment_method = $checkRide->row()->payment_method;
								$outstation_ride_type = $checkRide->row()->ride_type;
								#echo "<pre>"; print_r($checkRide->row()); die;
								$isGo = FALSE;
								if ($checkDriver->row()->ride_type== 'Normal' && $checkDriver->row()->mode== 'Available'){
										$isGo = TRUE;
								}
								if ($checkDriver->row()->ride_type== 'Share'){
										$isGo = TRUE;
								}
								#echo $checkRide->row()->type; die;
								if($ride_payment_method == 'cash' && $driver_wallet_amount >= $minimum_balance_to_go_online){
									if(isset($outstation_ride_type) && ($outstation_ride_type == 'outstation' && $driver_outstation_settings == 'ON') || $outstation_ride_type == 'hailing' || $checkRide->row()->type == 'Now' || $checkRide->row()->type == 'Later'){
										  if ($checkRide->row()->ride_status == 'Booked' || $checkRide->row()->driver['id'] == $driver_id) {
											if ($checkRide->row()->ride_status == 'Booked') {
												$userVal = $ci->app_model->get_selected_fields(USERS, array('_id' => MongoID($checkRide->row()->user['id'])), array('_id', 'user_name', 'email', 'image', 'avg_review', 'phone_number', 'country_code', 'push_type', 'push_notification_key'));
												if ($userVal->num_rows() > 0) {
													$service_id = $checkRide->row()->booking_information['service_id'];
													/* Update the ride information with fare and driver details -- Start */
													$pickup_lon = $checkRide->row()->booking_information['pickup']['latlong']['lon'];
													$pickup_lat = $checkRide->row()->booking_information['pickup']['latlong']['lat'];
													$service_type=$checkRide->row()->booking_information['service_type'];
													$from = $driver_lat . ',' . $driver_lon;
													$to = $pickup_lat . ',' . $pickup_lon;

													$urls = 'https://maps.googleapis.com/maps/api/directions/json?origin=' . $from . '&destination=' . $to . '&alternatives=true&sensor=false&mode=driving'.$ci->data['google_maps_api_key'];
													$gmap = file_get_contents($urls);
													$map_values = json_decode($gmap);
													$routes = $map_values->routes;
													if(!empty($routes)){
														#usort($routes, create_function('$a,$b', 'return intval($a->legs[0]->distance->value) - intval($b->legs[0]->distance->value);'));
														
														$distance_unit = $ci->data['d_distance_unit'];
														$duration_unit = 'min';
														if(isset($checkRide->row()->fare_breakup)){
															if($checkRide->row()->fare_breakup['distance_unit']!=''){
																$distance_unit = $checkRide->row()->fare_breakup['distance_unit'];
																$duration_unit = $checkRide->row()->fare_breakup['duration_unit'];
															} 
														}

														$mindistance = 1;
														$minduration = 1;
														$mindurationtext = '';
														if (!empty($routes[0])) {
															#$mindistance = ($routes[0]->legs[0]->distance->value) / 1000;
															$min_distance = $routes[0]->legs[0]->distance->text;
															if (preg_match('/km/',$min_distance)){
																$return_distance = 'km';
															}else if (preg_match('/mi/',$min_distance)){
																$return_distance = 'mi';
															}else if (preg_match('/m/',$min_distance)){
																$return_distance = 'm';
															} else {
																$return_distance = 'km';
															}
															
															$mindistance = floatval(str_replace(',','',$min_distance));
															if($distance_unit!=$return_distance){
																if($distance_unit=='km' && $return_distance=='mi'){
																	$mindistance = $mindistance * 1.60934;
																} else if($distance_unit=='mi' && $return_distance=='km'){
																	$mindistance = $mindistance * 0.621371;
																} else if($distance_unit=='km' && $return_distance=='m'){
																	$mindistance = $mindistance / 1000;
																} else if($distance_unit=='mi' && $return_distance=='m'){
																	$mindistance = $mindistance * 0.00062137;
																}
															}
															$mindistance = floatval(round($mindistance,2));
													
													
															$minduration = ($routes[0]->legs[0]->duration->value) / 60;
															$est_pickup_time = (time()) + $routes[0]->legs[0]->duration->value;
															$mindurationtext = $routes[0]->legs[0]->duration->text;
														}

														$fareDetails = $ci->app_model->get_all_details(LOCATIONS, array('_id' => MongoID($checkRide->row()->location['id'])));
														if ($fareDetails->num_rows() > 0) {
															$service_tax = 0.00;
															if (isset($fareDetails->row()->service_tax)) {
																if ($fareDetails->row()->service_tax > 0) {
																	$service_tax = $fareDetails->row()->service_tax;
																}
															}
															if (isset($fareDetails->row()->fare[$service_id]) && $service_id!=POOL_ID) {
																$peak_time = '';
																$night_charge = '';
																$peak_time_amount = '';
																$night_charge_amount = '';
																$min_amount = 0.00;
																$max_amount = 0.00;
																$pickup_datetime = MongoEPOCH($checkRide->row()->booking_information['est_pickup_date']);
																$pickup_date = date('Y-m-d', MongoEPOCH($checkRide->row()->booking_information['est_pickup_date']));
																
																/*** time zone conversion ***/
																if(isset($checkRide->row()->time_zone) && $checkRide->row()->time_zone != ''){
																	$from_timezone = date_default_timezone_get(); 
																	$to_timezone = $checkRide->row()->time_zone; 
																	$pickup_datetime = strtotime($ci->timeZoneConvert(date('Y-m-d H:i',$pickup_datetime),$from_timezone, $to_timezone,'Y-m-d H:i:s'));
																	$pickup_date = date('Y-m-d',$pickup_datetime);
																} 
																/***********************/
																
																if ($fareDetails->row()->peak_time == 'Yes') {
																	$time1 = strtotime($pickup_date . ' ' . $fareDetails->row()->peak_time_frame['from']);
																	$time2 = strtotime($pickup_date . ' ' . $fareDetails->row()->peak_time_frame['to']);
																	
																	$ptc = FALSE;
																	if ($time1 > $time2) {
																		if (date('A', $pickup_datetime) == 'PM') {
																			if (($time1 <= $pickup_datetime) && (strtotime('+1 day', $time2) >= $pickup_datetime)) {
																				$ptc = TRUE;
																			}
																		} else {
																			if ((strtotime('-1 day', $time1) <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
																				$ptc = TRUE;
																			}
																		}
																	} else if ($time1 < $time2) {
																		if (($time1 <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
																			$ptc = TRUE;
																		}
																	}
																	if ($ptc) {
																		$peak_time_amount = $fareDetails->row()->fare[$service_id]['peak_time_charge'];
																	}
																}
																if ($fareDetails->row()->night_charge == 'Yes') {
																	$time1 = strtotime($pickup_date . ' ' . $fareDetails->row()->night_time_frame['from']);
																	$time2 = strtotime($pickup_date . ' ' . $fareDetails->row()->night_time_frame['to']);
																	$nc = FALSE;
																	if ($time1 > $time2) {
																		if (date('A', $pickup_datetime) == 'PM') {
																			if (($time1 <= $pickup_datetime) && (strtotime('+1 day', $time2) >= $pickup_datetime)) {
																				$nc = TRUE;
																			}
																		} else {
																			if ((strtotime('-1 day', $time1) <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
																				$nc = TRUE;
																			}
																		}
																	} else if ($time1 < $time2) {
																		if (($time1 <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
																			$nc = TRUE;
																		}
																	}
																	if ($nc) {
																		$night_charge_amount = $fareDetails->row()->fare[$service_id]['night_charge'];
																	}
																}
																$stops_free_wait_time = 0;
																if(isset($fareDetails->row()->fare[$service_id]['stops_free_wait_time'])){
																	$stops_free_wait_time = $fareDetails->row()->fare[$service_id]['stops_free_wait_time'];
																}
																
																$booking_fee = $minimum_fare = 0;
																if(isset($fareDetails->row()->fare[$service_id]['booking_fee'])){
																	$booking_fee = $fareDetails->row()->fare[$service_id]['booking_fee'];
																}
																if(isset($fareDetails->row()->fare[$service_id]['minimum_fare'])){
																	$minimum_fare = $fareDetails->row()->fare[$service_id]['minimum_fare'];
																}
																
																$fare_breakup = array('min_km' => (string) $fareDetails->row()->fare[$service_id]['min_km'],
																	'min_time' => (string) $fareDetails->row()->fare[$service_id]['min_time'],
																	'min_fare' => (string) $fareDetails->row()->fare[$service_id]['min_fare'],
																	'per_km' => (string) $fareDetails->row()->fare[$service_id]['per_km'],
																	'per_minute' => (string) $fareDetails->row()->fare[$service_id]['per_minute'],
																	'wait_per_minute' => (string) $fareDetails->row()->fare[$service_id]['wait_per_minute'],
																	'cancellation_charge'=>(string) $fareDetails->row()->fare[$service_id]['cancellation_charge'],
																	'peak_time_charge' => (string) $peak_time_amount,
																	'stops_free_wait_time' => (string) $stops_free_wait_time,
																	'night_charge' => (string) $night_charge_amount,
																	'distance_unit' => (string) $distance_unit,
																	'duration_unit' => (string) $duration_unit,
																	'booking_fee' => (string) $booking_fee,
																	'minimum_fare' => (string) $minimum_fare
																);
															}
														}
												
														$vehicle_model = $checkDriver->row()->vehicle_model;
														
														$driverInfo = array('id' => (string) $checkDriver->row()->_id,
															'name' => (string) $checkDriver->row()->first_name,
															'email' => (string) $checkDriver->row()->email,
															'phone' => (string) $checkDriver->row()->dail_code . $checkDriver->row()->mobile_number,
															'vehicle_model' => (string) $vehicle_model,
															'vehicle_no' => (string) $checkDriver->row()->vehicle_number,
															'lat_lon' => (string) $driver_lat . ',' . $driver_lon,
															'est_eta' => (string) $mindurationtext
														);
														$history = array('booking_time' => $checkRide->row()->booking_information['booking_date'],
															'estimate_pickup_time' => MongoDATE($est_pickup_time),
															'driver_assigned' => MongoDATE(time())
														);
														$driver_commission = $checkRide->row()->commission_percent;
														if (isset($checkDriver->row()->driver_commission)) {
															$driver_commission = $checkDriver->row()->driver_commission;
														}
														if($checkRide->row()->ride_type == 'hailing'){
															$driver_commission = $checkDriver->row()->hailing_driver_commission;
														}
														$curr_duty_ride = $ride_id;
														if(isset($checkDriver->row()->duty_ride) && $checkDriver->row()->duty_ride!=''){
															$curr_duty_ride = $checkDriver->row()->duty_ride;
														}
														
														if($driver_commission>100) $driver_commission = 100;
														$rideDetails = array('ride_status' => 'Confirmed',
															'commission_percent' => floatval($driver_commission),
															'driver' => $driverInfo,
															'company_id'=>$company_id,
															'dispatcher_id'=>$dispatcher_id,
															'tax_breakup' => array('service_tax' => $service_tax),
															'booking_information.est_pickup_date' => MongoDATE($est_pickup_time),
															'history' => $history
														);
														
														if($company_id != ''){
															$getCompany = $ci->app_model->get_selected_fields(COMPANY,array('_id' => $company_id),array('company_name','email','mobile_number','dail_code'));
															$companyInfo = array('id' => (string)$company_id,
																				 'name' => $getCompany->row()->company_name,
																				 'email' => $getCompany->row()->email,
																				 'phone' => $getCompany->row()->dail_code.$getCompany->row()->mobile_number);
															$rideDetails['company'] = $companyInfo;
														}
														
														$active_trips = 0;
														if($service_id!=POOL_ID){
															$active_trips = 1;
															$rideDetails['fare_breakup'] = $fare_breakup;
														}else if($service_id==POOL_ID){
															
															if(!isset($rideDetails['fare_breakup'])){
																$driverCat = (string)$checkDriver->row()->category;
																$rideDetails['fare_breakup.cancellation_charge'] = $fareDetails->row()->fare[$driverCat]['cancellation_charge'];
															}
															
															$pooling_with = array(); $co_rider = array(); $pool_type = 0;
															
															$checkAvailRide = $ci->app_model->get_driver_active_trips($driver_id,$curr_duty_ride,"Share");
															if($checkAvailRide->num_rows()>0){
																$active_trips = intval($checkAvailRide->num_rows());
															}
															if($active_trips>=1){
																$pool_type = 1;
																$active_trips ++;
																$pooling_with = array("name"=>$checkAvailRide->row()->user["name"],
																								"id"=>$checkAvailRide->row()->user["id"]
																							);
																$co_rider = array("name"=>$checkAvailRide->row()->user["name"],
																								"id"=>$checkAvailRide->row()->user["id"]
																							);
																$ext_pooling_with = array("name"=>$checkRide->row()->user["name"],
																								"id"=>$checkRide->row()->user["id"]
																							);
																$ext_co_rider = array("name"=>$checkRide->row()->user["name"],
																								"id"=>$checkRide->row()->user["id"]
																							);
																if(isset($checkAvailRide->row()->ride_id)){
																	$ext_ride_id = (string)$checkAvailRide->row()->ride_id;
																	if(isset($checkAvailRide->row()->pooling_with)){
																		$ext_pooling_with = $ext_pooling_with;
																	}
																	if(isset($checkAvailRide->row()->co_rider)){
																		$ext_co_rider_val = $checkAvailRide->row()->co_rider;
																		$ext_co_rider_val[] = $ext_co_rider;
																		$ext_co_rider = $ext_co_rider_val;
																	}
																	$ext_ride_Arr = array("pooling_with"=>$ext_pooling_with,"co_ride"=>$ext_co_rider);
																	
																	
																	/** Discounted Pool fare update to first rider  **/
																	$mindistance = $checkAvailRide->row()->booking_information['est_travel_distance'];
																	$minduration = $checkAvailRide->row()->booking_information['est_travel_duration'];
																	$location_id = $checkAvailRide->row()->location['id'];
																	$ci->load->helper('pool_helper');
																	$poolFareResponse = get_pool_fare($mindistance,$minduration,$location_id,'discount',0,$pickup_lat,$pickup_lon);
																	if($poolFareResponse["status"]=="1"){
																		$est_amount = 0; $tax_amount = 0;
																		if($no_of_seat==1){
																			$est_amount = $poolFareResponse["passenger"];
																			$tax_amount = $poolFareResponse["single_tax"];
																		}else if($no_of_seat==2){
																			$est_amount = $poolFareResponse["co_passenger"];
																			$tax_amount = $poolFareResponse["double_tax"];
																		}
																		$pool_fare = array("est"=>(string)round($est_amount,2),
																									"tax"=>(string)round($tax_amount, 2),
																									"tax_percent"=>(string)$poolFareResponse["tax_percent"],
																									"base_fare"=>(string)$poolFareResponse["base_fare"],
																									"single_percent"=>(string)$poolFareResponse["single_percent"],
																									"double_percent"=>(string)$poolFareResponse["double_percent"],
																									"passanger"=>(string)round($poolFareResponse["passenger"], 2),
																									"co_passanger"=>(string)round($poolFareResponse["co_passenger"], 2),
																									"fare_category"=>$poolFareResponse["fare_category"],
																									"booking_fee"=>$poolFareResponse["booking_fee"]
																								);
																	}
																	
																	$ext_ride_Arr['pool_discount_applied'] = 'Yes';
																	$ext_ride_Arr['pool_fare'] = $pool_fare;
																	$ext_ride_Arr['booking_information.est_amount'] = $est_amount;
																	/*************/
																	
																	
																	
																	$ci->app_model->update_details(RIDES, $ext_ride_Arr, array('ride_id' => $ext_ride_id));
																	
																	/*	Sending the notification regarding the matching	*/
																	$ext_user_id = $checkAvailRide->row()->user["id"];
																	if($ext_user_id!=""){
																		$extUserVal = $ci->app_model->get_selected_fields(USERS, array('_id' => MongoID($ext_user_id)), array('_id','push_type','push_notification_key'));
																		if ($extUserVal->num_rows() > 0) {
																		  
																			if (isset($extUserVal->row()->push_type)) {
																				if ($extUserVal->row()->push_type != '') {
																					$message = $ci->format_string('Ride has been matched and you got extra discount', 'ride_matched','','user',(string)$extUserVal->row()->_id);						
																					$optionsFExt = array('ride_id' => $ext_ride_id);
																					if ($extUserVal->row()->push_type == 'ANDROID') {
																						if (isset($extUserVal->row()->push_notification_key['gcm_id'])) {
																							if ($extUserVal->row()->push_notification_key['gcm_id'] != '') {
																								$ci->sendPushNotification($extUserVal->row()->push_notification_key['gcm_id'], $message, 'track_reload', 'ANDROID', $optionsFExt, 'USER');
																							}
																						}
																					}
																					if ($extUserVal->row()->push_type == 'IOS') {
																						if (isset($extUserVal->row()->push_notification_key['ios_token'])) {
																							if ($extUserVal->row()->push_notification_key['ios_token'] != '') {
																								$ci->sendPushNotification($extUserVal->row()->push_notification_key['ios_token'], $message, 'track_reload', 'IOS', $optionsFExt, 'USER');
																							}
																						}
																					}
																				}
																			}
																		   
																		}
																	}
																	/*	Sending the notification regarding the matching	*/
																}
															}else{
																$active_trips = 1;
															}
															
															$rideDetails['pooling_with'] = $pooling_with;
															$rideDetails['co_rider'] = $co_rider;
															$rideDetails['pool_type'] = (string)$pool_type;
															$rideDetails['pool_id'] = (string)$curr_duty_ride;
														}
														$driverSRls = array("ride_type"=>"Normal",
																					"duty_ride"=>(string)$ride_id,
																					"share_drop"=>array()
																				);
														if(isset($checkRide->row()->pool_ride)){
															if($checkRide->row()->pool_ride=="Yes"){
																$share_drop = $checkRide->row()->booking_information['drop']['latlong'];
																$driverSRls = array("ride_type"=>"Share",
																							"duty_ride"=>(string)$curr_duty_ride,
																							"share_drop"=>$share_drop
																						);
																						
																						
																/*******  Set share pool route info *****/
																$ci->load->helper('pool_helper');
																if(isset($routes[0]->legs[0]) && !empty($routes[0]->legs[0])){
																	$legs = $routes[0]->legs[0];
																}
																$legs = base64_encode(json_encode($legs));
																$poolRouteData = [
																	'driver_id' => (string) $checkDriver->row()->_id,
																	'ride_id' => $ride_id,
																	'action' => 'set',
																	'legs' => ''
																];
																update_share_pool_route_info($poolRouteData,'auto');
															}
														}
														/* echo '<pre>'; print_r($ext_ride_Arr); 
														echo '<pre>'; print_r($driverSRls); 
														echo '<pre>'; print_r($rideDetails);  die; */
														$checkBooked = $ci->app_model->get_selected_fields(RIDES, array('ride_id' => $ride_id, 'ride_status' => 'Booked'), array('ride_id', 'ride_status'));
														$checkAvailable = $ci->app_model->get_selected_fields(DRIVERS, array('_id' => MongoID($driver_id)), array('mode','duty_ride','ride_type'));
														$availablity = false;
														if ($checkAvailable->row()->mode == 'Available') {
															$availablity = true;
														}else{
															$hasPool_ride = false;
															if(isset($checkAvailable->row()->duty_ride)){
																if ($checkAvailable->row()->duty_ride != '' && $checkAvailable->row()->ride_type == 'Share') {
																	$hasPool_ride = true;
																}
															}
														}
														
														$ispool_ride = 'No';
														 if(isset($checkRide->row()->pool_ride) && $checkRide->row()->pool_ride == 'Yes'){
															$ispool_ride = 'Yes';
														 }
	
														$upcoming_ride_status='No';
														if(!$hasPool_ride){
															if(!$availablity){
																if ((isset($checkAvailable->row()->duty_ride) && $checkAvailable->row()->duty_ride!='') && (!isset($checkAvailable->row()->upcoming_ride) || $checkAvailable->row()->upcoming_ride=='')) {
																	$availablity = true;
																	$active_trips = 2;
																	if($ispool_ride =='No'){
																	$driverSRls['upcoming_ride'] =$ride_id;
																	}
																	$upcoming_ride_status ='Yes';
																	unset($driverSRls['duty_ride']);
																}
															}
														}
														if($upcoming_ride_status=='No' && $ispool_ride =='No'){
															$driverSRls['upcoming_ride'] =$ride_id;
														}
														
														if(isset($checkAvailable->row()->duty_ride) && $checkAvailable->row()->duty_ride!=''){
															unset($driverSRls['duty_ride']);
															$active_trips = 2;
														}

														if ($checkBooked->num_rows() > 0 && ($availablity === true || $hasPool_ride == true)) {
															$ci->app_model->update_details(RIDES, $rideDetails, array('ride_id' => $ride_id));
															/* Update the ride information with fare and driver details -- End */

															
															/* Update the driver status to Booked */
															$driverSRls['mode'] = "Booked";
															$driverSRls['active_trips'] = intval($active_trips);
															$ci->app_model->update_details(DRIVERS, $driverSRls, array('_id' => MongoID($driver_id)));

															/* Update the no of rides  */
															$ci->app_model->update_user_rides_count('no_of_rides', $userVal->row()->_id);
															//$ci->app_model->update_driver_rides_count('no_of_rides', $driver_id);

															/* Update Stats Starts */
															$current_date = MongoDATE(strtotime(date("Y-m-d 00:00:00")));
															$field = array('ride_booked.hour_' . date('H') => 1, 'ride_booked.count' => 1);
															$ci->app_model->update_stats(array('day_hour' => $current_date), $field, 1);
															/* Update Stats End */


															/* Preparing driver information to share with user -- Start */
											
																$driver_image = base_url().USER_PROFILE_IMAGE_DEFAULT;
																if (isset($checkDriver->row()->image)) {
																	if ($checkDriver->row()->image != '') {
																		if (file_exists(USER_PROFILE_IMAGE.$checkDriver->row()->image)) {
																			$driver_image=base_url().USER_PROFILE_IMAGE.$checkDriver->row()->image;
																		}else {
																			$driver_image=get_filedoc_path(USER_PROFILE_IMAGE.$checkDriver->row()->image);
																		}
													
																	}
																}
															
															$driver_review = 0;
															if (isset($checkDriver->row()->avg_review)) {
																$driver_review = $checkDriver->row()->avg_review;
															}
															$driver_profile = array('driver_id' => (string) $checkDriver->row()->_id,
																'driver_name' => (string) $checkDriver->row()->first_name,
																'driver_email' => (string) $checkDriver->row()->email,
																'driver_image' => (string) $driver_image,
																'driver_review' => (string) floatval($driver_review),
																'driver_lat' => floatval($driver_lat),
																'driver_lon' => floatval($driver_lon),
																'min_pickup_duration' => $mindurationtext,
																'ride_id' => $ride_id,
																'phone_number' => (string) $checkDriver->row()->dail_code . $checkDriver->row()->mobile_number,
																'vehicle_number' => (string) $checkDriver->row()->vehicle_number,
																'vehicle_model' => (string) $vehicle_model,
																'pickup_location' => (string) $checkRide->row()->booking_information['pickup']['location'],
																'pickup_lat' => (string) $pickup_lat,
																'pickup_lon' => (string) $pickup_lon
															);
															/* Preparing driver information to share with user -- End */


															/* Preparing user information to share with driver -- Start */
															$user_image = base_url().USER_PROFILE_IMAGE_DEFAULT;
															if (isset($userVal->row()->image) && $userVal->row()->image != '') {
																if (file_exists(USER_PROFILE_IMAGE.$userVal->row()->image)) {
																	$user_image=base_url().USER_PROFILE_IMAGE.$userVal->row()->image;
																}else {
																	$user_image=get_filedoc_path(USER_PROFILE_IMAGE.$userVal->row()->image);
																}
															}
															$user_review = 0;
															if (isset($userVal->row()->avg_review)) {
																$user_review = $userVal->row()->avg_review;
															}
															$user_profile = array('user_name' => $userVal->row()->user_name,
																'user_email' => $userVal->row()->email,
																'phone_number' => (string) $userVal->row()->country_code . $userVal->row()->phone_number,
																'user_image' => $user_image,
																'user_review' => floatval($user_review),
																'ride_id' => $ride_id,
																'pickup_location' => $checkRide->row()->booking_information['pickup']['location'],
																'pickup_lat' => $pickup_lat,
																'pickup_lon' => $pickup_lon,
																'pickup_time' => get_time_to_string("H:i jS M, Y", MongoEPOCH($checkRide->row()->booking_information['actual_pickup_date']))
															);
															/* Preparing user information to share with driver -- End */

															/* Sending notification to user regarding booking confirmation -- Start */
															# Push notification
															if (isset($userVal->row()->push_type)) {
																if ($userVal->row()->push_type != '') {
																	$message = $ci->format_string('Your ride request confirmed', 'ride_request_confirmed','','user',(string)$userVal->row()->_id);						
																	$options = $driver_profile;
																	
																	$action  = "ride_confirmed";
																	if($ref=="manual") $action  = "ride_later_confirmed";
																	
																	if ($userVal->row()->push_type == 'ANDROID') {
																		if (isset($userVal->row()->push_notification_key['gcm_id'])) {
																			if ($userVal->row()->push_notification_key['gcm_id'] != '') {
																				$ci->sendPushNotification($userVal->row()->push_notification_key['gcm_id'], $message, $action, 'ANDROID', $driver_profile, 'USER');
																			}
																		}
																	}
																	if ($userVal->row()->push_type == 'IOS') {
																		if (isset($userVal->row()->push_notification_key['ios_token'])) {
																			if ($userVal->row()->push_notification_key['ios_token'] != '') {
																				$ci->sendPushNotification($userVal->row()->push_notification_key['ios_token'], $message, $action, 'IOS', $driver_profile, 'USER');
																			}
																		}
																	}
																}
															}
															/* Sending notification to user regarding booking confirmation -- End */
															
															$drop_location = 0;
															$drop_loc = '';$drop_lat = '';$drop_lon = '';
															if($checkRide->row()->booking_information['drop']['location']!=''){
																$drop_location = 1;
																$drop_loc = $checkRide->row()->booking_information['drop']['location'];
																$drop_lat = $checkRide->row()->booking_information['drop']['latlong']['lat'];
																$drop_lon = $checkRide->row()->booking_information['drop']['latlong']['lon'];
															}
															$user_profile['drop_location'] = (string)$drop_location;
															$user_profile['drop_loc'] = (string)$drop_loc;
															$user_profile['drop_lat'] = (string)$drop_lat;
															$user_profile['drop_lon'] = (string)$drop_lon;
															
															 $ride_type = 'normal'; 
															 if(isset($checkRide->row()->ride_type)){
																$ride_type = $checkRide->row()->ride_type;
															 }
															 
															 if($ride_type=='outstation'){
																if(isset($checkRide->row()->outstation_type) && $checkRide->row()->outstation_type!=''){
																	$ride_type="Outstation(".ucfirst($checkRide->row()->outstation_type)." Trip)";
																}
															}
															$user_profile['ride_type'] = (string)$ride_type;
															
															$ride_check_type='Normal';
															if(isset($checkRide->row()->pool_ride)){
																if($checkRide->row()->pool_ride=='Yes'){
																	$ride_check_type='Share';
																}
															}
															if(isset($checkRide->row()->ride_type)){
																if($checkRide->row()->ride_type!=''){
																	$ride_check_type=$checkRide->row()->ride_type;
																}
															}
															$booking_notes='';
															if(isset($checkRide->row()->booking_notes)){
																if($checkRide->row()->booking_notes!=''){
																	$booking_notes=$checkRide->row()->booking_notes;
																}
															}
															
															
															
															$category_name='';
															if(isset($checkRide->row()->booking_information['service_type']) && $checkRide->row()->booking_information['service_type']!=''){
																$category_name=$checkRide->row()->booking_information['service_type'];
															}
															$user_profile['category_name'] = (string)$category_name;
														
															$user_profile['check_ride_type'] = (string)$ride_check_type;
															
															$user_profile['booking_notes'] = (string)$booking_notes;
															/* if ($ride_id != '') {
																$checkInfo = $ci->app_model->get_all_details(TRACKING, array('ride_id' => $ride_id));
															
																$latlng = $driver_lat . ',' . $driver_lon;
																$gmap = file_get_contents("https://maps.googleapis.com/maps/api/geocode/json?latlng=" . $latlng . "&sensor=false".$ci->data['google_maps_api_key']);
																$mapValues = json_decode($gmap)->results;
																if(!empty($mapValues)){
																	$formatted_address = $mapValues[0]->formatted_address;
																	$cuurentLoc = array('timestamp' => MongoDATE(time()),
																		'locality' => (string) $formatted_address,
																		'location' => array('lat' => floatval($driver_lat), 'lon' => floatval($driver_lon))
																	);
																	
																	if ($checkInfo->num_rows() > 0) {
																		$ci->app_model->simple_push(TRACKING, array('ride_id' => (string) $ride_id), array('steps' => $cuurentLoc));
																	} else {
																		$ci->app_model->simple_insert(TRACKING, array('ride_id' => (string) $ride_id));
																		$ci->app_model->simple_push(TRACKING, array('ride_id' => (string) $ride_id), array('steps' => $cuurentLoc));
																	}
																}
															} */
															
															
															if (empty($user_profile)) {
																$user_profile = json_decode("{}");
															}
														if(isset($hailing_ride) && $hailing_ride != 'hailing'){	
															// if((isset($checkRide->row()->ride_type) && $checkRide->row()->ride_type == 'outstation') && (isset($driver['driver_outstation_settings']) && $driver['driver_outstation_settings'] == 'ON')){
														
															if(isset($checkDriver->row()->push_notification['type']) && $checkDriver->row()->push_notification['type']!=''){
																$driver_id = (string)$checkDriver->row()->_id;
																$message = $ci->format_string('You have a new trip', 'u_have_new_trip', '', 'driver', (string)$driver_id);
																if($checkDriver->row()->push_notification['type']=='ANDROID'){
																	$condition=array('_id'=>MongoID($driver_id));
																	$ci->mongo_db->where($condition)->inc('req_received',1)->update(DRIVERS);
																	$ci->sendPushNotification(array($checkDriver->row()->push_notification['key']),$message,'new_trip','ANDROID',$user_profile,'DRIVER');
																}
																if($checkDriver->row()->push_notification['type']=='IOS'){
																	$condition=array('_id'=>MongoID($driver_id));
																	$ci->mongo_db->where($condition)->inc('req_received',1)->update(DRIVERS);
																	$ci->sendPushNotification(array($checkDriver->row()->push_notification['key']),$message,'new_trip','IOS',$user_profile,'DRIVER');
																}
															}
														}
															

															$returnArr['status'] = '1';
															$returnArr['response'] = array('user_profile' => $user_profile, 
																										'message' => $ci->format_string("Ride Accepted", "ride_accepted")
																								);
															
															if(isset($checkDriver->row()->last_online_time)){
																$dataArr = array('last_accept_time' => MongoDATE(time()));
																$ci->app_model->update_details(DRIVERS, $dataArr, array('_id' => MongoID($driver_id)));
																update_mileage_system($driver_id,MongoEPOCH($checkDriver->row()->last_online_time),'free-roaming',$distance,$ci->data['d_distance_unit'],$ride_id);
															}
															
														} else {
															$returnArr['ride_view'] = 'home';
															$returnArr['response'] = $ci->format_string('you are too late, this ride is booked.', 'you_are_too_late_to_book_this_ride');
														}
													} else {
														$returnArr['ride_view'] = 'home';
														$returnArr['response'] = $ci->format_string('Sorry ! We can not fetch information', 'cannot_fetch_location_information_in_map');								
													}
												}else{
													$returnArr['ride_view'] = 'home';
													$returnArr['response'] = $ci->format_string('You cannot accept this ride.', 'you_cannot_accept_this_ride');
												}
											}else{
												$ride_status = $checkRide->row()->ride_status;
												if($ride_status=="Cancelled"){
													$returnArr['ride_view'] = 'home';
													$returnArr['response'] = $ci->format_string('This ride has been cancelled', 'this_ride_cancelled');
												}else if($checkRide->row()->driver['id'] == $driver_id){
													$returnArr['ride_view'] = 'detail';
													 $returnArr['response'] = $ci->format_string('Ride has been already accepted', 'ride_already_accepted_accepted');
												}else{
													$returnArr['ride_view'] = 'home';
													$returnArr['response'] = $ci->format_string('You cannot accept this ride.', 'you_cannot_accept_this_ride');
												}
											}
										} else {
											$returnArr['ride_view'] = 'home';
											$returnArr['response'] = $ci->format_string('you are too late, this ride is booked.', 'you_are_too_late_to_book_this_ride');
										}
									} else {
										$returnArr['ride_view'] = 'home';
										$returnArr['response'] = $ci->format_string('Driver Outstation Settings Disabled', 'driver_outstation_settings_disabled');
									}
							   } else {
									$returnArr['ride_view'] = 'home';
									$returnArr['response'] = $ci->format_string('Driver Wallet Amount Is Low For Cash Rides.', 'driver_wallet_amount_is_low');
								}
							} else {
								$returnArr['ride_view'] = 'home';
								$returnArr['response'] = $ci->format_string("This ride is unavailable", "ride_unavailable");
							}
						// } else {
							// $returnArr['response'] = $ci->format_string("This driver is currently not availabile");
						// }
					} else {
						$returnArr['response'] = $ci->format_string("Driver not found", "driver_not_found");
					}
				}else{
					$returnArr['response'] = $ci->format_string("Some Parameters Missing", "some_parameters_missing");
				}				
			} catch (MongoException $ex) {
				$returnArr['response'] = $ci->format_string("Error in connection", "error_in_connection");
			}
			return $returnArr;
		}
	}

	if (!function_exists('get_referer_name')){
		function get_referer_name($user_id){
			$ci =& get_instance();
			$checkuser = $ci->app_model->get_selected_fields(USERS, array('_id' => MongoID($user_id)),array('user_name'));
			if(isset($checkuser->row()->user_name)){
				return $checkuser->row()->user_name;
			}else{
				return '';
			}
		}
	}
	
	
	if ( ! function_exists('create_and_save_travel_path_in_map')){
		function create_and_save_travel_path_in_map($ride_id) {
            /* for invoice map*/
            $ci =& get_instance();                        
            
            $ride_info = $ci->user_model->get_all_details(RIDES, array('ride_id' => (string)$ride_id));
            if($ride_info->num_rows()==1){ 
				#($ride_info->row()->ride_status == 'Finished' || $ride_info->row()->ride_status == 'Completed')
                $ride_info=$ride_info->row();
				if(!empty($ride_info->booking_information['drop']['latlong']['lat'])&&!empty($ride_info->booking_information['drop']['latlong']['lon'])){
					$drop_lat=$ride_info->booking_information['drop']['latlong']['lat'];
					$drop_lon=$ride_info->booking_information['drop']['latlong']['lon'];
				}else{
					$drop_lat=0;
					$drop_lon=0;
				}
				if(!empty($ride_info->booking_information['pickup']['latlong']['lat'])&&!empty($ride_info->booking_information['pickup']['latlong']['lon'])){
					$pickup_lat=$ride_info->booking_information['pickup']['latlong']['lat'];
					$pickup_lon=$ride_info->booking_information['pickup']['latlong']['lon'];
				}else{
					$pickup_lat=0;
					$pickup_lon=0;
				}
                  
                $path = '';
                $path .= '|'.$pickup_lat.','.$pickup_lon;
                $pathArr[] = array($pickup_lat,$pickup_lon);
                $ride_begin_time = "";
                if(isset($ride_info->history['begin_ride'])){
					$ride_begin_time = $ride_info->history['begin_ride'];
                }
                $ride_end_time = "";
                if(isset($ride_info->history['end_ride'])){
					$ride_end_time = $ride_info->history['end_ride'];
                }
                $tracking_values= $ci->user_model->get_all_details(TRAVEL_HISTORY,array('ride_id' => $ride_id));
                if(isset($tracking_values->row()->history_end) && !empty($tracking_values->row()->history_end)){
                    $chkPath = array();
                    foreach($tracking_values->row()->history_end as $track){
                        $latlong = $track['lat'].'-'.$track['lon'];
                        if(!in_array($latlong,$chkPath)){
                            $chkPath[] = $latlong;
                            $pathArr[] = array($track['lat'],$track['lon']);
                            $path.='|'.$track['lat'].','.$track['lon'];
                        }
					}
				} 
				
				$live_url= base_url();
				if($_SERVER['HTTP_HOST']=="192.168.1.251" || $_SERVER['HTTP_HOST']=="localhost"){
					$live_url= 'https://cabily-e.zoplay.com/';
				}
				
				$dropMarker = "";
				$centered_lat = $pickup_lat; $centered_lon = $pickup_lon;
				if($ride_info->ride_status == 'Finished' || $ride_info->ride_status == 'Completed'){
					$path .= '|'.$drop_lat.','.$drop_lon;
					$dropMarker = "&markers=icon:".$live_url."images/drop_marker.png|".$drop_lat.",".$drop_lon;
					$centered_lat=($pickup_lat+$drop_lat)/2.0;
					$centered_lon=($pickup_lon+$drop_lon)/2.0;
				}  
                $pathArr[] = array($drop_lat,$drop_lon);
                if(count($pathArr) > 300){
                    require_once(APPPATH.'/third_party/LatLongEncoder/Polyline.php');
					$polyEncoder = new Polyline();
                    $encodedPath = $polyEncoder->encode($pathArr); 
					if(isset($encodedPath)) $path = '|enc:'.$encodedPath;
                }
                
			   
				$url="https://maps.googleapis.com/maps/api/staticmap?center=".$centered_lat.",".$centered_lon."&zoom=auto&size=300x113&sensor=false&markers=icon:".$live_url."images/pickup_marker.png|".$pickup_lat.",".$pickup_lon.$dropMarker."&path=color:0xbd2981ff|weight:2".$path."&style=feature:poi|element:labels|visibility:off&key=".$ci->config->item('google_maps_api_key'); 
				
				$ci->load->library('S3_Upload');
								
				$temp = base64_decode(base64_encode(file_get_contents($url)));
				$imgPath = 'trip_invoice/';
				$imgName = $ride_info->ride_id. "_small.jpg";
				$target_file1 = $imgPath . $imgName;
				
				
				$s3 = new S3_Upload(array("access_key"=>ASSET_S3_ACCESS_KEY, "secret_key"=>ASSET_S3_SECRET_KEY));
						
				$S3_route_map=$s3->putObject(array('data'=>$temp,'type'=>'data:image/jpeg;base64'), ASSET_S3_BUCKET_NAME, $target_file1, S3_Upload::ACL_PUBLIC_READ);
				 
                $url="https://maps.googleapis.com/maps/api/staticmap?center=".$centered_lat.",".$centered_lon."&zoom=auto&size=640x242&sensor=false&markers=icon:".$live_url."images/pickup_marker.png|".$pickup_lat.",".$pickup_lon.$dropMarker."&path=color:0xbd2981ff|weight:2".$path."&style=feature:poi|element:labels|visibility:off&key=".$ci->config->item('google_maps_api_key'); 

               $imgName = $ride_id. "_large.jpg";
				$target_file2 = $imgPath . $imgName;
				$temp = base64_decode(base64_encode(file_get_contents($url)));
				
                if($s3->putObject(array('data'=>$temp,'type'=>'data:image/jpeg;base64'), ASSET_S3_BUCKET_NAME, $target_file2, S3_Upload::ACL_PUBLIC_READ)){
					$ci->user_model->update_details(RIDES,array('map_created' => 'Yes'), array('ride_id' => $ride_id));
				} else {
					$ci->user_model->update_details(RIDES,array('map_created' => 'No'), array('ride_id' => $ride_id));
				}
            }
		}
	}
	
    if (!function_exists('get_all_categoty_eta_of_location')){
		function get_all_categoty_eta_of_location($location=array(),$etaVals=array(),$fare_type='Normal',$AirFareInfo=array()){
			$ci =& get_instance();
            $peak_time_amount = 1;
            $night_charge_amount = 1;
            $etaArr = array(); 
            if(!empty($location['result'][0]) && !empty($etaVals)){
                @extract($etaVals);
                if(isset($location['result'][0]['avail_category'])){
                    $avail_categoryArr = $location['result'][0]['avail_category'];
                    foreach($avail_categoryArr as $category){
                        
                    
                        if ($location['result'][0]['peak_time'] == 'Yes') {
                            $time1 = strtotime($pickup_date . ' ' . $location['result'][0]['peak_time_frame']['from']);
                            $time2 = strtotime($pickup_date . ' ' . $location['result'][0]['peak_time_frame']['to']);
                            $ptc = FALSE;
                            if ($time1 > $time2) {
                                if (date('a', $pickup_datetime) == 'PM') {
                                    if (($time1 <= $pickup_datetime) && (strtotime('+1 day', $time2) >= $pickup_datetime)) {
                                        $ptc = TRUE;
                                    }
                                } else {
                                    if ((strtotime('-1 day', $time1) <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
                                        $ptc = TRUE;
                                    }
                                }
                            } else if ($time1 < $time2) {
                                if (($time1 <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
                                    $ptc = TRUE;
                                }
                            }
                            if ($ptc) {
                                $peak_time_amount = $location['result'][0]['fare'][$category]['peak_time_charge'];
                            }
                        }
                        if ($location['result'][0]['night_charge'] == 'Yes') {
                            $time1 = strtotime($pickup_date . ' ' . $location['result'][0]['night_time_frame']['from']);
                            $time2 = strtotime($pickup_date . ' ' . $location['result'][0]['night_time_frame']['to']);
                            $nc = FALSE;
                            if ($time1 > $time2) {
                                if (date('a', $pickup_datetime) == 'PM') {
                                    if (($time1 <= $pickup_datetime) && (strtotime('+1 day', $time2) >= $pickup_datetime)) {
                                        $nc = TRUE;
                                    }
                                } else {
                                    if ((strtotime('-1 day', $time1) <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
                                        $nc = TRUE;
                                    }
                                }
                            } else if ($time1 < $time2) {
                                if (($time1 <= $pickup_datetime) && ($time2 >= $pickup_datetime)) {
                                    $nc = TRUE;
                                }
                            }
                            if ($nc) {
                                $night_charge_amount = $location['result'][0]['fare'][$category]['night_charge'];
                            }
                        }
                        
                        $min_amount = floatval($location['result'][0]['fare'][$category]['min_fare']);
                       
                        if (floatval($location['result'][0]['fare'][$category]['min_time']) < floatval($minduration)) {
                            $ride_fare = 0;
                            $ride_time = floatval($minduration) - floatval($location['result'][0]['fare'][$category]['min_time']);
                            $ride_fare = $ride_time * floatval($location['result'][0]['fare'][$category]['per_minute']);
                            $min_amount = $min_amount + $ride_fare;
                        } 
                        if (floatval($location['result'][0]['fare'][$category]['min_km']) < floatval($mindistance)) {
                            $after_fare = 0;
                            $ride_time = floatval($mindistance) - floatval($location['result'][0]['fare'][$category]['min_km']);
                            $after_fare = $ride_time * floatval($location['result'][0]['fare'][$category]['per_km']);
                            $min_amount = $min_amount + $after_fare;
                        } 
						
						/**  updated **/
						if($nc && $ptc){
							$night_surge = $min_amount * $night_charge_amount;
							$peak_surge = $min_amount * $peak_time_amount;
							$min_amount = $night_surge + $peak_surge ;
						} else {
							$min_amount = $min_amount * $night_charge_amount;
							$min_amount = $min_amount * $peak_time_amount;
						} 
						/**********/
						
						if (isset($location['result'][0]['fare'][$category]['minimum_fare'])) {
							if($location['result'][0]['fare'][$category]['minimum_fare'] > $min_amount){
								$min_amount = $location['result'][0]['fare'][$category]['minimum_fare'];
							}
						}
						if (isset($location['result'][0]['fare'][$category]['booking_fee'])) {
							$min_amount = $min_amount + $location['result'][0]['fare'][$category]['booking_fee'];
						}
						
						
                        $max_amount = $min_amount + ($min_amount*0.01*30);
                        $est_amount = $min_amount + ($min_amount*0.01*15);
                        
                        if($platform == 'landEst'){
                            $etaArr[$category] = array(
                                'min_amount' => number_format($min_amount,0),
                                'max_amount' => number_format($max_amount,0),
                                'est_amount' => number_format($est_amount,0)
                            );

                        } else {
                            $etaArr[$category] = array(
                                'min_amount' => number_format($min_amount, 2),
                                'max_amount' => number_format($max_amount, 2),
                                'est_amount' => number_format($est_amount, 2)
                            );
                        }
                    }
                }
            } 
            return $etaArr;
		}
	}
	
	
	if (!function_exists('sent_ride_request')){
		function sent_ride_request($ride_id='',$push_and_driver=array(),$push_ios_driver=array(),$options=array(),$pickupArr=array(),$userName='',$addon_id='',$distance_unit='mi',$ride_type='Normal',$drop=''){ 
			$ci =& get_instance();	
			$ride_type=ucfirst($ride_type);
			$checkRide = $ci->app_model->get_all_details(RIDES,array('ride_id'=>$ride_id));
			#echo "<pre>"; print_r($locationsList->result()); die;
			 if($ride_type=='Outstation'){
				 
                    if(isset($checkRide->row()->outstation_type) && $checkRide->row()->outstation_type!=''){
                        $ride_type="Outstation(".ucfirst($checkRide->row()->outstation_type)." Trip)";
                    }
                }
				$ride_check_type='Normal';
				if(isset($checkRide->row()->pool_ride)){
					if($checkRide->row()->pool_ride=='Yes'){
						$ride_check_type='Share';
					}
				}
				if(isset($checkRide->row()->ride_type)){
					if($checkRide->row()->ride_type!=''){
						$ride_check_type=$checkRide->row()->ride_type;
					}
				}
				$booking_notes='';
				if(isset($checkRide->row()->booking_notes)){
					if($checkRide->row()->booking_notes!=''){
						$booking_notes=$checkRide->row()->booking_notes;
					}
				}
				#echo "<pre>"; print_r($options); die;
			 if(isset($checkRide->row()->booking_information['est_amount']) && $checkRide->row()->booking_information['est_amount']!=''){
					$options[4] = $checkRide->row()->booking_information['est_amount'];
                } 
			$category='';
            if(isset($checkRide->row()->booking_information['service_type']) && $checkRide->row()->booking_information['service_type']!=''){
                $category=$checkRide->row()->booking_information['service_type'];
            }
			$dropAddress = '';
			if(isset($drop) && $drop != ''){
				$dropAddress = $drop;
			}
			#echo "<pre>"; print_r($push_ios_driver); die;
			if (!empty($push_and_driver)) {
				foreach ($push_and_driver as $keys => $value) {
					$driver_id = $value['id'];
					$driver_locArr = $value['loc'];
					$messaging_status = ''; if(isset($value['messaging_status'])) $messaging_status = $value['messaging_status'];
					$distanceInfo = $ci->get_google_distance_between_two_points($driver_locArr,$pickupArr,$distance_unit);
					$options[6] = $distanceInfo['mindistance'];
					$options[7] = $distanceInfo['mindurationtext'];
					$options[8]= $userName;
					$options[9]= $addon_id;
					$options[10]= (string)time();
					$driver_Msg = $ci->format_string("Request for pickup user","request_pickup_user", '', 'driver', (string)$driver_id);
					
					$reqHisArr =array('driver_id'=> MongoID($driver_id),
						'driver_loc'=>$driver_locArr,
						'requested_time'=>MongoDATE(time()),
						'distance'=> $distanceInfo['mindistance'],
						'ride_id'=> (string)$ride_id,
						'status'=>'sent',
						'messaging_status' => $messaging_status
					 );
					$ci->app_model->simple_insert(RIDE_REQ_HISTORY, $reqHisArr);
					$ack_id = $ci->mongo_db->insert_id();
					$options[11]= (string) $ack_id;
					$options[12]= (string) $ride_type;
					$options[13]= (string) $dropAddress;
					$options[14]=  (string) $category;
					$options[15] = '';
					if($value['mode'] != '' && $value['mode'] == 'Booked' && $value['ride_type'] != '' && $value['ride_type'] !='Share'){
					 $options[15] = 'tailing';	
					}
					$options[16]=$ride_check_type;
					$options[17]=$booking_notes;
					$ci->sendPushNotification($keys, $driver_Msg , 'ride_request', 'ANDROID', $options, 'DRIVER');
					$condition = array('_id' => MongoID($driver_id));
					$ci->mongo_db->where($condition)->inc('req_received', 1)->update(DRIVERS);
				}
			}
			if (!empty($push_ios_driver)) {
				foreach ($push_ios_driver as $keys => $value) {
					$driver_id = $value['id'];
					$driver_locArr = $value['loc'];
					$messaging_status = ''; if(isset($value['messaging_status'])) $messaging_status = $value['messaging_status'];
					$distanceInfo = $ci->get_google_distance_between_two_points($driver_locArr,$pickupArr,$distance_unit);
					$options[6] = $distanceInfo['mindistance'];
					$options[7] = $distanceInfo['mindurationtext'];
					$options[8]=$userName;
					$options[9]=$addon_id;
					$options[10]= (string)time();
					$driver_Msg = $ci->format_string("Request for pickup user","request_pickup_user", '', 'driver', (string)$driver_id);
					
					$reqHisArr =array('driver_id'=> MongoID($driver_id),
						'driver_loc'=>$driver_locArr,
						'requested_time'=>MongoDATE(time()),
						'distance'=> $distanceInfo['mindistance'],
						'ride_id'=> (string)$ride_id,
						'status'=>'sent',
						'messaging_status' => $messaging_status
					 ); 
					 $ci->app_model->simple_insert(RIDE_REQ_HISTORY, $reqHisArr);
					 $ack_id = $ci->mongo_db->insert_id();
					$options[11]= (string) $ack_id;
					$options[12]= (string) $ride_type;
					$options[13]= (string) $dropAddress;
					$options[14] = (string) $category;
					$options[15] = '';
					if($value['mode'] != '' && $value['mode'] == 'Booked' && $value['ride_type'] != '' && $value['ride_type'] !='Share'){
					 $options[15] = 'tailing';	
					}
					$options[16]=$ride_check_type;
					$options[17]=$booking_notes;
					$condition = array('_id' => MongoID($driver_id));
					$ci->mongo_db->where($condition)->inc('req_received', 1)->update(DRIVERS);
					$ci->sendPushNotification($keys, $driver_Msg, 'ride_request', 'IOS', $options, 'DRIVER');
				}
			}
		}
	}
	
	
	if (!function_exists('cash_payment_received')){
		function cash_payment_received($driver_id='',$ride_id='',$receivType='manual',$end_status=''){ 
			$ci =& get_instance();	
			$returnArr['status'] = '0';
			$returnArr['response'] = '';
			try {
				if($driver_id == '' || $ride_id == ''){
					$driver_id = (string) $ci->input->post('driver_id');
					$ride_id = (string) $ci->input->post('ride_id');
					$amount = $ci->input->post('amount');
				}

				if ($driver_id != '' && $ride_id != '') {
					$driverChek = $ci->app_model->get_all_details(DRIVERS, array('_id' => MongoID($driver_id)));
				#	echo "<pre>";print_R($driverChek->row()->push_notification);die;
					if ($driverChek->num_rows() > 0) {
						$checkRide = $ci->app_model->get_all_details(RIDES, array('ride_id' => $ride_id, 'driver.id' => $driver_id));
						if ($checkRide->num_rows() == 1) {
							$paid_amount = 0.00;
							$tips_amount = 0.00;
							
							
							if (isset($checkRide->row()->total['tips_amount'])) {
								$tips_amount = $checkRide->row()->total['tips_amount'];
							}
							
							if (isset($checkRide->row()->total)) {
								if (isset($checkRide->row()->total['grand_fare']) && isset($checkRide->row()->total['wallet_usage'])) {
									$paid_amount = ($checkRide->row()->total['grand_fare']+ $tips_amount) - $checkRide->row()->total['wallet_usage'];
									$paid_amount = round($paid_amount,2);
								}
							}
							$pay_summary = 'Cash';
							if (isset($checkRide->row()->pay_summary)) {
								if ($checkRide->row()->pay_summary != '') {
									if ($checkRide->row()->pay_summary != 'Cash') {
										if($checkRide->row()->pay_summary['type']!="Cash"){
											$pay_summary = $checkRide->row()->pay_summary['type'] . '_Cash';
										}
									}
								} else {
									$pay_summary = 'Cash';
								}
							}
							$pay_summary = array('type' => $pay_summary);
							$paymentInfo = array('ride_status' => 'Completed',
								'pay_status' => 'Paid',
								'history.pay_by_cash_time' => MongoDATE(time()),
								'total.paid_amount' => round(floatval($paid_amount), 2),
								'pay_summary' => $pay_summary
							);
							if($driverChek->row()->upcoming_ride!=''){
									$avail_data = array('mode' => 'Booked', 'availability' => 'Yes');
									$ci->app_model->update_details(DRIVERS, $avail_data, array('_id' => MongoID($driver_id)));
								}else{
									$avail_data = array('mode' => 'Available', 'availability' => 'Yes');
								}
							//if($checkRide->row()->pay_status !="Paid"){
								
								/*** Debit Site Commission from Driver Wallet ***/
								
								if(isset($checkRide->row()->amount_commission) && $checkRide->row()->amount_commission > 0){
									$commissionAmt = $checkRide->row()->amount_commission;
									
									if(isset($checkRide->row()->total['coupon_discount'])){
										$commissionAmt = $commissionAmt - $checkRide->row()->total['coupon_discount'];
									}
									if($commissionAmt > 0){
										$wallet_amount = 0;
										if(isset($driverChek->row()->wallet_amount)){
											$wallet_amount = $driverChek->row()->wallet_amount;
										}
										$bal_walletamount=($wallet_amount-$commissionAmt);
										$txn_time = time();
										$initialAmt = array('type' => 'DEBIT',
															'debit_type' => 'payment',
														   'description' => 'Commission for ride #'.$ride_id,
														   'ref_id' => $ride_id,
														   'trans_amount' => floatval($commissionAmt),
														   'avail_amount' => floatval($bal_walletamount),
														   'trans_date' => MongoDATE(time()),
														   'trans_id' => $txn_time,
														   'driver_id' => MongoID($driver_id)
										);
										$ci->app_model->simple_insert(DRIVER_WALLET_TRANSACTION,$initialAmt);
										$ci->app_model->update_wallet((string) $driver_id, 'DEBIT', floatval($commissionAmt),'driver');
										$paymentInfo['payout_status'] = 'Yes';
									}
								}
								/************************************************/
								
								$ci->app_model->update_details(RIDES, $paymentInfo, array('ride_id' => $ride_id));
								/* Update Stats Starts */
								$current_date = MongoDATE(strtotime(date("Y-m-d 00:00:00")));
								$field = array('ride_completed.hour_' . date('H') => 1, 'ride_completed.count' => 1);
								$ci->app_model->update_stats(array('day_hour' => $current_date), $field, 1);
								/* Update Stats End */
								if($driverChek->row()->upcoming_ride!=''){
									$avail_data = array('mode' => 'Booked', 'availability' => 'Yes');
									$ci->app_model->update_details(DRIVERS, $avail_data, array('_id' => MongoID($driver_id)));
								}else{
									$avail_data = array('mode' => 'Available', 'availability' => 'Yes');
								}
								$ci->app_model->update_details(DRIVERS, $avail_data, array('_id' => MongoID($driver_id)));
								$trans_id = time() . rand(0, 2578);
								$transactionArr = array('type' => 'cash',
									'amount' => floatval($paid_amount),
									'trans_id' => $trans_id,
									'trans_date' => MongoDATE(time())
								);
								$ci->app_model->simple_push(PAYMENTS, array('ride_id' => $ride_id), array('transactions' => $transactionArr));

								$user_id = $checkRide->row()->user['id'];
								$userVal = $ci->app_model->get_selected_fields(USERS, array('_id' => MongoID($user_id)), array('_id', 'user_name', 'email', 'image', 'avg_review', 'phone_number', 'country_code', 'push_type', 'push_notification_key'));
								#echo "<pre>"; print_r($userVal); die;
								if (isset($userVal->row()->push_type)) {
									if ($userVal->row()->push_type != '') {
										
										$message=$ci->format_string("Your payment was successful.","your_payment_successful",'','user', (string)$userVal->row()->_id);
										
										$options = array('ride_id' => (string) $ride_id, 'user_id' => (string) $user_id);
										if ($userVal->row()->push_type == 'ANDROID') {
											if (isset($userVal->row()->push_notification_key['gcm_id'])) {
												if ($userVal->row()->push_notification_key['gcm_id'] != '') {
													$ci->sendPushNotification(array($userVal->row()->push_notification_key['gcm_id']), $message, 'payment_paid', 'ANDROID', $options, 'USER');
												}
											}
										}
										if ($userVal->row()->push_type == 'IOS') {
											if (isset($userVal->row()->push_notification_key['ios_token'])) {
												if ($userVal->row()->push_notification_key['ios_token'] != '') {
													$ci->sendPushNotification(array($userVal->row()->push_notification_key['ios_token']), $message, 'payment_paid', 'IOS', $options, 'USER');
												}
											}
										}
									}
								}
								if($end_status=='Manual'){
									if (isset($driverChek->row()->push_notification)) {
										#echo "test";die;
										if ($driverChek->row()->push_notification != '') {
											$message = $ci->format_string('Your Trip has been completed', 'your_trip_has_been_completed', '', 'driver', (string)$driver_id);
											$options = array('ride_id' => (string) $ride_id, 'driver_id' => $driver_id);
											if (isset($driverChek->row()->push_notification['type'])) {
												if ($driverChek->row()->push_notification['type'] == 'ANDROID') {
													if (isset($driverChek->row()->push_notification['key'])) {
														if ($driverChek->row()->push_notification['key'] != '') {
															$ci->sendPushNotification($driverChek->row()->push_notification['key'], $message, 'ride_completed', 'ANDROID', $options, 'DRIVER');
														}
													}
												}
												if ($driverChek->row()->push_notification['type'] == 'IOS') {
													if (isset($driverChek->row()->push_notification['key'])) {
														if ($driverChek->row()->push_notification['key'] != '') {
															$ci->sendPushNotification($driverChek->row()->push_notification['key'], $message, 'ride_completed', 'IOS', $options, 'DRIVER');
														}
													}
												}
											}
										}
									}
								}
								
								#	make and sending invoice to the rider 	#
								$ci->app_model->update_ride_amounts($ride_id);
								$fields = array(
									'ride_id' => (string) $ride_id
								);
								$url = base_url().'prepare-invoice';
								$ci->load->library('curl');
								$output = $ci->curl->simple_post($url, $fields);
								
							//}

							$returnArr['status'] = '1';
							$returnArr['response'] = $ci->format_string('amount received', 'amount_received');
						} else {
							$returnArr['response'] = $ci->format_string("Invalid Ride", "invalid_ride");
						}
					} else {
						$returnArr['response'] = $ci->format_string("Driver not found", "driver_not_found");
					}
				} else {
					$returnArr['response'] = $ci->format_string("Some Parameters Missing", "some_parameters_missing");
				}
			} catch (MongoException $ex) {
				$returnArr['response'] = $ci->format_string("Error in connection", "error_in_connection");
			}
			
			if($receivType == 'manual'){
				$json_encode = json_encode($returnArr, JSON_PRETTY_PRINT);
				echo $ci->cleanString($json_encode);
			} else {
				return $returnArr;
			}
			
		}
	}
	
/* End of file ride_helper.php */
/* Location: ./application/helpers/ride_helper.php */


