<?php
namespace AddressPosition;

class GoogleAPI {
    public static function getLatLng($number, $street, $town, $postcode) {
        //get lat/lng points Google Maps API
        $address = urlencode($number. " ".$street." ".$town." ".$postcode);


        $curl_maps = curl_init();
        curl_setopt($curl_maps, CURLOPT_URL, "https://maps.googleapis.com/maps/api/geocode/json?address=". $address ."&key=". GOOGLE_API_KEY); 
        curl_setopt($curl_maps, CURLOPT_RETURNTRANSFER, 1);
        $maps_output = curl_exec($curl_maps); 
        curl_close($curl_maps); 

        $maps_output = json_decode($maps_output);


        if ( isset($maps_output->results[0]->geometry->location) ) {
            return( array(
                'lat' => $maps_output->results[0]->geometry->location->lat, 
                'lng' => $maps_output->results[0]->geometry->location->lng 
            ));
        }
        else 
        {
            return( array(
                'lat' => 0,
                'lng' => 0
            ));
        }

    }
}

?>