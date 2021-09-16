<?php 
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoggerController extends Controller
{
public function show_message(Request $request){
// GET Request only
if($request->isMethod('get')){
	return response()->json([
	"Error" => "This method is not supported",
	"Action" => "Use a POST request to assign a bus for a trip"
	], 200);
}
}

public function assign_bus_for_trip(Request $request){
	// Assign bus to the trip with validation
	// POST request using JSON format
	// Containing Valid api_token and rf_id
	// Containing client_type -> must be bus_stop
	// Containing valid RF_ID
	// Containing valid trip_id
	// MUST be called by the origin stop of the trip
	if($request->isMethod('post')){
		$content_type = strtolower($request->header('Content-Type'));
		if($content_type=='application/json'){
			$client_type = strtolower($request->input('client_type'));
			if($client_type=="bus_stop"){
				$stop_id = $request->input('stop_id');
				if(checkStopExists($stop_id) && checkApiTokenExists($stop_id, $client_type)){
				$api_token = $request->input('api_token');
					if(verifyApiToken($api_token, $client_type, $stop_id)){
						$rf_id = $request->input('rf_id');
						$bus = getBusByRfId($rf_id);
						if(!is_null($bus)){
							// Bus exists. Valid RFID
							$trip_id = $request->input('trip_id');
							if(checkTripExists($trip_id) && checkTripToStart($trip_id) && checkNoBusAssigned($trip_id)){
								// Trip exists. Not started yet for today. No bus has been assigned yet.
								$trip = getTripbyId($trip_id);
								$route_origin_id = getStopsInRoute($trip->route_id, $trip->is_onward)[0];
								if(strcmp($route_origin, $stop_id)==0){
									// Ensure that the calling stop is the origin of trip
									DB::table('trips')
									->where('trip_id', $trip_id)
									->update(['bus_id' => $api_token_new]);
								}
								else{
									return response()->json([
										"Error" => "Caller must be origin of trip"
										], 400); // Bad Request
								}
							}
							else{
								return response()->json([
									"Error" => "Invalid Trip ID"
									], 400); // Bad Request
							}
						}
						else{
							return response()->json([
								"Error" => "Corrupt RF-ID"
								], 400); // Bad Request
						}
					}
					else{
						return response()->json([
						"Error" => "Invalid or Expired API Token"
						], 401); // Unauthorized
					}
				}
				else{
					return response()->json([
						"Error" => "Invalid or Expired API Token"
					], 401); // Unauthorized
				}
			}
			else{
				return response()->json([
					"Error" => "This Client is Not Allowed"
				], 401);  //Unauthorized
			}
		}
		else{
			return response()->json([
				"Error" => "Unknown format. Use JSON",
			], 400);
		}
	}
}

public function add_log(Request $request){
	// Log the bus_arival time
	// POST request using JSON format
	// Containing Valid api_token and stop_id
	// Containing client_type -> must be bus_stop
	// Containing valid trip_id (CHNAGE TO RF-UID)
	if($request->isMethod('post')){
		$arr_date = date("Y-m-d");
		$arr_time = date("H:i") ; // Log the time as soon as possible
		$content_type = strtolower($request->header('Content-Type'));
		if($content_type=='application/json'){
			$client_type = strtolower($request->input('client_type'));
			if($client_type=="bus_stop"){
				$stop_id = $request->input('stop_id');
				if(checkStopExists($stop_id) && checkApiTokenExists($stop_id, $client_type)){
				$api_token = $request->input('api_token');
					if(verifyApiToken($api_token, $client_type, $stop_id)){
						/* NEW CODE */
						$rf_id = $request->input('rf_id');
						$bus = getBusByRfId($rf_id);
						if(!is_null($bus)){
							// Bus exists. Valid RFID
							$trip = getCurrentTripOfBus($bus->bus_id);
							if(!is_null($trip)){
								$trip_id = $trip->trip_id;
								/* -- */
								//$trip_id = strtoupper($request->input('trip_id')); // Will be empty string if not avl in Request
								if(!is_null($trip_id) && checkTripExists($trip_id)){
									if(checkStopInRoute($trip_id, $stop_id)){
										if(checkSameDayLog($trip_id, $stop_id, $arr_date)){
										// If this trip has been logged already, ignore
										return response()->json([
											"Error" => "This trip was already logged"
										], 208); // Already Reported
										}
										else{
										DB::table('time_logs')->insert([
											'trip_id' => $trip_id,
											'stop_id' => $stop_id,
											'arrival_time' => $arr_time,
											'arrival_date' => $arr_date,
										]);
										$next_stop = getNextStop($trip_id, $stop_id);
										if(!$next_stop){
											// This is the last stop of the route
											;
										}
										else{
											$predicted_time = predictByTraffic($stop_id, $next_stop);
											cleanTripPredictions($trip_id, $next_stop, $arr_date);
											// Make new prediction entry
											DB::table('live_traffic_predictions')->insert([
											'trip_id' => $trip_id,
											'trip_date' => $arr_date,
											'predicted_time' => $predicted_time,
											'stop_id' => $next_stop,
											]);
										}
										return response()->json([
											"Success" => "Trip Time Logged",
											"Time" => $arr_time,
											"Date" => $arr_date
										], 202); // Accepted
										}
									}
									else{
										return response()->json([
											"Error" => "This Client is Not Allowed"
										], 401);  //Unauthorized
									}
								}
								else{
									return response()->json([
										"Error" => "Invalid Trip ID"
									], 400); // Bad Request
								}
							}
							else{
								return response()->json([
									"Error" => "Corrupt RF-ID"
								], 400); // Bad Request
							}
						}
						else{
							return response()->json([
								"Error" => "Corrupted RF-ID"
							], 400); // Bad Request
						}
					}
					else{
						return response()->json([
						"Error" => "Invalid or Expired API Token"
						], 401); // Unauthorized
					}
				}
				else{
				return response()->json([
					"Error" => "Invalid or Expired API Token"
				], 401); // Unauthorized
				}
			}
			else{
				return response()->json([
					"Error" => "This Client is Not Allowed"
				], 401);  //Unauthorized
			}
		}
		else{
		return response()->json([
			"Error" => "Unknown format. Use JSON",
		], 400);
		}
	}
	}

}

?>