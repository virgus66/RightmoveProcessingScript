<?php

use \Sites\Site;
use \Plots\Plot;


abstract class FeedTemplate implements FeedInterface {
  
  protected $sites      = [];
  protected $plots      = [];
  public    $properties = []; //helping arr - everything that stayed here after process will be archived
  protected $totalPlots = 0;
  public    $total_archived;
  public    $msg;
  public    $errors     = [];
  public    $rightmovePropTypes = [
    0 => 1, // not specified
    1 => 3, // Terraced House,
    2 => 3, // End of terrace house,
    3 => 2, // Semi-detached house,
    4 => 1, // Detached house,
    // 5 Mews house, 
    6 => 3, // Cluster house, 
    7 => 7, // Ground floor flat, 
    8 => 7, // Flat, 
    9 => 7, // Studio flat, 
    10 => 7, // Ground floor maisonette, 
    11 => 7, // Maisonette, 
    12 => 4, // Bungalow, 
    13 => 3, // Terraced bungalow, 
    14 => 2, // Semi-detached bungalow, 
    15 => 4, // Detached bungalow, 
    // 16 Mobile home, 
    20 => 10, // Land (Residential), 
    // 21 Link detached house, 
    // 22 Town house, 
    // 23 Cottage,
    // 24 Chalet, 
    // 25 Character Property, 
    // 26 House (unspecified),
    // 27 Villa, 
    28 => 5, // Apartment, 
    29 => 5, // Penthouse, 
    // 30 Finca, 
    // 43 Barn Conversion, 
    44 => 5, // Serviced apartment, 
    // 45 Parking,
    // 46 Sheltered Housing, 
    // 47 Reteirment property, 
    // 48 House share,
    // 49 Flat share,
    // 50 Park home, 
    // 51 Garages, 
    52 => 7, // Farm House, 
    // 53 Equestrian facility, 
    // 56 Duplex, 
    // 59 Triplex, 
    // 62 Longere, 
    // 65 Gite, 
    // 68 Barn, 
    // 71 Trulli, 
    // 74 Mill, 
    // 77 Ruins, 
    // 80 Restaurant, 
    // 83 Cafe, 
    // 86 Mill, 
    // 92 Castle, 
    // 95 Village House, 
    // 101 Cave House, 
    // 104 Cortijo, 
    // 107 Farm Land, 
    110 => 10, // Plot,
    113 => 7, // Country House, 
    // 116 Stone House, 
    // 117 Caravan, 
    // 118 Lodge, 
    // 119 Log Cabin, 
    // 120 Manor House, 
    // 121 Stately Home, 
    // 125 Off-Plan, 
    128 => 2, // Semi-detached Villa, 
    131 => 1, // Detached Villa, 
    // 134 Bar/Nightclub, 
    // 137 Shop, 
    // 140 Riad, 
    // 141 House Boat, 
    // 142 Hotel Room, 
    // 143 Block of Apartments, 
    // 144 Private Halls, 
    // 178 Office, 
    // 181 Business Park, 
    // 184 Serviced Office, 
    // 187 Retail Property (High Street), 
    // 190 Retail Property (Out of Town), 
    // 193 Convenience Store, 
    // 196 Garages, 
    // 199 Hairdresser/Barber Shop, 
    // 202 Hotel, 
    // 205 Petrol Station, 
    // 208 Post Office, 
    // 211 Pub, 
    // 214 Workshop & Retail Space, 
    // 217 Distribution Warehouse, 
    // 220 Factory, 
    // 223 Heavy Industrial, 
    // 226 Industrial Park, 
    // 229 Light Industrial, 
    // 232 Storage, 
    // 235 Showroom, 
    // 238 Warehouse, 
    241 => 10, // Land (Commercial), 
    // 244 Commercial Development, 
    // 247 Industrial Development, 
    // 250 Residential Development, 
    // 253 Commercial Property, 
    // 256 Data Centre, 
    259 => 7, // Farm, 
    // 262 Healthcare Facility, 
    // 265 Marine Property, 
    // 268 Mixed Use, 
    // 271 Research & Development Facility, 
    // 274 Science Park, 
    // 277 Guest House, 
    // 280 Hospitality, 
    // 283 Leisure Facility, 
    // 298 Takeaway, 
    // 301 Childcare Facility, 
    // 304 Smallholding, 
    // 307 Place of Worship, 
    // 310 Trade Counter, 
    // 511 Coach House, 
    // 512 House of Multiple Occupation, 
    // 535 Sports facilities, 
    // 538 Spa, 
    // 541 Campsite & Holiday Village
  ];
  
  public function __construct() {
    $this->msg = new MessagesHelper();
  }
  

  public function start() {
    print_r( $this );
  }


  public function plotsInFeed($xml) {
    $plots_nb = 0;
    foreach( $xml->developer as $developer ) {
      foreach( $developer->{'developer-region'} as $f_region ) {
        foreach( $f_region->development as $dev ) {
          foreach ($dev->style as $style) {
            $plots_nb++;
          }
          foreach ($dev->plot as $plot) {
            $plots_nb++;
          }  
        }
      }
    }
    return $plots_nb;
  }

  
  public function getDeveloper($id) {
    $this->msg->info("getting developer info", 'feed_status');

    $developer = ucadoApi('developer/get/'.$id, TOKEN);
    if ( $developer->status != 200 ) {
      $this->msg->out("Can't get Developer - status $developer->status , message: $developer->message <br>");
      $this->msg->info("CLOSE", "feed_status");
      echo "data: END-OF-STREAM\n\n";
      die();
    }
    $this->msg->info($developer->data->name, 'info');

    if( $developer->data->sites != null ) {
      foreach( $developer->data->sites as $site_id ) {
        $this->msg->info("Get site details: id: ".$site_id, 'feed_status');
        $site = new Site($site_id);

        if ( $site->id != null ) { $this->sites[$site->external_id] = $site; } 
        else
        {
          $this->errors['sites'][] = 'Server respond incorrectly to get Site, id: '.$site_id;
          $this->msg->out('Server respond incorrectly to get Site, id: '.$site_id);
        }
        
        if ( $site->property_ids != null ) {
          foreach( $site->property_ids as $property_id ) {
            $this->msg->info("Getting property details: ".$property_id, 'feed_status');
            $tmp = new Plot();

            if( $tmp->get($property_id)->status == 200 ) {
              $this->properties[$property_id] = $tmp;
            } 
            else 
            {
              $this->errors['properties'][] = 'Server respond incorrectly to get Property, id: '.$property_id;
              $this->msg->out('Can\'t get info of property id: '.$property_id, 'deleted');
            };
          }
        }
      }
    }
    $this->msg->info("CLOSE", "feed_status");
    return $developer;
  }


  public function getPlotsInSite($external_site_id) {
    $this->plots = [];
    $count = 0;
    if ( array_key_exists($external_site_id, $this->sites) ) {
        if ( count($this->sites[$external_site_id]->property_ids) > 0 ) {
          foreach($this->sites[$external_site_id]->property_ids as $property_id) {
              
            if (isset( $this->properties[ $property_id ] )) {
              $count++;
              $tmp_prop = $this->properties[ $property_id ];
              $this->plots[ $tmp_prop->nb ] = $tmp_prop;
            } else $this->msg->out("Property found in Site->property_ids but not in properties array. Should never happend.", 'deleted');
          }
          $this->msg->out("$count plots found internaly");
        } else $this->msg->out('No plots in this site.', 'deleted');
    } else $this->msg->out("Can't find site in DB.", 'modified');
  }
  

  public function archivePlotsNotFoundInFeed() {
    $this->total_archived = 0;

    foreach ( $this->properties as $property_to_archive) {
      $res = $property_to_archive->changePropertyStatus(21);
      
      if ($res->status == 200) {
        $this->msg->out("Property id: $property_to_archive->id archived", 'property deleted'); 
        $this->total_archived++;
      } 
      else 
      {
        $this->errors['properties'][] = "Failed to change status for id: $property_to_archive->id - Server respond: $res->message";
        $this->msg->out("Failed to change status for id: $property_to_archive->id - Server respond: $res->message");
      }
    }
  }


  public function displayErrors() {
    foreach ( $this->errors as $key=>$val ) {
      $this->msg->out("<strong> $key errors:</strong>");
      foreach ( $val as $err ) {
        $this->msg->out($err);
      }
    }
  }

}