<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
 *
 * This model contains all db functions related to SMS sending
 * @author Casperon
 *
 */

class Sms_model extends My_Model{
	public function __construct(){
        parent::__construct();
		$this->load->library(array('twilio'));	
    }
	
	/**
	*
	* This function sends the otp on registration account
	* @param String $phone_code
	* @param String $phone_number
	* @param String $otp_number
	*
	**/
	public function opt_for_registration($phone_code='',$phone_number='',$otp_number='',$lang_code = 'en'){
		if($phone_code!='' || $phone_number!='' || $otp_number!=''){
			if(substr($phone_code,0,1) == '+'){
				$phone_code = $phone_code;
			} else {
				$phone_code = '+'.$phone_code;
			}
			$from = $this->config->item('twilio_number');
			$to = $phone_code.$phone_number;
			
			$message = get_sms_content('passanger_registration_otp');
			$message = str_replace('{$otp_number}',$otp_number,$message);
			$this->send_common_sms($from, $to, $message);
		}
	}
	
	
	
	
	/**
	*
	* This function sends the otp regarding the ride
	* @param String $ride_id
	*
	**/
	public function opt_for_ride($ride_id=''){
		if($ride_id!=''){
			$checkRide = $this->get_all_details(RIDES,array('ride_id'=>$ride_id));
			if($checkRide->num_rows() == 1){
				$user_id = $checkRide->row()->user['id'];
				$userVal = $this->get_selected_fields(USERS,array('_id'=>MongoID($user_id)),array('country_code','phone_number','email','user_name'));
				if($userVal->num_rows() == 1){
					$phone_code = $userVal->row()->country_code;
					$phone_number = $userVal->row()->phone_number;
					if(substr($phone_code,0,1) == '+'){
						$phone_code = $phone_code;
					} else {
						$phone_code = '+'.$phone_code;
					}
					$otp_number=rand(1000,9999); 
					$from = $this->config->item('twilio_number');
					$to = $phone_code.$phone_number;
					
					$message = get_sms_content('ride_otp_sms');
					$message = str_replace('{$ride_id}',$ride_id,$message);
					$message = str_replace('{$otp_number}',$otp_number,$message);
                    
					$this->send_common_sms($from, $to, $message);
					$condition=array('ride_id'=>$ride_id);
					$otp_array=array('ride_otp'=>(string)$otp_number);
					$this->update_details(RIDES,$otp_array,$condition);  
				}
			}
		}
	}
	
	/**
	*
	* This function sends the SMS on drived reached rider's location
	* @param String $ride_id
	*
	**/
	public function sms_on_driver_arraival($ride_id=''){
		if($ride_id!=''){
			$checkRide = $this->get_all_details(RIDES,array('ride_id'=>$ride_id));
			if($checkRide->num_rows() == 1){
				$user_id = $checkRide->row()->user['id'];
				$userVal = $this->get_selected_fields(USERS,array('_id'=>MongoID($user_id)),array('country_code','phone_number','email','user_name'));
				if($userVal->num_rows() == 1){
					$phone_code = $userVal->row()->country_code;
					$phone_number = $userVal->row()->phone_number;
					if(substr($phone_code,0,1) == '+'){
						$phone_code = $phone_code;
					} else {
						$phone_code = '+'.$phone_code;
					}
					$from = $this->config->item('twilio_number');
					$to = $phone_code.$phone_number;
					
					$message = get_sms_content('cab_arrived');
					$message = str_replace('{$ride_id}',$ride_id,$message);
                    
					$this->send_common_sms($from, $to, $message);
				}
			}
		}
	}
	
	/**
	*
	* This function sends the otp on registration account
	* @param String $phone_code
	* @param String $phone_number
	*
	**/
	public function send_sms_share_driver_tracking_location($mobile_no='',$location='',$user_name='',$ride_id=''){
		if($mobile_no != ''){
			$trackLink = base_url().'track?rideId='.$ride_id;
			$from = $this->config->item('twilio_number');
			$to = $mobile_no;
			$user_name = ucfirst($user_name);
			$site_name = strtolower($this->config->item('email_title'));
						
			$message = get_sms_content('sharing_ride');
			$message = str_replace('{$user_name}',$user_name,$message);
			$message = str_replace('{$trackLink}',$trackLink,$message);
			$message = str_replace('{$site_name}',$site_name,$message);
						
			$this->send_common_sms($from, $to, $message);
		}
	}
	
	
	/**
	*
	* This function sends the otp on driver registration account
	* @param String $phone_code
	* @param String $phone_number
	* @param String $otp_number
	*
	**/
	public function opt_for_driver_registration($phone_code='',$phone_number='',$otp_number='',$lang_code = 'en'){
		if($phone_code!='' || $phone_number!='' || $otp_number!=''){
			if(substr($phone_code,0,1) == '+'){
				$phone_code = $phone_code;
			} else {
				$phone_code = '+'.$phone_code;
			}
			$from = $this->config->item('twilio_number');
			$to = $phone_code.$phone_number;
						
			$message = get_sms_content('driver_registration_otp');
			$message = str_replace('{$otp_number}',$otp_number,$message);
			
            $this->send_common_sms($from, $to, $message); 
		}
	}
	
	/**
	*
	* This sends sms to the user when admin added amount
	* @param String $phone_code
	* @param String $phone_number
	*
	**/
	public function send_wallet_money_credit_sms($phone_code='',$phone_number='',$user_name='',$amt=0,$tot_amt=0){
		if($phone_code!='' || $phone_number!='') {
			if(substr($phone_code,0,1) == '+'){
				$phone_code = $phone_code;
			} else {
				$phone_code = '+'.$phone_code;
			}
			$from = $this->config->item('twilio_number');
			$to = $phone_code.$phone_number;
            $amt = $this->config->item('currency_code').' '.$amt;
			$balance_amt = $this->config->item('currency_code').' '.$tot_amt;
			
			$message = get_sms_content('wallet_money_credit');
			$message = str_replace('{$user_name}',$user_name,$message);
			$message = str_replace('{$amt}',$amt,$message);
			$message = str_replace('{$balance_amt}',$balance_amt,$message);
		
			$this->send_common_sms($from, $to, $message);
		}
	}
	
	/**
	*
	* This sends sms to the user when admin added amount
	* @param String $phone_code
	* @param String $phone_number
	*
	**/
	public function send_wallet_money_debit_sms($phone_code='',$phone_number='',$user_name='',$amt=0,$tot_amt=0){
		if($phone_code!='' || $phone_number!='') {
			if(substr($phone_code,0,1) == '+'){
				$phone_code = $phone_code;
			} else {
				$phone_code = '+'.$phone_code;
			}
			$from = $this->config->item('twilio_number');
			$to = $phone_code.$phone_number;
            
			$amt = $this->config->item('currency_code').' '.$amt;
			$balance_amt = $this->config->item('currency_code').' '.$tot_amt;
			
			$message = get_sms_content('wallet_money_debit');
			$message = str_replace('{$user_name}',$user_name,$message);
			$message = str_replace('{$amt}',$amt,$message);
			$message = str_replace('{$balance_amt}',$balance_amt,$message);
		
			$this->send_common_sms($from, $to, $message);
		}
	}
    
    
    public function otp_for_payment_confirmation($phone_code='',$phone_number='',$otp_number=''){
		if($phone_code!='' || $phone_number!='' || $otp_number!=''){
			if(substr($phone_code,0,1) == '+'){
				$phone_code = $phone_code;
			} else {
				$phone_code = '+'.$phone_code;
			}
			$from = $this->config->item('twilio_number');
			$to = $phone_code.$phone_number;
			
			$message = get_sms_content('ride_otp_for_payment');
			$message = str_replace('{$otp_number}',$otp_number,$message);
			
			#$this->send_common_sms($from, $to, $message);
		}
	}
        
	public function send_common_sms($from = '',$to = '', $message = ''){
		$from = $this->config->item('afro_message_number');
        if($from != '' && $to != '' && $message != ''){
			$afro_message_mode        = $this->config->item('afro_message_account_type');
			$afro_message_auth_token = $this->config->item('afro_message_auth_token');
			$afro_message_number      = $this->config->item('afro_message_number');
			if($afro_message_mode=="prod"){
				try{
					$ch = curl_init();
					// base url
					$url = 'https://api.afromessage.com/api/send';
					$token = $afro_message_auth_token;
					$to = $to;
					$from = $afro_message_number;
					$sender = curl_escape($ch,'Go Trip');
					// message should be URL encoded
					$message = curl_escape($ch,$message);
					$callback = '';

					/** set request options **/
					curl_setopt($ch, CURLOPT_URL, $url . '?from=' . $from . '&sender=' .$sender . '&to=' . $to . '&message=' . $message . '&callback=' . $callback);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

					/** Add headers **/
					$headers = array();
					$headers[] = 'Authorization: Bearer '.$token;
					curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

					/** Execute request **/
					$result = curl_exec($ch);
					/** Handle response **/
					if (curl_errno($ch)) {
						// general http error
						return 'Error:' . curl_error($ch);
					} else {	
						$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
						switch ($http_code) {
						case 200:
								/** response object ... inspect the `acknowledge` node and act accordingly **/
								$data = json_decode($result,true);
								if ($data['acknowledge'] == 'success') {
									return "Api success";
								}else{
									return "Api failure";
								}
								break;
						default:
						  /** most probably authorization error ... inspect response**/
						  return 'Other HTTP Code: '. $http_code;
						}
					}
					/** finish call **/
					curl_close ($ch);
									  
					
				}catch(Exception $e){
					$response = $e->getMessage();
				}
			}
			#var_dump($response);
        }
    }
	
	public function send_common_sms_old($from = '',$to = '', $message = ''){
		$from = $this->config->item('twilio_number');
        if($from != '' && $to != '' && $message != ''){
			$twilio_mode        = $this->config->item('twilio_account_type');
			$twilio_account_sid = $this->config->item('twilio_account_sid');
			$twilio_auth_token  = $this->config->item('twilio_auth_token');
			$twilio_number      = $this->config->item('twilio_number');
			if($twilio_mode=="prod"){
				try{
					require_once(APPPATH.'/third_party/twilio/Services/Twilio.php'); 
					$account_sid = $twilio_account_sid; 
					$auth_token = $twilio_auth_token; 
					$client = new Services_Twilio($account_sid, $auth_token);
					return $response = $client->account->messages->sendMessage($twilio_number,$to,$message);
					
				}catch(Exception $e){
					$response = $e->getMessage();
				}
			}
			#var_dump($response);
        }
    }	
	public function send_dispatcher_booking_confirmation_sms($infoArr = array()){

			$ride_id = (string)$infoArr['ride_id'];
			$phone_code = $infoArr['phone_code'];
			$phone_number = $infoArr['phone_number'];

			if($phone_code!='' || $phone_number!='' || $ride_id!=''){
					if(substr($phone_code,0,1) == '+'){
							$phone_code = $phone_code;
					} else {
							$phone_code = '+'.$phone_code;
					}
					$from = $this->config->item('twilio_number');
					$to = $phone_code.$phone_number;
					
					$message = get_sms_content('ride_booked_dispatcher');
					$message = str_replace('{$ride_id}',$ride_id,$message);
			
					$response = $this->send_common_sms($from, $to, $message); 
			}
	}
	
	
	public function passanger_change_mobile_otp($infoArr = array()){
		$user_name = (string)$infoArr['user_name'];
		$otp_number = (string)$infoArr['otp_number'];
		$phone_code = $infoArr['phone_code'];
		$phone_number = $infoArr['phone_number'];

		if($phone_code!='' || $phone_number!='' || $otp_number!='' || $user_name!=''){
			if(substr($phone_code,0,1) == '+'){
				$phone_code = $phone_code;
			} else {
				$phone_code = '+'.$phone_code;
			}
			$from = $this->config->item('twilio_number');
			$to = $phone_code.$phone_number;

			$message = get_sms_content('passanger_change_mobile_otp');
			$message = str_replace('{$user_name}',$user_name,$message);
			$message = str_replace('{$otp_number}',$otp_number,$message);

			$response = $this->send_common_sms($from, $to, $message); 
		}
	}
	
	public function passanger_reset_password_otp($infoArr = array()){
		$user_name = (string)$infoArr['user_name'];
		$code = (string)$infoArr['code'];
		$phone_code = $infoArr['phone_code'];
		$phone_number = $infoArr['phone_number'];

		if($phone_code!='' || $phone_number!='' || $code!='' || $user_name!=''){
			if(substr($phone_code,0,1) == '+'){
				$phone_code = $phone_code;
			} else {
				$phone_code = '+'.$phone_code;
			}
			$from = $this->config->item('twilio_number');
			$to = $phone_code.$phone_number;

			$message = get_sms_content('passanger_reset_password_otp');
			$message = str_replace('{$user_name}',$user_name,$message);
			$message = str_replace('{$code}',$code,$message);

			$response = $this->send_common_sms($from, $to, $message); 
		}
	}
	
	public function emergency_contact_update($infoArr = array()){
		$em_user_name = (string)$infoArr['em_user_name'];
		$user_name = (string)$infoArr['user_name'];
		$otp_number = (string)$infoArr['otp_number'];
		$phone_code = $infoArr['phone_code'];
		$phone_number = $infoArr['phone_number'];

		if($phone_code!='' || $phone_number!='' || $user_name!='' || $em_user_name!='' || $otp_number!=''){
			if(substr($phone_code,0,1) == '+'){
				$phone_code = $phone_code;
			} else {
				$phone_code = '+'.$phone_code;
			}
			$from = $this->config->item('twilio_number');
			$to = $phone_code.$phone_number;

			$message = get_sms_content('emergency_contact_update');
			$message = str_replace('{$em_user_name}',$em_user_name,$message);
			$message = str_replace('{$user_name}',$user_name,$message);
			$message = str_replace('{$otp_number}',$otp_number,$message);

			$response = $this->send_common_sms($from, $to, $message); 
		}
	}
	
	public function emergency_alert($infoArr = array()){
		$em_user_name = (string)$infoArr['em_user_name'];
		$user_name = (string)$infoArr['user_name'];
		$phone_code = $infoArr['phone_code'];
		$phone_number = $infoArr['phone_number'];

		if($phone_code!='' || $phone_number!='' || $user_name!='' || $em_user_name!='' || $otp_number!=''){
			if(substr($phone_code,0,1) == '+'){
				$phone_code = $phone_code;
			} else {
				$phone_code = '+'.$phone_code;
			}
			$from = $this->config->item('twilio_number');
			$to = $phone_code.$phone_number;

			$message = get_sms_content('emergency_alert');
			$message = str_replace('{$em_user_name}',$em_user_name,$message);
			$message = str_replace('{$user_name}',$user_name,$message);

			$response = $this->send_common_sms($from, $to, $message); 
		}
	}
	
	public function send_split_cost_request_sms($smsArr = array()){
		
		$ride_id = $smsArr['ride_id'];
		$phone_code = $smsArr['rec_dail_code'];
		$phone_number = $smsArr['rec_phone_number'];
		$user_name = $smsArr['user_name'];

		if($ride_id != '' && $phone_number!='' || $phone_code!=''){
			if(substr($phone_code,0,1) == '+'){
				$phone_code = $phone_code;
			} else {
				$phone_code = '+'.$phone_code;
			}
			$to = $phone_code.$phone_number;
			
			$ride_continue_url = base_url().'rsplit/'.$ride_id;
			$message = 'Hi, '.$user_name.' wants to split ride fare with you. Confirm '.$ride_continue_url;
            $message = 'Split fare request. Click to confirm '.$ride_continue_url; 
            
			$response = $this->send_common_sms("", $to, $message,'split_request_send'); 

			return '1';
		} else {
			return '0';
		}
	}
	
		public function send_gift_ride_request_sms($smsArr = array()){
		
		$ride_id = $smsArr['ride_id'];
		$phone_code = $smsArr['rec_dail_code'];
		$phone_number = $smsArr['rec_phone_number'];
		$user_name = $smsArr['user_name'];

		if($ride_id != '' && $phone_number!='' || $phone_code!=''){
			if(substr($phone_code,0,1) == '+'){
				$phone_code = $phone_code;
			} else {
				$phone_code = '+'.$phone_code;
			}
			$to = $phone_code.$phone_number;
			
			$ride_continue_url = base_url().'rgift/'.$ride_id;
			$message = 'Hi, '.$user_name.' wants to gift ride with you. Confirm '.$ride_continue_url;
            $message = 'Gift ride request. Click to confirm '.$ride_continue_url; 
            
			$response = $this->send_common_sms("", $to, $message,'gift_ride_request_send'); 

			return '1';
		} else {
			return '0';
		}
	}
    
    public function split_cost_accept_confirmation($smsArr = array()){
		$phone_code = $smsArr['sender_dail_code'];
		$phone_number = $smsArr['sender_phone_number'];
		$user_name = $smsArr['user_name'];
		
		if($phone_number !='' || $phone_code!=''){
			if(substr($phone_code,0,1) == '+'){
				$phone_code = $phone_code;
			} else {
				$phone_code = '+'.$phone_code;  
			}
			$to = $phone_code.$phone_number; 
			$message = 'Hi, '.$user_name.' accepted your split fare request';
			$response = $this->send_common_sms("", $to, $message,'split_request_accept'); 
			return '1';
		} else {
			return '0';
		}
	}
	
	public function gift_ride_accept_confirmation($smsArr = array()){
		$phone_code = $smsArr['sender_dail_code'];
		$phone_number = $smsArr['sender_phone_number'];
		$user_name = $smsArr['user_name'];
		
		if($phone_number !='' || $phone_code!=''){
			if(substr($phone_code,0,1) == '+'){
				$phone_code = $phone_code;
			} else {
				$phone_code = '+'.$phone_code;  
			}
			$to = $phone_code.$phone_number; 
			$message = 'Hi, '.$user_name.' accepted your gift ride request';
			$response = $this->send_common_sms("", $to, $message,'gift_ride_accept'); 
			return '1';
		} else {
			return '0';
		}
	}
    
    public function split_cost_reject_confirmation($smsArr = array()){
		$phone_code = $smsArr['sender_dail_code'];
		$phone_number = $smsArr['sender_phone_number'];
		$user_name = $smsArr['user_name'];
		
		if($phone_number !='' || $phone_code!=''){
			if(substr($phone_code,0,1) == '+'){
				$phone_code = $phone_code;
			} else {
				$phone_code = '+'.$phone_code;
			}
			$to = $phone_code.$phone_number;
			$message = 'Hi, '.$user_name.' rejected your split fare request';
			$response = $this->send_common_sms("", $to, $message,'split_request_rejected'); 
			return '1';
		} else {
			return '0';
		}
	}
	
	  public function gift_ride_reject_confirmation($smsArr = array()){
		$phone_code = $smsArr['sender_dail_code'];
		$phone_number = $smsArr['sender_phone_number'];
		$user_name = $smsArr['user_name'];
		
		if($phone_number !='' || $phone_code!=''){
			if(substr($phone_code,0,1) == '+'){
				$phone_code = $phone_code;
			} else {
				$phone_code = '+'.$phone_code;
			}
			$to = $phone_code.$phone_number;
			$message = 'Hi, '.$user_name.' rejected your gift ride request';
			$response = $this->send_common_sms("", $to, $message,'gift_ride_request_rejected'); 
			return '1';
		} else {
			return '0';
		}
	}
	
	
	 /**
     * 
     * Ride hailing Begin Trip or End Trip sms Send For User
     */
   
	public function ride_hailing_sms_to_user($ride_id='',$ride_status = ''){
		if($ride_id!=''){
			
			$checkRide = $this->get_all_details(RIDES,array('ride_id'=>$ride_id));
			if($checkRide->num_rows() == 1){
				$user_id = $checkRide->row()->user['id'];
				$userVal = $this->get_selected_fields(USERS,array('_id'=>MongoID($user_id)),array('country_code','phone_number','user_name'));
				if($userVal->num_rows() == 1){
					$country_code = $userVal->row()->country_code;
					$phone_number = $userVal->row()->phone_number;
					if(substr($country_code,0,1) == '+'){
						$country_code = $country_code;
					} else {
						$country_code = '+'.$country_code;
					}
					$from = $this->config->item('afro_message_number');
					$to = $country_code.$phone_number;
					$currency_symbol = $this->config->item('currency_symbol');
					$total_amount = $checkRide->row()->total['grand_fare'];
					$driver_name = $checkRide->row()->driver['name'];
					$driver_phone = $checkRide->row()->driver['phone'];
					$vehicle_number = $checkRide->row()->driver['vehicle_no'];
					$total_amount = round($total_amount, 2);
					if($ride_status == 'begin'){
						$message = 'Trip Has Started\nDriver: '. $driver_name . '\nPhone: '.$driver_phone."\nPlate Number: ". $vehicle_number . "\nLet\'s GO!";
					}else{
						$message = 'Trip Has ended your Total price is '.$currency_symbol.$total_amount;
					}
					
					$message = str_replace('{$ride_id}',$ride_id,$message);
					$this->send_common_sms($from, $to, $message);
				}
			}
		}
	}
	
}

?>