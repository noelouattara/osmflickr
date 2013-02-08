<?php
/**
* @package   osmflickr
* @subpackage osmflickr
* @author    DHONT René-Luc
* @copyright 2011 DHONT René-Luc
* @link      http://www.3liz.com
* @license    All rights reserved
*/

class photoCtrl extends jController {
    /**
    *
    */
    function index() {
        $f = jClasses::getService('osmflickr~phpFlickr');

        $isConnected = $f->isConnected();
        if ( ! $isConnected ) {
          jMessage::add('not connected', 'error');
          $rep = $this->getResponse('redirect');
          $rep->action = 'osmflickr~default:index';
          return $rep;
        }

        $photo_id = $this->param('photo_id');
        if ( !$photo_id ) {
          jMessage::add('photo_id not found', 'error');
          $rep = $this->getResponse('redirect');
          $rep->action = 'osmflickr~default:index';
          return $rep;
        }
        jClasses::inc('osmflickr~flickrPhoto');
        $secret = $this->param('secret');

        $rep = $this->getResponse('htmlmap');

        $user = $f->getUserSession();

        $rep->body->assign('isConnected', $isConnected);
        $rep->body->assign('user', $user);

        $photo = new flickrPhoto( $photo_id, $secret );
        $photo->getInfo();

        $rep->title = 'OsmFlickr - '.$photo->title;

        $rep->body->assign('photo', $photo);
        jLog::log(json_encode($photo->info),'debug');
        
        $tpl = new jTpl();
        $tpl->assign('photo', $photo);
        // Get photo osmtag
        $info = $tpl->fetch('photo_osmtag');
        // Get photo info
        $info .= $tpl->fetch('photo_info');
        $rep->body->assign('INFO', $info);

        // Add the json config as a javascript variable
        $rep->addJSCode("var addTagUrl = '".jUrl::get('osmflickr~photo:addTag',array('photo_id'=>$photo_id))."';");
        $rep->addJSCode("var getOsmTagsUrl = '".jUrl::get('osmflickr~photo:getOsmTags',array('photo_id'=>$photo_id,'secret'=>$secrect))."';");
        $rep->addJSCode("var cfgUrl = '".jUrl::get('osmflickr~photo:getProjectConfig')."';");
        $rep->addJSCode("var wmsServerURL = '".jUrl::get('osmflickr~photo:getCapabilities')."';");
        $rep->addJSCode("var osmUrl = '".jUrl::get('osmflickr~service:OpenStreetMap')."';");
        $rep->addJSCode("var xapiUrl = '".jUrl::get('osmflickr~service:xapi')."';");
        $rep->addJSCode("var nominatimUrl = '".jUrl::get('osmflickr~service:nominatim')."';");

        return $rep;
    }

    /**
     * Return the default GetCapabilities.
  * @param string $REQUEST Name of the request.
  * @param integer $tree_id Id of the tree.
  * @return Json string containing the project options.
  */
  function getCapabilities() {

    $rep = $this->getResponse('binary');

    # default values
    $wmsUrl = jUrl::get('osmflickr~photo:getCapabilities');

    $request = $this->param('REQUEST');
    if( !$request || $request == "GetCapabilities") {
      $rep->content = '
      <WMS_Capabilities xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.3.0" xmlns="http://www.opengis.net/wms" xsi:schemaLocation="http://www.opengis.net/wms http://schemas.opengis.net/wms/1.3.0/capabilities_1_3_0.xsd">
       <Name>WMS</Name>
       <Title>OpenStreetMap - Flickr</Title>
       <Abstract><![CDATA[]]></Abstract>
       <ContactInformation>
        <ContactPersonPrimary>
         <ContactPerson></ContactPerson>
        </ContactPersonPrimary>
        <ContactVoiceTelephone></ContactVoiceTelephone>
        <ContactElectronicMailAddress></ContactElectronicMailAddress>
       </ContactInformation>
       <Capability>
        <Request>
         <GetCapabilities>
          <Format>text/xml</Format>
          <DCPType>
           <HTTP>
            <Get>
             <OnlineResource xlink:type="simple" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="'.$wmsUrl.'"/>
            </Get>
           </HTTP>
          </DCPType>
         </GetCapabilities>
        </Request>
        <Layer>
        </Layer>
       </Capability>
      </WMS_Capabilities>';
      $rep->addHttpHeader ("mime/type", "text/xml");
    } else {
      $rep->setHttpStatus  ("404", "Not SUpported");
    }

    return $rep;
  }

  /**
  * Get the project configuration : map options and layers.
  * @param integer $tree_id Id of the tree
  * @return Json string containing the project options.
  */
  function getProjectConfig() {
    $rep = $this->getResponse('binary');

    # default values
    $bbox = '-85.0,-85.0,85.0,85.0';

    $rep->content = '{
  "options" : {
    "googleStreets":"False",
    "googleHybrid":"False",
    "googleSatellite":"False",
    "googleTerrain":"False",
    "osmMapnik":"True",
    "osmMapquest":"True",
    "projection" : {"proj4":"+proj=longlat +ellps=WGS84 +towgs84=0,0,0,0,0,0,0 +no_defs", "ref":"EPSG:4326"},
    "bbox":['.$bbox.'],
    "imageFormat" : "image/png",
    "minScale" : 10000,
    "maxScale" : 10000000,
    "zoomLevelNumber" : 10,
    "mapScales" : [100000,50000,25000,10000]
  },
  "layers" : {}
}';
    $rep->addHttpHeader ("mime/type", "text/json");
    return $rep;
  }

  function addTag() {
    $rep = $this->getResponse('binary');
    $rep->addHttpHeader ("mime/type", "text/plain");

    $f = jClasses::getService('osmflickr~phpFlickr');

    $isConnected = $f->isConnected();
    if ( ! $isConnected ) {
      $rep->content = 'not connected';
      return $rep;
    }

    $photo_id = $this->param('photo_id');
    if ( !$photo_id ) {
      $rep->content = 'photo_id not found';
      return $rep;
    }

    $tag = $this->param('tag');
    if ( !$photo_id ) {
      $rep->content = 'tag is mandatory';
      return $rep;
    }

    if ( $f->photos_addTags($photo_id, $tag) )
      $rep->content = 'success';
    else
      $rep->content = 'fail';

    return $rep;
  }

  function removeTag() {
    $rep = $this->getResponse('binary');
    $rep->addHttpHeader ("mime/type", "text/plain");

    $f = jClasses::getService('osmflickr~phpFlickr');

    $isConnected = $f->isConnected();
    if ( ! $isConnected ) {
      $rep->content = 'not connected';
      return $rep;
    }

    $tag_id = $this->param('tag_id');
    if ( !$tag_id ) {
      $rep->content = 'tag_id not found';
      return $rep;
    }

    if ( $f->photos_removeTag($tag_id) )
      $rep->content = 'success';
    else
      $rep->content = 'fail';

    return $rep;
  }

  function getOsmTags() {
    $rep = $this->getResponse('htmlfragment');
    $f = jClasses::getService('osmflickr~phpFlickr');

    $isConnected = $f->isConnected();
    if ( ! $isConnected ) {
      jMessage::add('not connected', 'error');
      return $rep;
    }

    $photo_id = $this->param('photo_id');
    if ( !$photo_id ) {
      jMessage::add('photo_id not found', 'error');
      return $rep;
    }
    jClasses::inc('osmflickr~flickrPhoto');
    $secret = $this->param('secret');

    $photo = new flickrPhoto( $photo_id, $secret );
    $photo->getInfo();

    $tpl = new jTpl();
    $tpl->assign('photo', $photo);
    // Get photo osmtag
    $info = $tpl->fetch('photo_osmtag');

    $rep->addContent( $info );
    return $rep;
  }
}
