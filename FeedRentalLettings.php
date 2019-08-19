<?php
namespace FeedRL {
  use \Sites\Site;
  use \Plots\Plot;

  
  class FeedRentalLettings extends \FeedTemplate {

    public $developer;
    public $stopCounter = 0;

    public function __construct( $feed_json ) {
      parent::__construct();
      
      $this->errors['sites']      = [];
      $this->errors['properties'] = [];
      $this->msg->info( $this->plotsInFeed($feed_json) , 'total_plots');
      $this->developer = $this->getDeveloper(DEV_ID)->data;

      $this->processFeed( $feed_json );
    }
    
    public function plotsInFeed($feed_json) {
      return sizeof($feed_json);
    }
    
    public function processFeed($json) {
      
      if ( count($this->sites) == 0 ) {
        // ----- Add Site
        $site = new Site();
        $odp = $site->upsert('Site of '.$this->developer->name, $this->developer->address, $this->developer->bio, time());
        if ($odp->status == 200) {
          $this->msg->out('Site added, id: '.$odp->data->id, 'site added');
          $this->sites[] = $odp->data;
        } else {
          $this->errors['sites'][] = 'Server responded incorrectly when adding Site, Ext site id '.$code;
          $this->msg->out('Server responded incorrectly when adding Site, status: '.$odp->status.' message: '.$odp->message, 'deleted');
        }
      }
      
      $this->msg->out('<strong>----==== Rentals / Lettings structure ====----</strong>');

      foreach ( $json as $property ) {
        $house_name_number = $this->getNumberInString($property->address->house_name_number);
        $currPropertyIndex = $this->findPropertyIndex(
          $house_name_number->number,
          $property->address->latitude,
          $property->address->longitude
        );

        $bed  = $property->details->bedrooms;
        $bath = $property->details->bathrooms;
        $features = [];
        if ($bed > 0)  $features[] = ['id'=>1, 'value'=>$bed];
        if ($bath > 0) $features[] = ['id'=>2, 'value'=>$bath];


        if ( sizeof($currPropertyIndex) > 0 ) {
          // MODIFY PLOT
          $p                    = $this->properties[ reset($currPropertyIndex)->id ];
          $p->town              = (string)$property->address->town;
          $p->nb                = (string)$house_name_number->number;
          $p->street            = (string)$house_name_number->street;
          $p->postcode          = $property->address->postcode_1.' '.$property->address->postcode_2;
          $p->price             = (string)$property->price_information->price;
          $p->site_id           = reset($this->sites)->id;
          // $p->type              = (string)$plot->{'property-type'};
          $p->property_features = $features;
          $p->lat               = (float)$property->address->latitude;
          $p->lng               = (float)$property->address->longitude;
          $p->property_listings = ($property->new_home == true) ? [4] : [1];
          $p->type_id           = $this->returnType( $property->property_type, $p->id );
          $p->description       = strip_tags($property->details->description);

          $res = $p->update();

          if ( $res->status == 200 ) {
            $this->msg->out("Property modified: ".$res->data->property_id." Price: £".$p->price, 'property modified');
            if ( process_photos ) {
              $this->msg->out( $p->deletePhotos());
              $this->msg->out( $p->deleteAllFloorplans());
              foreach ($property->media as $media) {
                $res = $p->addPhotoLettings( $media );
                if ( $res != null) $this->msg->out( $res );

                $res2 = $p->addFloorplanEstateAgent($media);
                if ($res2 != null) $this->msg->out( $res2 );
              }
            }
          } else {
            $this->msg->out("Can't modify property id: $p->id. Server message: $res->message");
            $this->errors['properties'][] = "Can't modify property id: $p->id. Server message: $res->message";
          }

          unset( $this->properties[ reset($currPropertyIndex)->id ] );
          $this->msg->out('$this->properties = '. count($this->properties) );
        }
        else
        {
          // ADD PLOT
          $p                    = new Plot();
          $p->town              = (string)$property->address->town;
          $p->nb                = (string)$house_name_number->number;
          $p->street            = (string)$house_name_number->street;
          $p->postcode          = $property->address->postcode_1.' '.$property->address->postcode_2;
          $p->price             = (string)$property->price_information->price;
          $p->site_id           = reset($this->sites)->id;
          // $p->type              = (string)$plot->{'property-type'};
          $p->property_features = $features;
          $p->lat               = (float)$property->address->latitude;
          $p->lng               = (float)$property->address->longitude;
          $p->property_listings = ($property->new_home == true) ? [4] : [1];
          $p->type_id           = $this->returnType( $property->property_type );
          $p->description       = strip_tags($property->details->description);
          
          $res = $p->create();

          if ( $res->status == 200 ) {
            $p->update();
            $this->msg->out("Property added: ".$res->data->property_id." Price: £".$p->price, 'property added');
            // ADD MEDIA
            foreach ($property->media as $media) {
              $res = $p->addPhotoLettings( $media );
              if ( $res != null) $this->msg->out( $res );

              $res2 = $p->addFloorplanEstateAgent($media);
              if ($res2 != null) $this->msg->out( $res2 );
            }
          }
          else {
            $this->msg->out("Can't create property. Server message: $res->message");
            $this->errors['properties'][] = "Can't create property. Server message: $res->message";
          }
        }

        $this->msg->info(++$this->totalPlots, 'total');
      }
    
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
      if ( isset($this->rightmovePropTypes[ (int)$type ]) ) {
        return $this->rightmovePropTypes[ (int)$type ];
      }
      else 
      {
        $this->errors['properties'][] = "Type not matched. prop_id: $prop_id , ext. type: $type";
        return 1;
      }
    }

  }
}