<?php
namespace Autodesk\ForgeServices;
use Autodesk\Forge\Client\Api\BucketsApi;
use Autodesk\Forge\Client\Model\PostBucketsPayload;
use Autodesk\Forge\Client\Api\ObjectsApi;
use Autodesk\Forge\Client\Api\DerivativesApi;
use Autodesk\Forge\Client\Model\Manifest;


class PathInfo
{
  private  $RootFileName;
  private  $LocalPath;
  private  $BasePath;
  private  $URN;
  private  $Files;

  public function __get($property_name){
    if(isset($this->$property_name)){
        return($this->$property_name);
    }else{
        return(NULL);
    }      
  }

    public function __set($property_name, $value){
        $this->$property_name = $value;
    }
};

class ManifestItem{
  private $Guid;
  private $MIME;
  private $Path;

  public function __get($property_name){
    if(isset($this->$property_name)){
        return($this->$property_name);
    }else{
        return(NULL);
    }      
  }

    public function __set($property_name, $value){
        $this->$property_name = $value;
    }

};

class DataManagement{

    const BASE_URL          = 'https://developer.api.autodesk.com/';
    const DERIVATIVE_PATH   = "derivativeservice/v2/derivatives/";   
    private $urns = array();
    private static $ROLES = array(
        "Autodesk.CloudPlatform.DesignDescription",
        "Autodesk.CloudPlatform.PropertyDatabase",
        "Autodesk.CloudPlatform.IndexableContent",
        "leaflet-zip",
        "thumbnail",
        "graphics",
        "preview",
        "raas",
        "pdf",
        "lod"
    );

    public function __construct(){
        set_time_limit(0);
    }    

    public function createOneBucket(){
         global $twoLeggedAuth;
         $accessToken = $twoLeggedAuth->getTokenInternal();
         
         // get the request body
         $body = json_decode(file_get_contents('php://input', 'r'), true);
         
         $bucketKey = $body['bucketKey'];
         // $policeKey = $body['policyKey'];
         $policeKey = "transient";
 
         $apiInstance = new BucketsApi($accessToken);
         $post_bucket = new PostBucketsPayload(); 
         $post_bucket->setBucketKey($bucketKey);
         $post_bucket->setPolicyKey($policeKey);
 
         try {
             $result = $apiInstance->createBucket($post_bucket);
             print_r($result);
         } catch (Exception $e) {
             echo 'Exception when calling BucketsApi->createBucket: ', $e->getMessage(), PHP_EOL;
         }   
      }
 
 
      /////////////////////////////////////////////////////////////////////////
      public function getBucketsAndObjects(){
         global $twoLeggedAuth;
         $accessToken = $twoLeggedAuth->getTokenInternal();
         
         $id = $_GET['id'];
         try{
             if ($id === '#') {// root
                 $apiInstance = new BucketsApi($accessToken);
                 $result = $apiInstance->getBuckets();
                 $resultArray = json_decode($result, true);
                 $buckets = $resultArray['items'];
                 $bucketsLength = count($buckets);
                 $bucketlist = array();
                 for($i=0; $i< $bucketsLength; $i++){
                     $bucketInfo = array('id'=>$buckets[$i]['bucketKey'],
                                         'text'=>$buckets[$i]['bucketKey'],
                                         'type'=>'bucket',
                                         'children'=>true
                     );
                     array_push($bucketlist, $bucketInfo);
                 }
                 print_r(json_encode($bucketlist));
             }
             else{
                 $apiInstance = new ObjectsApi($accessToken);
                 $bucket_key = $id; 
                 $result = $apiInstance->getObjects($bucket_key);
                 $resultArray = json_decode($result, true);
                 $objects = $resultArray['items'];
 
                 $objectsLength = count($objects);
                 $objectlist = array();
                 for($i=0; $i< $objectsLength; $i++){
                     $objectInfo = array('id'=>base64_encode($objects[$i]['objectId']),
                                         'text'=>$objects[$i]['objectKey'],
                                         'type'=>'object',
                                         'children'=>false
                     );
                     array_push($objectlist, $objectInfo);
                 }
                 print_r(json_encode($objectlist));
             }
         }catch(Exception $e){
             echo 'Exception when getting buckets/objects: ', $e->getMessage(), PHP_EOL;
         }
 
      }
 

      public function uploadFile(){
          global $twoLeggedAuth;
          $accessToken = $twoLeggedAuth->getTokenInternal();
          // $body = file_get_contents('php://input', 'r');
          // var_dump($body);
          
          $body = $_POST;
          $file = $_FILES;
          // $_SESSION['file'] = $file;
          // var_dump($_SESSION['file']);die;
          // var_dump($_FILES);die;
          // die;
  
          $apiInstance = new ObjectsApi($accessToken);
          $bucket_key  = $body['bucketKey']; 
          $fileToUpload    = $file['fileToUpload'];
          $filePath = $fileToUpload['tmp_name'];
          $content_length = filesize($filePath); 
  
          // $fileRead = fread($filePath, $content_length);
          
          try {
              $result = $apiInstance->uploadObject($bucket_key, $fileToUpload['name'], $content_length, $filePath );
              print_r($result);
          } catch (Exception $e) {
              echo 'Exception when calling ObjectsApi->uploadObject: ', $e->getMessage(), PHP_EOL;
          }
      } 


      public function downloadSVF( ){
        global $twoLeggedAuth;
        $accessToken = $twoLeggedAuth->getTokenInternal();

        $body = json_decode(file_get_contents('php://input', 'r'), true);
        $objectUrn = $body['objectName'];

        $svfList = $this->extractSVF($objectUrn, $accessToken);
        print_r(json_encode($svfList));
      }    


      private function extractSVF( $urn, $accessToken){
        $derivativeApi = new DerivativesApi( $accessToken);      
        try {
            $manifest = $derivativeApi->getManifest($urn);
            $this->parseManifest($manifest['derivatives']);
            foreach($this->urns as $key=>$item){
                switch($item->MIME){
                    case "application/autodesk-svf":
                        $item->Path->Files = $this->svfDerivates($item, $accessToken->getAccessToken());
                        break;
                    case "application/autodesk-f2d":
                        $item->Path->Files = $this->f2dDerivates($item, $accessToken->getAccessToken());
                        break;
                    case "application/autodesk-db":
                        $item->Path->Files = array(
                            "objects_attrs.json.gz",
                            "objects_vals.json.gz",
                            "objects_offs.json.gz",
                            "objects_ids.json.gz",
                            "objects_avs.json.gz",
                            $item->Path->RootFileName
                         );
                        break;
                  default:
                         $item->Path->Files = array(
                             $item->Path->RootFileName
                         );
                    break;
                }
            }
            $resourceList = array();
            // now organize the list for external usage
            foreach( $this->urns as $urnItem ){
                if( is_null($urnItem->Path->Files) )
                    continue;
                foreach( $urnItem->Path->Files as $fileItem ){
                    // response json format
                    $fileResource = array(
                        "FileName" => $fileItem,
                        "RemotePath" => self::DERIVATIVE_PATH . $urnItem->Path->BasePath . $fileItem,
                        "LocalPath"  => $urnItem->Path->LocalPath . $fileItem
                    );
                    
                    $resourceList[] = $fileResource;
                }
            }
            return $resourceList;
        } catch (Exception $e) {
            echo 'Exception when extracting the menifest: ', $e->getMessage(), PHP_EOL;
            error_log('Exception when extracting the menifest: ' . $e->getMessage());
        }
      }

      private function getDerivative($manifest, $accessToken){
        // urlencode the manifest
        $endpoint = self::BASE_URL . self::DERIVATIVE_PATH . urlencode($manifest);

        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $endpoint,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",          
          CURLOPT_HTTPHEADER => array(
            "accept-encoding: gzip, deflate",
            "authorization: Bearer " . $accessToken
          ),
        ));
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);        
        if ($err) {
            echo "cURL Error #:" . $err;
            error_log("cURL Error #:" . $err);
            return;
        } 

        $filename = substr(strrchr($manifest, "/"), 1); 
        file_put_contents($filename, $response);

        $manifestJson = NULL;
        // Parse the svf manifest
        if( strpos($manifest, ".gz") === false ){
            $zip = zip_open($filename);
            if (is_resource($zip)) {
                while ($zip_entry = zip_read($zip)) {
                    $entryName = zip_entry_name($zip_entry);
                    if( $entryName === "manifest.json"){
                        if (zip_entry_open($zip, $zip_entry, "r")) {
                            $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                            $manifestJson = json_decode($buf,true);
                            zip_entry_close($zip_entry);
                        }
                        break;
                    }
                }  
                zip_close($zip);
            }              
        }else{
            // Parse the f2d manifest
            $gz = gzopen ( $filename, 'r' );
            $content = '';
            while (!gzeof($gz)) {
                $content = $content . gzread($gz, 4096);
            }            
            $manifestJson= json_decode($content,true);
            gzclose ( $gz );
        }
        unlink($filename);
        return $manifestJson;
      }

      private function svfDerivates($manifestItem, $accessToken){
        $manifest = $this->getDerivative($manifestItem->Path->URN, $accessToken);
        if(!$manifest)
            return;
        $files = array();
        $files[] = $manifestItem->Path->RootFileName;
        return array_merge($files, $this->getAssets($manifest));
      }

      private function f2dDerivates($manifestItem, $accessToken){
        $manifest = $this->getDerivative($manifestItem->Path->BasePath . "manifest.json.gz", $accessToken);
        if(!$manifest)
            return;
        $files = array();
        $files[] = "manifest.json.gz";
        return array_merge($files, $this->getAssets($manifest));
      }

      private function getAssets($mainfest){
          $files = [];
          foreach( $mainfest['assets'] as $asset ){
              if( strpos($asset['URI'], "embed:/" ) === FALSE ) 
                $files[] = $asset['URI'];
          }
        return $files;   
      }

      private function parseManifest( $manifest ){
        foreach($manifest as $item ){
            if( $item['role'] &&  (in_array($item['role'], self::$ROLES)) ){
                $manifestItem       = new ManifestItem();
                $manifestItem->Guid = $item['guid'];
                $manifestItem->MIME = $item['mime'];
                $manifestItem->Path = $this->decomposeURN($item['urn']);

                $this->urns[] = $manifestItem;
            }
            if( $item['children']){
                $this->parseManifest($item['children']);
            }
        }
      }
      
      // Decompose the URN to local files
      private function decomposeURN( $encodedURN ){
        $urn                = str_replace('"', '\"', $encodedURN);
        $path               = new PathInfo();
        $path->URN          = $encodedURN;
        $path->RootFileName = substr(strrchr($urn, "/"), 1);
        $path->BasePath     = substr($urn, 0, strrpos($urn, "/")+1 );
        $path->LocalPath    = substr($path->BasePath, strpos($path->BasePath, "/")+1 );
        $path->LocalPath    = preg_replace("/^output\//", "", $path->LocalPath);
        return $path;
      }
}
