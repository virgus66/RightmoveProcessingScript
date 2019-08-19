<?php
namespace Styles {

class Style {
  public $id = null;
  public $name;
  public $description;
  public $external_id;
  public $type_id = 1;
  public $site_id;
  public $features = [];
  public $pictures = [];
  public $photoCount = 0;

  public function __construct(int $id=null, object $properties=null) {
    if ($properties != null) { 
      $this->updateObject($properties); 
      print_r($this);
    }
    if ($id != null) {
      $this->get($id);
      print_r($this);
    }
  }

  public function get($id) {
    $res = ucadoApi('style/get/'.$id, TOKEN);
    if ($res->status == 200) $this->updateObject($res->data);
    return $res;
  }

  public function upsert($name, $description, $features, $external_id, $site_id, $id=null) {
    $o = [
      "id"                => $id,
      "name"              => mb_convert_encoding($name,  "ASCII"),
      "description"       => mb_convert_encoding($description,  "ASCII"),
      "type_id"           => $this->type_id,
      "property_features" => $features,
      "external_id"       => $external_id,
      "site_id"           => $site_id //takes this parameter when upsert, but not returned in any respond
    ];
    $res = ucadoApiPost('style/upsert', $o, TOKEN);
    if ($res->status == 200) $this->updateObject($res->data);
    return $res;
  }

  public function delete() {
    $res = ucadoDeleteApi('style/delete/'.$this->id, TOKEN);
    return $res;
  }

  public function updateObject($data) {
    $this->id           = $data->id;
    $this->name         = $data->name;
    $this->description  = $data->description;
    $this->external_id  = $data->external_id;
    $this->features     = $data->features;
    $this->pictures     = $data->pictures;
    if (isset($data->site_id)) $this->site_id = $data->site_id;
  }

  public function addBedrooms($amount, $site_id) {
    $this->features[] = ['id' => 1, 'value' => $amount];
    $o = [
      "id"                => $this->id,
      "name"              => $this->name,
      "description"       => $this->description,
      "type_id"           => $this->type_id,
      "property_features" => $this->features,
      "external_id"       => $this->external_id,
      "site_id"           => $site_id //takes this parameter when upsert, but not returned in any respond
    ];    
    $res = ucadoApiPost('style/upsert', $o, TOKEN);
    if ($res->status == 200) $this->updateObject($res->data);
    return $res;
  }

  public function addPhoto($media) {
    if( (string)$media->type == "image" ) {
        
        if (isset($media->url)) $photo_selected = (string)$media->url;
        if (isset($media->filename)) $photo_selected = (string)$media->filename;
        preg_match('/^http.*/', $photo_selected, $matches);

        if ( count($matches) > 0 ) {
            $odp = ucadoApiPost('property/add_image', [
                "style_id"   => $this->id,
                "is_main_image" => ($this->photoCount == 0) ? 1 : 0,
                "type"          => 1,
                "image_url"     => $photo_selected
            ], TOKEN);

            if ($odp->status == 200) {
              $this->photoCount++;
              return "<div class='photo added'>Image added succesfully, id: ".$odp->data->id."</div>"; 
            } 
            else {
                return "<div class='photo deleted'>".$odp->message.", img: ". $odp->data->id ." Couldn't add that image</div>";
            }
        } else {
            $status = $this->imageFromFile( $photo_selected);
            if ($status) { return "Photo added"; } else return "FAILED to add photo"; 
        }
    }
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
            "style_id"      => $this->id,
            "is_main_image" => $main,
            "type"          => 1,
            "image"         => $base64
        ], TOKEN);

        if ($odp->status == 200) {
            print_r("<div class='photo added'>".$odp->message.", img: ". $img);
            return true;
        } else {
            print_r("<div class='photo deleted'>".$odp->message.", img: ". $img ." Couldn't add that image</div>\n\n");
            return false;
        }
    }
    else if ($img == null || $img == "") print_r("<div class='photo'>No images for this style provided in feed.</div>\n\n");
    else print_r( "<div class='photo'>file found in feed XML file but not in dir... file=". $img ."</div>\n\n" );
  }

  public function deletePhotos() {
    if ($this->pictures != null) {
      $success = 0;
      $failed  = 0;
      $total   = count($this->pictures);

      foreach ( $this->pictures as $k => $picture ) {
        $res = ucadoDeleteApi ('delete-property-image/'.$picture->id, TOKEN);
        if ($res->status == 200) { 
          $success++;
          unset($this->pictures[$k]); 
        } else $failed++;
      }
      return ("<div class='photo deleted'>Deleted pictures for style id: $this->id - success: $success failed: $failed of $total</div>\n\n");
    } else return 'no photos to delete';
  }

}
}


?>