<?php 
/**----------------------------------------------------------------------
* created by David C.
* 
* to update feed on specific server you need to: 
* 1) choose correct server in cms_config.php
* 2) prompt this page by sending GET request with correct 'dev' parameter
*    specified in FeedDownloader namespace - ChooseDev class Devs
*  ---------------------------------------------------------------------- */

header("Content-Type: text/event-stream\n\n");
header('Cache-Control: no-cache');

// --- Config
require_once "./feed.config.php";
// --- Network
require_once "./http_req/call_api_function.php";
require_once "./http_req/call_api_function_post.php";
require_once "./http_req/call_delete_api.php";
// --- Interfaces
require_once "./interfaces/FeedInterface.php";

require_once "./utilities/FeedDownloader.php";
require_once "./model/Site.php";
require_once "./model/Style.php";
require_once "./model/Plot.php";
require_once "./utilities/MessagesHelper.php";
require_once "./utilities/CustomErrors.php";
require_once "./utilities/Matching.php";
require_once "./FeedTemplate.php";
require_once "./FeedStylePlot.php";
require_once "./FeedPlotOnly.php";
require_once "./FeedRentalLettings.php";
require_once "./FeedBLM.php";

use Config\Conf as Conf;
use FeedDownloader as FD;
use Sites\Site as Site;
use Styles\Style as Style;
use Plots\Plot as Plot;
use MatchingFeatures\{MatchingFeatures as match, MatchFromFloors as matchFloors};
use FeedRL\FeedRentalLettings as FeedRentalLettings;

/**
 * --------- Settings coming from request ---------
 */
    $config = new Conf();
    $dev  = $config->getDeveloperObject( $_GET['dev'] );
    $conf = $config->getServerConf( $_GET['server'] );
    $process_photos = (isset($_GET['process_photos']) && $_GET['process_photos'] == 'true') ? true : false;
// ------------------------------------------------

define('SERVER',         $conf->SERVER);
define('AUTH',           $conf->AUTH);
define('TOKEN',          $dev->token);
define('DEV_ID',         $dev->dev_id);
define('FEED_URL',       $dev->feed_url);
define('IMG_FOLDER',     $dev->IMG_FOLDER);
define('LOCAL_PATH',     $dev->local_path);
define('FEAT_XML',       $dev->feat_xml);
define('process_photos', $process_photos);
if (IMG_FOLDER != null) define('FILES', scandir(IMG_FOLDER));
define('ROOTPATH', dirname(__FILE__) . '/');
//print_r(get_defined_constants(true)['user']);


/* 
  ====================================================
                  Feed initialization
  ==================================================== 
*/
if ( LOCAL_PATH == null && FEED_URL != null) {
  $download = FD\Downloader::run( FEED_URL );

  if ($download->code == 200) {
    $xml         = new SimpleXMLElement($download->out);
    $development = $xml->developer->{'developer-region'}->development;

    if ( isset($development->style) ) {
      $feed = new FeedStylePlot($xml);
    } else if (isset($development[1]->plot)) {
      $feed = new FeedPlotOnly($xml);
    } else die('unknown feed type');

    $feed = new Feed($xml);
  }
  else 
  echo "data: $download->out\n\n"; 
}
else
{
  $expl   = explode('.', LOCAL_PATH);
  $length = sizeof($expl);
  $ext    = strtolower($expl[ $length-1 ]);

  if ( $ext == 'json' ) {
    $download = FD\Downloader::run_local_json( LOCAL_PATH );
    $feed = new FeedRentalLettings($download);
  } else if ( $ext == 'blm' ) {
    $feed = new FeedBLM( LOCAL_PATH );
  } else {
    $download = FD\Downloader::run_local( LOCAL_PATH );
    $feed     = new Feed($download);
  }

}
echo "data: END-OF-STREAM\n\n"; //Give browser a signal to stop re-opening connection
ob_end_flush();






/**
 *  FEED DOWNLOADER
 */
//print_r( FD\Downloader::run('https://www.redrow.co.uk/rr-api/data/importer/rightmove') );
//print_r( FD\Downloader::run_local('rightmove.xml')->developer );
//print_r( FD\Downloader::run_local('../feeds/xmls/barrat.xml'));

?>