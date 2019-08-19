<?php

use Sites\Site as Site;
use Plots\Plot as Plot;
use AddressPosition\GoogleAPI as addr;

class FeedBLM extends FeedTemplate {

  public $dieAfter = 0;

  public function __construct( $f_path ) {
    parent::__construct();
    $this->getDeveloper(DEV_ID);
    $this->processFeed($f_path);
  }

  public function processFeed( $path ) {
    $fs = fopen($path,"r");

    while ( $line = fgets($fs) ) {

      if ( strstr($line,"#DEFINITION#") ) {
        $def = fgets($fs);
        $def_elem = explode('^', $def);
        // print_r( $def_elem );
      }

      if ( strstr($line,"DATA") ) {
        // ========== PROCESS DATA ==========
        if ( count($this->sites) == 0 ) {
          // ----- Add Site
          $site = new Site();
          $odp = $site->upsert('Site of '.$_GET['dev'], 'no address', 'no bio', time());
          if ($odp->status == 200) {
            $this->msg->out('Site added, id: '.$odp->data->id, 'site added');
            $this->sites[] = $odp->data;
          } else {
            $this->errors['sites'][] = 'Server responded incorrectly when adding Site, Ext site id '.$code;
            $this->msg->out('Server responded incorrectly when adding Site, status: '.$odp->status.' message: '.$odp->message, 'deleted');
          }
        }
        
        $this->msg->out('<strong>----==== BLM structure ====----</strong>');

        // Count properties in feed
        $fs_copy = fopen($path,"r");
        $inTotal = 0;
        while ( $oneLine = fgets($fs_copy) ) {
          if (strstr($oneLine,"#END#")) break;
          $row = explode("^",$oneLine);
          if ( !isset($row[1]) ) continue;
          $inTotal++;
        }
        $this->msg->info($inTotal, 'total_plots');
        
        // ========== CREATE ARR FROM ROW ==========
        while( $line2 = fgets($fs) ) {
          if (strstr($line2,"#END#")) break;
          $row = explode("^",$line2);
          if ( !isset($row[1]) ) continue;
          
          $house_name_number = $this->getNumberInString($row[1]);
          $latLng = addr::getLatLng( $house_name_number->number, 
                                     $house_name_number->street, 
                                     $row[2],
                                     $row[5].' '.$row[6]);
          $lat = floor($latLng['lat']* 100000) / 100000;
          $lng = floor($latLng['lng']* 100000) / 100000;

          $currPropertyIndex = $this->findPropertyIndex( $house_name_number->number, $lat, $lng );

          $features = [];
          if ($row[21] > 0) $features[] = ['id'=>1, 'value'=>$row[21]];
          if ($row[22] > 0) $features[] = ['id'=>2, 'value'=>$row[22]];

          if ( sizeof($currPropertyIndex) > 0 ) {
            // MODIFY PLOT
            $p                    = $this->properties[ reset($currPropertyIndex)->id ];
            $p->town              = (string)$row[2];
            $p->nb                = (string)$house_name_number->number;
            $p->street            = (string)$house_name_number->street;
            $p->postcode          = $row[5].' '.$row[6];
            $p->price             = (string)$row[24];
            $p->site_id           = reset($this->sites)->id;
            // $p->type              = (string)$plot->{'property-type'};
            $p->property_features = $features;
            $p->lat               = (float)$lat;
            $p->lng               = (float)$lng;
            $p->property_listings = [1];
            $p->type_id           = $this->returnType( $row[26], $p->id );
            $p->description       = mb_convert_encoding( strip_tags($row[18]) ,  "ASCII") ;
  
            $res = $p->update();
  
            if ( $res->status == 200 ) {
              $this->msg->out("Property modified: ".$res->data->property_id." Price: £".$p->price, 'property modified');
              
              // ADD MEDIA
              if ( process_photos ) {
                $this->msg->out( $p->deletePhotos());
                $this->msg->out( $p->deleteAllFloorplans());

                for ($i=38; $i<=156; $i+=2) {
                  if ( $row[$i] != null ) {
                    $is_main = ($i==38) ? 1 : 0;
                    $res = $p->imageFromFile( $row[$i], $is_main );
                    $this->msg->out($res);
                  }
                }
              }
            } else {
              $this->msg->out("Can't modify property id: $p->id. Server message: $res->message");
              $this->errors['properties'][] = "Can't modify property id: $p->id. Server message: $res->message";
            }

            unset( $this->properties[ $p->id ] );
          }
          else
          {
            // ADD PLOT
            $p                    = new Plot();
            $p->town              = (string)$row[2];
            $p->nb                = (string)$house_name_number->number;
            $p->street            = (string)$house_name_number->street;
            $p->postcode          = $row[5].' '.$row[6];
            $p->price             = (string)$row[24];
            $p->site_id           = reset($this->sites)->id;
            // $p->type              = (string)$plot->{'property-type'};
            $p->property_features = $features;
            $p->lat               = (float)$lat;
            $p->lng               = (float)$lng;
            $p->property_listings = [1];
            $p->type_id           = $this->returnType( $row[26] );
            $p->description       = mb_convert_encoding( strip_tags($row[18]) ,  "ASCII") ;
            
            $res = $p->create();
  
            if ( $res->status == 200 ) {
              $p->update();
              $this->msg->out("Property added: ".$res->data->property_id." Price: £".$p->price, 'property added');
              
              // ADD MEDIA
              for ($i=38; $i<=156; $i+=2) {
                if ( $row[$i] != null ) {
                  $is_main = ($i==38) ? 1 : 0;
                  $res = $p->imageFromFile( $row[$i], $is_main );
                  $this->msg->out($res);
                }
              }
            }
            else {
              $this->msg->out("Can't create property. Server message: $res->message");
              $this->errors['properties'][] = "Can't create property. Server message: $res->message";
            }
          }

          $this->msg->info(++$this->totalPlots, 'total');
        }
        break;
      }
    }
    
    fclose($fs);
    $this->archivePlotsNotFoundInFeed();
    $this->displayErrors();
  }

  protected function getNumberInString( $house_name_number ) {
    $arr = explode(" ", $house_name_number);
    $number = $arr[0];
    array_shift($arr);
    $street = implode(' ', $arr);
    return (object)[ "street"=>$street, "number"=>$number ] ;
  }

  protected function findPropertyIndex($nb, $lat, $lng) {
    return 
      array_filter($this->properties, function($prop, $key) use($nb,$lat,$lng) {
        return
          (string)$prop->nb == (string)$nb && 
          (string)$prop->lat == (string)$lat && 
          (string)$prop->lng == (string)$lng ;
        
      }, ARRAY_FILTER_USE_BOTH);
  }

  protected function returnType($type, $prop_id="new") {
    if ( isset($this->blmPropertyTypes[ (int)$type ]) ) {
      return $this->blmPropertyTypes[ (int)$type  ];
    }
    else 
    {
      $this->errors['properties'][] = "Type not matched. prop_id: $prop_id , ext. type: $type";
      return 1;
    }
  }

  public $blmPropertyTypes = [
    // 0 =>  // Not Specified Not Specified (ONLY)
    1 => 3, // Terraced Houses
    2 => 3, // End of Terrace Houses
    3 => 2, // Semi-Detached Houses
    4 => 1, // Detached Houses
    // 5 =>  // Mews Houses
    // 6 =>  // Cluster House Houses
    7 => 5, // Ground Flat Flats / Apartments
    8 => 5, // Flat Flats / Apartments
    9 => 5, // Studio Flats / Apartments
    10 => 5, // Ground Maisonette Flats / Apartments
    11 => 5, // Maisonette Flats / Apartments
    12 => 4, // Bungalow Bungalows
    13 => 4, // Terraced Bungalow Bungalows
    14 => 4, // Semi-Detached Bungalow Bungalows
    15 => 4, // Detached Bungalow Bungalows
    // 16 =>  // Mobile Home Mobile / Park Homes
    // 19 =>  // Commercial Property Commercial Property
    20 => 10, // Land Land
    // 21 =>  // Link Detached House Houses
    // 22 =>  // Town House Houses
    23 => 7, // Cottage Houses
    // 24 =>  // Chalet Houses
    // 25 =>  // Character Character Property
    // 26 =>  // House Houses
    // 27 =>  // Villa Houses
    28 => 5, // Apartment Flats / Apartments
    29 => 5, // Penthouse Flats / Apartments
    // 30 =>  // Finca Houses
    // 43 =>  // Barn Conversion Character Property
    44 => 5, // Serviced Apartments Flats / Apartments
    // 45 =>  // Parking Garage / Parking
    // 46 =>  // Sheltered Housing Retirement Property
    // 47 =>  // Retirement Property Retirement Property
    // 48 =>  // House Share House / Flat Share
    // 49 =>  // Flat Share House / Flat Share
    // 50 =>  // Park Home Mobile / Park Homes
    // 51 =>  // Garages Garage / Parking
    52 => 7, // Farm House Character Property
    // 53 =>  // Equestrian Facility Character Property
    56 => 5, // Duplex Flats / Apartments
    59 => 5, // Triplex Flats / Apartments
    // 62 =>  // Longere Character Property
    // 65 =>  // Gite Character Property
    68 => 7, // Barn Character Property
    // 71 =>  // Trulli Character Property
    // 74 =>  // Mill Character Property
    // 77 =>  // Ruins Character Property
    // 80 =>  // Restaurant Commercial Property
    // 83 =>  // Cafe Commercial Property
    // 86 =>  // Mill Commercial Property
    // 92 =>  // Castle Character Property
    // 95 =>  // Village House Houses
    // 101 =>  // Cave House Character Property
    // 104 =>  // Cortijo Character Property
    107 => 7, // Farm Land Land
    110 => 10, // Plot Land
    // 113 =>  // Country House Character Property
    // 116 =>  // Stone House Character Property
    // 117 =>  // Caravan Mobile / Park Homes
    // 118 =>  // Lodge Character Property
    // 119 =>  // Log Cabin Character Property
    // 120 =>  // Manor House Character Property
    // 121 =>  // Stately Home Character Property
    125 => 10, // Off-Plan Land
    // 128 =>  // Semi-detached Villa Houses
    131 => 1, // Detached Villa Houses
    // 134 =>  // Bar / Nightclub Commercial Property
    // 137 =>  // Shop Commercial Property
    // 140 =>  // Riad Character Property
    // 141 =>  // House Boat Character Property
    // 142 =>  // Hotel Room Flats / Apartments
    143 => 5, // Block of Apartments Flats / Apartments
    // 144 =>  // Private Halls Flats / Apartments
    // 178 =>  // Office Commercial Property
    // 181 =>  // Business Park Commercial Property
    // 184 =>  // Serviced Office Commercial Property
    // 187 =>  // Retail Property (high street) Commercial Property
    // 190 =>  // Retail Property (out of town) Commercial Property
    // 193 =>  // Convenience Store Commercial Property
    // 196 =>  // Garage Commercial Property
    // 199 =>  // Hairdresser / Barber Shop Commercial Property
    // 205 =>  // Petrol Station Commercial Property
    // 208 =>  // Post Office Commercial Property
    // 211 =>  // Pub Commercial Property
    // 214 =>  // Workshop & Retail space Commercial Property
    // 217 =>  // Distribution Warehouse Commercial Property
    // 220 =>  // Factory Commercial Property
    // 223 =>  // Heavy Industrial Commercial Property
    // 226 =>  // Industrial Park Commercial Property
    // 229 =>  // Light Industrial Commercial Property
    // 232 =>  // Storage Commercial Property
    // 235 =>  // Showroom Commercial Property
    // 238 =>  // Warehouse Commercial Property
    // 241 =>  // Land Commercial Property
    // 244 =>  // Commercial Development Commercial Property
    // 247 =>  // Industrial Development Commercial Property
    // 250 =>  // Residential Development Commercial Property
    // 256 =>  // Data Centre Commercial Property
    259 => 7, // Farm Commercial Property
    // 262 =>  // Healthcare Facility Commercial Property
    // 265 =>  // Marine Property Commercial Property
    // 268 =>  // Mixed Use Commercial Property
    // 271 =>  // Research & Development Facility Commercial Property
    // 274 =>  // Science Park Commercial Property
    // 277 =>  // Guest House Commercial Property
    // 280 =>  // Hospitality Commercial Property
    // 283 =>  // Leisure Facility Commercial Property
    // 298 =>  // Takeaway Commercial Property
    // 301 =>  // Childcare Facility Commercial Property
    // 304 =>  // Smallholding Land
    // 307 =>  // Place of Worship Commercial Property
    // 310 =>  // Trade Counter Commercial Property
    // 511 =>  // Coach House Flats / Apartments
  ];
}

