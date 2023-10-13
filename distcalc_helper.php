<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


if ( ! function_exists('get_distance_from_latlong')) {
	function get_distance_from_latlong($travel_history="",$ride_id="") {
		$ci =& get_instance();
		$math_ext_distance = 0;
		if($travel_history!=""){
			$travel_history = trim($travel_history,',');
			$travel_historyArr = array();
			$travelRecords = @explode(',',$travel_history);
			$lat = ""; 
			$long = ""; 
			if(count($travelRecords)>1){
				for( $i = 0; $i < count($travelRecords); $i++){
					$splitedHis = @explode(';',$travelRecords[$i]);
					if(isset($splitedHis[0])) $lat = $splitedHis[0];
					if(isset($splitedHis[1])) $long = $splitedHis[1];
					if(is_valid_lat_long($lat,$long)){
						$travel_historyArr[] = array('lat' => $lat,
													 'lon' => $long,
													 'update_time' => MongoDATE(strtotime($splitedHis[2]))
													);
					}
				}
			}
			if(!empty($travel_historyArr)){
				$getRideHIstoryVal = $ci->app_model->get_all_details(TRAVEL_HISTORY,array('ride_id' => (string)$ride_id));
				if($getRideHIstoryVal->num_rows()>0){
					$ci->app_model->update_details(TRAVEL_HISTORY,array('history_end' => $travel_historyArr),array('ride_id' => $ride_id));
				}else{
					$ci->app_model->simple_insert(TRAVEL_HISTORY,array('ride_id' => $ride_id,'history_end' => $travel_historyArr));
				}
			}
			$dis_val_arr = array();
			$val1 = array();
			$val2 = array();
			$getRideHIstory = $ci->app_model->get_all_details(TRAVEL_HISTORY,array('ride_id' => $ride_id));
			if($getRideHIstory->num_rows()>0){
				foreach ($getRideHIstory->result() as $key => $data) {
					$hisMid = array();
					$hisEnd = array();
					if(isset($data->history)){
						$hisMid = $data->history;
					}
					if(isset($data->history_end)){
						$hisEnd = $data->history_end;
					}
					$hisFinal = $hisEnd;
					if(count($hisEnd) > count($hisMid)){
						$hisFinal = $hisEnd;
					}else{
						$hisFinal = $hisMid;
					}
					foreach($hisFinal as $value) {
						if(count($val1)==0){
							$val1[0] = $value['lat'];
							$val1[1] = $value['lon']; 
							$val2[0] = $value['lat'];
							$val2[1] = $value['lon'];
							continue;
						}else{
							$val1[0] = $val2[0];
							$val1[1] = $val2[1]; 
						}
						$val2[0] = $value['lat'];
						$val2[1] = $value['lon'];
						$dis_val_arr[] = round(cal_distance($val1[0], $val1[1], $val2[0], $val2[1]),3);
					}
				}
			}
			$math_ext_distance = array_sum($dis_val_arr);
			if (!is_numeric($lat)){
				$math_ext_distance = 0.00;
			}
		}
		return $math_ext_distance;
	}
}

if ( ! function_exists('is_valid_lat_long')) {
	function is_valid_lat_long($lat,$long){
		if ($lat!="" && $long!="") {
			if (is_numeric($lat) && is_numeric($long)) {
				if ($lat!=0 && $long!=0) {
					return true;
				}
			}
		}
		return false;
	}
}

if ( ! function_exists('cal_distance')) {
	function cal_distance($latitudeFrom=0.00, $longitudeFrom=0.00, $latitudeTo=0.00, $longitudeTo=0.00, $earthRadius = 3959){
		// convert from degrees to radians
		$latFrom = deg2rad($latitudeFrom);
		$lonFrom = deg2rad($longitudeFrom);
		$latTo = deg2rad($latitudeTo);
		$lonTo = deg2rad($longitudeTo);

		$latDelta = $latTo - $latFrom;
		$lonDelta = $lonTo - $lonFrom;

		$angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
		$dis = $angle * $earthRadius * 1.609344;
		if ($dis > 1000) {
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
													$distance_unit = 'km';
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
													$dis =  floatval(round($apxdistance,2));
		}
	}
		return $dis;
	}
}


if ( ! function_exists('cal_hours')) {
	function cal_hours($time, $format = '%02d:%02d')
    {
        if ($time < 1) {
            return;
        }
        $hours = floor($time / 60);
        $minutes = ($time % 60);
        if($hours > 0){ 
            $format=$hours." Hour";
            if($hours > 1){
                $format.="s";
            }
            if($minutes > 0){
                $format.=" ,".$minutes." Minute";
                if($minutes > 1){
                    $format.="s";
                }
            }
            return $format;
        }else{
            $format=$minutes." Minutes";
            return $format;
        }
        
    }
}


if ( ! function_exists('get_google_direction_info')) {
	function get_google_direction_info($from='',$to ='',$distance_unit=''){
		$ci =& get_instance();
		$returnArr['status'] = '0';
		$returnArr['response'] = '';
		if($from != '' && $to != ''){
			$url = 'https://maps.googleapis.com/maps/api/directions/json?origin=' . urlencode($from) . '&destination=' . urlencode($to) . '&alternatives=true&sensor=false&mode=driving'.$ci->data['google_maps_api_key'];
			$gmap = file_get_contents($url);
			$map_values = json_decode($gmap);
			$routes = $map_values->routes;
			if(!empty($routes)){
				#usort($routes, create_function('$a,$b', 'return intval($a->legs[0]->distance->value) - intval($b->legs[0]->distance->value);'));
				$start_address = (string) $routes[0]->legs[0]->start_address;
				$end_address = (string) $routes[0]->legs[0]->end_address;

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
				$mindurationtext = $routes[0]->legs[0]->duration->text;
				$responseArr = array('distance' => $mindistance,
													'duration' => $minduration,
													'duration_text' => $mindurationtext,
													'distance_text' => $routes[0]->legs[0]->distance->text
				);
				$returnArr['status'] = '1';
				$returnArr['response'] = $responseArr;
			}
		}
		return $returnArr;
	}
}
	


/* End of file distcalc_helper.php */
/* Location: ./application/helpers/distcalc_helper.php */