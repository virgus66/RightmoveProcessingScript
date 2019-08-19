<?php
namespace Properties {

include 'AddressPosition.php';
use AddressPosition\GoogleAPI as addr;

class Property {
    private $TOKEN ;
    public $number;
    public $street;
    public $town;
    public $postcode;
    protected $price;
    public $site_id;
    public $style_id;
    public $lat;
    public $lng;
    public $type_id;
    public $property_listings;
    public $property_features;

    public function __construct($token, $o) {
        $this->TOKEN             = $token;
        $this->number            = $o['number'];
        $this->street            = $o['street'];
        $this->town              = $o['town'];
        $this->postcode          = $o['postcode'];
        $this->price             = $o['price'];
        $this->site_id           = $o['site_id'];
        $this->style_id          = $o['style_id'];
        $this->type_id           = $o['type_id'];
        $this->status_id         = $o['status_id'];
        $this->property_listings = $o['property_listings'];
    }

    public static function propertyInfo($id, $token) {
        return( ucadoApiPost('property/details', [ "property_id"=>$id, "lat"=>8.0239048, "lng"=>9.8340234 ], $token) );
    }

    public function addProperty() {
        $o = [
            "lat"       => $this->lat,
            "lng"       => $this->lng,
            "post_code" => $this->postcode,
            "town"      => $this->town,
            "country"   => "UK",
            "street"    => $this->street,
            "number"    => $this->number,
            "price"     => $this->price,
            "site_id"   => $this->site_id,
            "style_id"  => $this->style_id
        ];
        return ucadoApiPost('property/add', $o, $this->TOKEN);
    }

    public function updateProperty($id) {
        $o = [
            "property_id"       =>$id,
            "lat"               => $this->lat,
            "lng"               => $this->lng,
            "post_code"         => $this->postcode,
            "town"              => $this->town,
            "country"           => "UK",
            "street"            => $this->street,
            "number"            => $this->number,
            "price"             => $this->price,
            "type_id"           => $this->type_id,
            "property_listings" => $this->property_listings,
            "status_id"         => $this->status_id,
            "property_features" => $this->property_features
        ];
        return ucadoAPiPost('property/update', $o, $this->TOKEN);
    }
    
    public function setLatLng($nb,$str,$town,$postcode) {
        $latLng = addr::getLatLng($nb,$str,$town,$postcode);
        //$latLng is an arr
        $this->lat = $latLng['lat'];
        $this->lng = $latLng['lng'];
    }

    public function buildQuery() {
        return $query;
    }

    public function show() {
        print_r($this);
    }
}
}
?>