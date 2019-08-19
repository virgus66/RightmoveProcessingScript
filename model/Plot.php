<?php
namespace Plots {

require_once './utilities/AddressPosition.php';
require_once "./utilities/ImageResizer.php";

use AddressPosition\GoogleAPI as addr;
use ImageResizer\ImageResizer as IR;

class Plot {
  protected static $rightmovePropertyTypes = [
    'detached-house'      => 1,
    'semi-detached-house' => 2,
    'link-detached-house' => 2,
    'terraced-house'      => 3,
    'end-terraced-house'  => 3,
    'town-house'          => 1,
    'maisonette'          => 5,
    'mews'                => 3,
    'bungalow'            => 4,
    'cluster-house'       => 1,
    'house'               => 1,
    'flat'                => 5,
    'retirement-property' => 1
  ];
  public $id;
  public $nb;
  public $street;
  public $town;
  public $postcode;
  public $price;
  public $site_id;
  public $style_id;
  public $lat;
  public $lng;
  public $type;
  public $type_id;
  public $property_listings = [4];
  public $status_id         = 2;
  public $property_features;
  public $propertyPictures;
  public $photoCount = 0;
  public $floor_plans;
  public $description;

  public function __construct() {

  }

  public function create() {
    $o = [
      "lat"       => $this->lat,
      "lng"       => $this->lng,
      "post_code" => $this->postcode,
      "town"      => $this->town,
      "country"   => "UK",
      "street"    => $this->street,
      "number"    => $this->nb,
      "price"     => $this->price,
      "site_id"   => $this->site_id,
      "style_id"  => $this->style_id,
    ];
    $res = ucadoApiPost('property/add', $o, TOKEN);
    if ($res->status == 200) { $this->id = $res->data->property_id; };
    return $res;
  }

  public function update() {
    if( !isset($this->type_id) ) {
      if ( array_key_exists($this->type, self::$rightmovePropertyTypes)  ) {
        $this->type_id = self::$rightmovePropertyTypes[ $this->type ];
      } else $this->type_id = 1;
    }

    $o = [
      "property_id"       => $this->id,
      "lat"               => $this->lat,
      "lng"               => $this->lng,
      "post_code"         => $this->postcode,
      "town"              => $this->town,
      "country"           => "UK",
      "street"            => $this->street,
      "number"            => $this->nb,
      "price"             => $this->price,
      "type_id"           => $this->type_id,
      "property_listings" => $this->property_listings,
      "status_id"         => $this->status_id,
      "property_features" => $this->property_features,
      "description"       => $this->description,
    ];
    return ucadoAPiPost('property/update', $o, TOKEN);
  }

  public function get($id) {
    $res = ucadoApiPost('property/details', [ "property_id"=>$id, "lat"=>8.0239048, "lng"=>9.8340234 ], TOKEN);

    if ($res->status == 200) {
      $this->id               = $res->data->property_id;
      $this->nb               = $res->data->street_number;
      $this->street           = $res->data->streetName;
      $this->town             = $res->data->town;
      $this->postcode         = $res->data->postcode;
      $this->price            = $res->data->price;
      $this->site_id          = $res->data->site_id;
      $this->style_id         = $res->data->style_id;
      $this->lat              = $res->data->lat;
      $this->lng              = $res->data->lng;
      $this->type_id          = $res->data->type->id;
      $this->propertyPictures = $res->data->propertyPictures;
      $this->property_features= $res->data->propertyFeatures;
      $this->floor_plans      = $res->data->floor_plans;
    }
/*     foreach ($this as $key=>$val) {
      echo $key." - ".$val."<br>";
    } */
    return $res;
  }

  public function setLatLng() {
    $latLng = addr::getLatLng($this->nb, $this->street, $this->town, $this->postcode);
    //$latLng is an array
    $this->lat = $latLng['lat'];
    $this->lng = $latLng['lng'];
  }

  public function changePropertyStatus($status) {
    $res = ucadoApiPost('property/set-status', [
        "property_id" => $this->id,
        "status_id"   => $status
    ], TOKEN);

    return $res;
  }
  
  public function addPhoto($media) {
    if( (string)$media->type == "image" ) {
      
      if (isset($media->url)) $photo_selected = (string)$media->url;
      if (isset($media->filename)) $photo_selected = (string)$media->filename;
      preg_match('/^http.*/', $photo_selected, $matches);
      
      if ( count($matches) > 0 ) {
        $odp = ucadoApiPost('property/add_image', [
          "property_id"   => $this->id,
          "is_main_image" => ($this->photoCount == 0) ? 1 : 0,
          "type"          => 1,
          "image_url"     => $photo_selected
        ], TOKEN);
        
        if ($odp->status == 200) {
          $this->photoCount++;
          return "<div class='photo added'>Image added succesfully, id: ".$odp->data->id."</div>";
        } 
        else {
          //$failed++;
          return "<div class='photo deleted'>".$odp->message.", img: ". $odp->data->id ." Couldn't add that image</div>";
        }
      } else {
        $status = $this->imageFromFile( $photo_selected);
        if ($status) { return "Photo added"; } else return "FAILED to add photo"; 
      }
    }
  }

  public function addPhotoLettings($media) {
    if ( $media->media_type == 1 ) {
      $odp = ucadoApiPost('property/add_image', [
        "property_id"   => $this->id,
        "is_main_image" => ($media->sort_order == 0) ? 1 : 0,
        "type"          => 1,
        "image_url"     => $media->media_url
      ], TOKEN);
      
      if ($odp->status == 200) {
        $this->photoCount++;
        return "<div class='photo added'>Image added succesfully, id: ".$odp->data->id."</div>";
      } 
      else {
        //$failed++;
        return "<div class='photo deleted'>".$odp->message.", img: ". $odp->data->id ." Couldn't add that image</div>";
      }
    }
  }

  public function addFloorplanEstateAgent($media) {
    if ( $media->media_type == 2 ) {
      $odp = ucadoApiPost('property/add/floor-plan', [
        "property_id" => $this->id,
        "title"       => $media->caption != null ? $media->caption : 'title',
        "image_url"   => $media->media_url,
      ], TOKEN);
    
      if ($odp->status == 200) {
        return "<div class='photo added'>Floor Plan added succesfully.</div>";
      } else return "<div class='photo deleted'>".$odp->message.". Couldn't add that floorplan</div>";
    }
  }

  public function deleteAllFloorplans() {
    if ( $this->floor_plans != null ) {
      $success = 0;
      $failed  = 0;
      $total   = count( $this->floor_plans );
      
      foreach ( $this->floor_plans as $floor_plan ) {
        $res = ucadoApiPost('property/remove/floor-plan', [
          "floor_plan_id" => $floor_plan->floor_plan_id
        ], TOKEN);
      }
      return ("<div class='photo deleted'>Deleted floorplans for property id: $this->id - success: $success failed: $failed of $total</div>\n\n");
    } else return "no floorplans to delete";
  }
  
  public function imageFromFile($img, $main=0) {
    $key = array_search($img, FILES);
    
    if ($key) {
      $path = IMG_FOLDER .'/'. FILES[$key];
      //info about file type
      $info = pathinfo($path, PATHINFO_EXTENSION);
      
      //resize image
      $image_resizer = new IR($path);
      if ( $image_resizer->wider_dimension ) {
        $image_resizer->load();
        $base64 = $image_resizer->resized_img_b64;
        print_r("<div class='photo'>image resized</div>\n\n");
      } else {
        $file_stream = file_get_contents($path);
        $base64 = base64_encode($file_stream);
      }
      
      $odp = ucadoApiPost('property/add_image', [
        "property_id"   => $this->id,
        "is_main_image" => $main,
        "type"          => 1,
        "image"         => $base64
      ], TOKEN);
      

      if ($odp->status == 200) {
        return "<div class='photo added'>".$odp->message.", img: ". $img;
      } else {
        return "<div class='photo deleted'>".$odp->message.", img: ". $img ." Couldn't add that image</div>\n\n";
      }
    }
    else if ($img == null || $img == "") print_r("<div class='photo'>No images for this style provided in feed.</div>\n\n");
    else print_r( "<div class='photo'>file found in feed XML file but not in dir... file=". $img ."</div>\n\n" );
  }

  public function deletePhotos() {
    if ($this->propertyPictures != null) {
      $success = 0;
      $failed  = 0;
      $total   = count($this->propertyPictures);
  
      foreach ( $this->propertyPictures as $k => $picture ) {
        $res = ucadoDeleteApi ('delete-property-image/'.$picture->id, TOKEN);

        if ($res->status == 200) { 
          $success++;
          unset($this->propertyPictures[$k]); 
        } else $failed++;
      }
      return ("<div class='photo deleted'>Deleted pictures for property id: $this->id - success: $success failed: $failed of $total</div>\n\n");
    } else return 'no photos to delete';
  }

}

}


?>