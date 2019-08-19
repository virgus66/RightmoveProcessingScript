<?php

use Sites\Site as Site;
use Plots\Plot as Plot;
use MatchingFeatures\{MatchingFeatures as match, MatchFromFloors as matchFloors};

class FeedPlotOnly extends FeedTemplate {

  public function __construct($xml) {
    parent::__construct();

    $this->errors['sites']      = [];
    $this->errors['properties'] = [];
    $this->msg->info($this->plotsInFeed($xml), 'total_plots');
    
    $this->getDeveloper(DEV_ID);
    $this->processFeed($xml);
  }

  public function processFeed($xml) {
    $match = new match;
    $this->msg->out($xml->name. " feed created at ".$xml->created);
    

    foreach( $xml->developer as $developer ) {
      foreach( $developer->{'developer-region'} as $f_region ) {
        //---------- SITE-DEVELOPMENT ----------
        foreach( $f_region->development as $dev ) {

            $this->msg->out("Development: ". (string)$dev['name']);
            
            // -------- parse feed data
            $name   = (string)$dev['name'];
            $desc   = (string)$dev->description;
            $code   = (string)$dev['code'];
            $street = (string)$dev->address1;
            ($dev->address2 != "") ? $street .= ", ".$dev->address2 : '';
            ($dev->address3 != "") ? $street .= ", ".$dev->address3 : '';
            $addr   = $street;
            $addr  .= ", ".$dev->town;
            $addr  .= ", ".$dev->postcode;
            $addr   = $addr;
            // -------- EO parse feed data


            if( !array_key_exists( $code, $this->sites ) ) {
              // ----- Add Site
              $site = new Site();
              $odp = $site->upsert($name, $addr, $desc, $code);
              if ($odp->status == 200) {
                $this->msg->out('Site added, id: '.$odp->data->id, 'site added');
              } else {
                $this->errors['sites'][] = 'Server responded incorrectly when adding Site, Ext site id '.$code;
                $this->msg->out('Server responded incorrectly when adding Site, status: '.$odp->status.' message: '.$odp->message, 'deleted');
                continue;
              }
            }
            else 
            {
              // ------ Modify Site
              $site = $this->sites[ $code ];
              $odp = $site->upsert($name, $addr, $desc, $code, $site->id);
              if ($odp->status == 200) {
                $this->msg->out('Site modified, id: '.$odp->data->id, 'site modified');
              } else {
                $this->errors['sites'][] = 'Server responded incorrectly when modifying Site, id: '.$site->id. 'external: '.$code;
                $this->msg->out('Server responded incorrectly to update Site, status: '.$odp->status.' message: '.$odp->message, 'deleted');
                continue;
              }
            }

            //---------- PLOT ONLY ----------
              $this->msg->out('<strong>----==== Plot only structure ====----</strong>');                  
              $this->getPlotsInSite($site->external_id);   
              
              foreach ($dev->plot as $plot) {
                $bedrooms_nb = (int)    $plot->bedrooms;
                $plot_nb     = (string) $plot->{'name-number'};
                
                // Features new mapping
                $features = []; 
                foreach( $plot->feature as $rm_feature) {
                  $features[] = (string) $rm_feature; 
                }
                $features   = $match->check($features);
                $features[] = ['id' => 1, 'value' => $bedrooms_nb];
                $features = array_merge($features, matchFloors::checkRooms($plot));
                //EO Features

                if ( array_key_exists($plot_nb, $this->plots) ) {
                  // MODIFY PLOT
                  $p                    = $this->plots[ $plot_nb ];
                  $p->town              = (string)$dev->town;
                  $p->nb                = (string)$plot->{'name-number'};
                  $p->street            = (string)$dev->address1;
                  $p->postcode          = (string)$dev->postcode;
                  $p->price             = (string)$plot->price;
                  $p->site_id           = $site->id;
                  $p->type              = (string)$plot->{'property-type'};
                  $p->property_features = $features;

                  $res = $p->update();

                  if ( $res->status == 200 ) {
                    $this->msg->out("Property modified: ".$res->data->property_id." Price: £".(string)$plot->price, 'property modified');
                    if ( process_photos ) {
                      $this->msg->out( $p->deletePhotos());
                      foreach ($plot->media as $media) {
                        $res = $p->addPhoto( $media );
                        if ( $res != null) $this->msg->out( $res );
                      }
                    }
                  } else {
                    $this->msg->out("Can't modify property id: $p->id. Server message: $res->message");
                    $this->errors['properties'][] = "Can't modify property id: $p->id. Server message: $res->message";
                  }

                  unset( $this->properties[ $p->id ] );
                  $this->msg->out('$this->properties = '. count($this->properties) );
                }
                else
                {
                  // ADD PLOT
                  $p                    = new Plot();
                  $p->town              = (string)$dev->town;
                  $p->nb                = (string)$plot->{'name-number'};
                  $p->street            = (string)$dev->address1;
                  $p->postcode          = (string)$dev->postcode;
                  $p->price             = (string)$plot->price;
                  $p->site_id           = $site->id;
                  $p->type              = (string)$plot->{'property-type'};
                  $p->property_features = $features;

                  $p->setLatLng();
                  $res = $p->create();

                  if ( $res->status == 200 ) {
                    $p->update();
                    $this->msg->out("Property added: ".$res->data->property_id." Price: £".(string)$plot->price, 'property added');
                    // ADD MEDIA
                    foreach ($plot->media as $media) {
                      $res = $p->addPhoto( $media );
                      if ( $res != null) $this->msg->out( $res );
                    }
                  }
                  else {
                    $this->msg->out("Can't create property. Server message: $res->message");
                    $this->errors['properties'][] = "Can't create property. Server message: $res->message";
                  }
                }
                
                $this->msg->info(++$this->totalPlots, 'total');
              }

        } // EO Development
      } // EO Region
    } // EO Developer


    $this->archivePlotsNotFoundInFeed();
    $this->displayErrors();
  }

}