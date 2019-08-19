<?php

use Sites\Site as Site;
use Styles\Style as Style;
use Plots\Plot as Plot;
use \MatchingFeatures\MatchingFeatures as match;

class FeedStylePlot extends FeedTemplate {

  protected $styles = [];

  public function __construct($xml) {
    parent::__construct();

    $this->errors['sites']      = [];
    $this->errors['styles']     = [];
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

            $this->getStylesInSite( $code );

            //---------- STYLE / PLOT ----------
              $this->msg->out('<strong>----==== Style / Plot structure ====----</strong>');  
              foreach ($dev->style as $style) {

                // Features new mapping
                $features = []; 
                foreach( $style->feature as $rm_feature) { $features[] = (string) $rm_feature; }
                print_r( $features = $match->check($features) );
                //EO Features

                if (!array_key_exists( (string)$style['code'], $this->styles )) {
                    // ----- Add Style
                    $style_obj = new Style();
                    $odp = $style_obj->upsert( 
                      utf8_encode((string)$style['name']), 
                      (string)$style->description, 
                      $features, 
                      (string)$style['code'], 
                      $site->id
                    );
                    if ($odp->status == 200) {
                      $this->msg->out('Style added, id: '.$odp->data->id, 'style added');
                      // Add photos if succesfully created style
                      foreach ($style->media as $media) {
                        $res = $style_obj->addPhoto( $media );
                        if ( $res != null) $this->msg->out( $res );
                      }
                    } else {
                      $this->errors['styles'][] = 'Server responded incorrectly when adding Style, Ext site id '.(string)$style['code'];
                      $this->msg->out('Server responded incorrectly when adding Style, status: '.$odp->status.' message: '.$odp->message, 'deleted');
                      continue;
                    }
                }
                else 
                {
                    // ----- Modify Style
                    $style_obj = $this->styles[ (string)$style['code'] ];

                    $odp = $style_obj->upsert( 
                      utf8_encode((string)$style['name']), 
                      (string)$style->description, 
                      $features, 
                      (string)$style['code'], 
                      $site->id, 
                      $style_obj->id
                    );
                    if ($odp->status == 200) {
                      $this->msg->out('Style modified, id: '.$odp->data->id, 'style modified');
                      if ( process_photos ) {
                        $this->msg->out( $style_obj->deletePhotos());
                        foreach ($style->media as $media) {
                          $res = $style_obj->addPhoto( $media );
                          if ( $res != null) $this->msg->out( $res );
                        }
                      }
                    } else {
                      $this->errors['styles'][] = 'Server responded incorrectly when modifying Style, Ext site id '.(string)$style['code'];
                      $this->msg->out('Server responded incorrectly when modifying Style, status: '.$odp->status.' message: '.$odp->message, 'deleted');
                      continue;
                    }
                }

                // PLOTS
                if (isset($style->plot)) {
                    foreach( $style->plot as $plot ) {
                      
                      $bedrooms_nb = (int)    $plot->bedrooms;
                      $feedType    = (string) $style->{'property-type'};
                      $style_obj->addBedrooms($bedrooms_nb, $site->id);

                      
                      if ( $prop_id = $this->plotExistsInDB($site, (string)$plot->{'name-number'}) ) {
                        // MODIFY PLOT
                        $p           = $this->properties[ $prop_id ];
                        $p->town     = (string)$dev->town;
                        $p->nb       = (string)$plot->{'name-number'};
                        $p->street   = (string)$dev->address1;
                        $p->postcode = (string)$dev->postcode;
                        $p->price    = (string)$plot->price;
                        $p->site_id  = $site->id;
                        $p->style_id = $style_obj->id;
                        $p->type     = (string)$style->{'property-type'};

                        $res = $p->update();

                        if ( $res->status == 200 ) {
                          $this->msg->out("Property modified: ".$res->data->property_id." Price: £".(string)$plot->price, 'property modified');
                        } else {
                          $this->msg->out("Can't create property. Server message: $res->message");
                          $this->errors['properties'][] = "Can't create property. Server message: $res->message";
                        }

                        unset( $this->properties[ $prop_id ] );
                        $this->msg->out('$this->properties = '. count($this->properties) );
                      }
                      else 
                      {
                        // ADD PLOT
                        $p           = new Plot();
                        $p->town     = (string)$dev->town;
                        $p->nb       = (string)$plot->{'name-number'};
                        $p->street   = (string)$dev->address1;
                        $p->postcode = (string)$dev->postcode;
                        $p->price    = (string)$plot->price;
                        $p->site_id  = $site->id;
                        $p->style_id = $style_obj->id;
                        $p->type     = (string)$style->{'property-type'};

                        $p->setLatLng();
                        $res = $p->create();

                        if ( $res->status == 200 ) {
                          $p->update();

                          $this->msg->out("Property added: ".$res->data->property_id." Price: £".(string)$plot->price, 'property added');
                          // ADD MEDIA
                        } else {
                          $this->msg->out("Can't create property. Server message: $res->message");
                          $this->errors['properties'][] = "Can't create property. Server message: $res->message";
                        }
                      }
                      
                      $this->totalPlots++;
                      $this->msg->info($this->totalPlots, 'total');
                    }
                } // EO Plots
              }

        } // EO Development
      } // EO Region
    } // EO Developer


    $this->archivePlotsNotFoundInFeed();
    $this->displayErrors();
  }

  public function getStylesInSite($ext_site_id) {
    $this->styles = [];
    $count = 0;

    if ( array_key_exists($ext_site_id, $this->sites) ) {
      if ( count($this->sites[$ext_site_id]->style_ids) > 0 ) {
        foreach($this->sites[$ext_site_id]->style_ids as $style_id) {
          $count++;
          $style = new Style();

          if ($style->get($style_id)->status == 200) {
            $this->styles[ $style->external_id ] = $style;
          } 
          else $this->msg->out('Can\'t get site info from API.', 'deleted');
        }
        $this->msg->out("$count styles found internaly");
      } else $this->msg->out('No styles in this site.','deleted');
    } else $this->msg->out('Site not found in DB.', 'modified');
  }

  public function plotExistsInDB($site_obj, $plot_nb) {
    if ( count($site_obj->property_ids) > 0 ) {
      foreach ( $site_obj->property_ids as $prop_id ) {
        if ( array_key_exists(  $prop_id, $this->properties ) ) {
          if ( (int)$this->properties[ $prop_id ]->nb == (int)$plot_nb ) return $prop_id;
        }
      }
    }
    return false;
  }

}