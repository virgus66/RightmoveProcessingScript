<?php
namespace MatchingFeatures {    

    require_once dirname(__FILE__)."/FeedDownloader.php";
    use FeedDownloader\Downloader as FD;

    class MatchingFeatures {
        //public $memoize = [];
        public $noMatch = [];
        public $xml;
        public $matchesArr = [];

        public function __construct() {
            $this->xml = FD::run_local(ROOTPATH.FEAT_XML);
        }
    
        public function check(array $wordsArr) {
            $this->matchesArr = [];
            foreach ($wordsArr as $word) {
                $word = strtolower(mb_convert_encoding($word,  "Windows-1252"));
                $f = $this->extractAmount($word);                
                $match = false;

                foreach ($this->xml->developer->dictionary as $dictionary_obj) {

                    $xml_ext_feature  = (string)$dictionary_obj->ext_feature;
                    if ( $f['name'] == $xml_ext_feature ) {
                        $match = true;
                        $this->memoize[] = strtolower($f['name']);
                        foreach ( $dictionary_obj->int_feat_id as $internal_id) {
                            $this->matchesArr[] = (object)['id' => (string)$internal_id, 'value' => $f['amount']];
                        }
                        break;
                    }
                }
                if (!$match && !array_search($f['name'], $this->noMatch))  $this->noMatch[] = $f['name'];
            }
            return $this->matchesArr;
        }

        public function extractAmount(string $feed_f) {
            preg_match_all('/(^\d+)?\b(.*)\b/i', $feed_f, $matches, PREG_SET_ORDER, 0);
            //print_r($matches[0]);
            $amount  = intval($matches[0][1]);
            $amount = ($amount == null || $amount == 1) ? 1 : $amount;
            $feature = trim($matches[0][2]);

            return ['amount'=>$amount, 'name'=>$feature];
        }
    }

    class MatchFromFloors {
        public static function checkRooms($plot) {
            $bath      = 0;
            $singleBed = 0;
            $doubleBed = 0;
						$enSuite   = 0;
        
            if (isset($plot->floor)) {
							foreach ($plot->floor as $floor) {
								if (isset($floor->room)) {
									foreach ($floor->room as $room) {

										preg_match_all('/(\w+)(?:.*(single|double))?/i', $room['name'], $res, PREG_SET_ORDER, 0 );
										if (isset($res[0])) {
											if ( strtolower($res[0][1] ) == 'bathroom' ) $bath++;
											if ( strtolower($res[0][1] ) == 'ensuite' )  $enSuite++;
											if ( strtolower($res[0][1] ) == 'bedroom' ) {
												if (isset($res[0][2])) {
													if ( strtolower($res[0][2] ) == 'single' ) $singleBed++;
													if ( strtolower($res[0][2] ) == 'double' ) $doubleBed++;
												}
											}
										}

									}
								}
							}
						}
						
						return [
							($bath 		  > 0) ? (object)['id' => 2,  'value' => $bath] 		 : null,
							($singleBed > 0) ? (object)['id' => 47, 'value' => $singleBed] : null,
							($doubleBed > 0) ? (object)['id' => 1,  'value' => $doubleBed] : null,
							($enSuite 	> 0) ? (object)['id' => 65, 'value' => $enSuite] 	 : null,
						];
            // else return null;
				}
				
				public function regexp( $name, $str ) {
					preg_match_all('/'.$name.'/i', $str, $res, PREG_SET_ORDER, 0 );

					if (true) {
						return true;
					}
				}
    }
}

?>