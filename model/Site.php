<?php
namespace Sites {

class Site {
  public $id = null;
  public $name;
  public $address;
  public $description;
  public $developer_id = DEV_ID;
  public $external_id;
  public $property_ids = [];
  public $style_ids    = [];


  public function __construct(int $id=null, object $properties=null) {
    if ($properties != null) {
      $this->updateObject($properties);
      //print_r($this);
    }
    if ($id != null) {
      $this->get($id);
      //print_r($this);
    }
  }

  public function get($id) {
    $res = ucadoApi('developer-site/get/'.$id, TOKEN);
    if ($res->status == 200) $this->updateObject($res->data);
    return $res;
  }

  public function upsert($name, $address, $description, $external_id, $id=null) {
    $o = [
      "id"           => $id,
      "name"         => mb_convert_encoding($name,  "ASCII"),
      "address"      => $address,
      "description"  => mb_convert_encoding($description,  "ASCII"),
      "developer_id" => $this->developer_id,
      "external_id"  => $external_id,
    ];

    $res = ucadoApiPost('developer-site/upsert', $o, TOKEN);
    if ($res->status == 200) $this->updateObject($res->data);
    return $res;
  }

  public function delete() {
    $res = ucadoDeleteApi('developer-site/delete/'.$this->id, TOKEN);
    return $res;
  }

  public function updateObject($data) {
    $this->name         = $data->name;
    $this->address      = $data->address;
    $this->description  = $data->description;
    $this->external_id  = $data->external_id;
    if (isset($data->id))           $this->id           = $data->id;
    if (isset($data->property_ids)) $this->property_ids = $data->property_ids;
    if (isset($data->style_ids))    $this->style_ids    = $data->style_ids; 
  }

  public function info() {
    print_r($this);
  }
}


}

?>