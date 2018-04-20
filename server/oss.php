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

    public static $BASE_URL = 'https://developer.api.autodesk.com/';
    public static $DERIVATIVE_PATH = "derivativeservice/v2/derivatives/";   
    // public static $DERIVATIVE_PATH = "modelderivative/v2/designdata/";
    
    public static $urn = "dXJuOmFkc2sub2JqZWN0czpvcy5vYmplY3Q6cGhwc2FtcGxlYnVja2V0L3dvcmtzaG9wX2JpbV9waHAucnZ0";
    
    
    

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
             echo 'Exception when calling ObjectsApi->getObjects: ', $e->getMessage(), PHP_EOL;
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


      public function DownloadSVF( ){
        global $twoLeggedAuth;
        $accessToken = $twoLeggedAuth->getTokenInternal();
        $this->ExtractSVF(self::$urn, $accessToken);
      }    

      private function ExtractSVF( $urn, $accessToken){
        $derivativeApi = new DerivativesApi( $accessToken);
        $accept_encoding = "accept_encoding_example"; // string | If specified with `gzip` or `*`, content will be compressed and returned in a GZIP format.
        
        try {
            $Manifest = $derivativeApi->getManifest($urn);
            $this->ParseManifest($Manifest['derivatives']);
            foreach($this->urns as $key=>$item){
                switch($item->MIME){
                    case "application/autodesk-svf":
                        $item->Path->Files = $this->SVFDerivates($item, $accessToken->getAccessToken());
                        break;
                    case "application/autodesk-f2d":
                        $item->Path->Files = $this->F2DDerivates($item, $accessToken->getAccessToken());
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
        } catch (Exception $e) {
            echo 'Exception when calling DerivativesApi->getManifest: ', $e->getMessage(), PHP_EOL;
        }

      }

      private function GetDerivative($manifest, $accessToken){
        // urlencode the manifest
        $endpoint = self::$BASE_URL . self::$DERIVATIVE_PATH . urlencode($manifest);

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
            return;
        } 

        $filename = substr(strrchr($manifest, "/"), 1); 
        file_put_contents($filename, $response);

        // Parse the svf and f2d manifest
        if( strpos($manifest, ".gz") === false ){


        }else{
            // Parse the manifest stream.
            $zip = new ZipArchive();
            $buffer_size = 4096; // read 4kb at a time
            $file = gzopen($filename, 'wb');
            
            $str='';
            while(!gzeof($file)) {
                $str.=gzread($file, $buffer_size);
            }
            gzclose($file);
            var_dump(json_decode($str,true));
        }
        unlink($filename);
      }

      private function SVFDerivates($ManifestItem, $accessToken){
        $manifest = $this->GetDerivative($ManifestItem->Path->URN, $accessToken);

        if(!$manifest)
            return;

        $files = array();
        array_push($files, $ManifestItem->Path->BasePath); // add the BasePath
        array_push($files, GetAssets($manifest));

        return $files;
      }

      private function F2DDerivates($ManifestItem, $accessToken){
        $manifest = $this->GetDerivative($ManifestItem->Path->BasePath . "manifest.json.gz", $accessToken);

        if(!$manifest)
            return;

        $files = array();
        array_push($files, "manifest.json.gz");
        array_push($files, GetAssets($manifest));

        return $files;
      }

      private function GetAssets($mainfest){
          // TBD
          $files = [];
          foreach( $manifest['assets'] as $asset ){
              if( strpos($asset['URI'], "embed:/" ) !== false ) 
                    push_back($files, $asset['URI']);
          }
        return $files;   
      }

      private function ParseManifest( $manifest ){
        foreach($manifest as $item ){
            if( $item['role'] &&  (in_array($item['role'], self::$ROLES)) ){
                $manifestItem = new ManifestItem();
                $manifestItem->Guid = $item['guid'];
                $manifestItem->MIME = $item['mime'];
                $manifestItem->Path = $this->DecomposeURN($item['urn']);

                array_push($this->urns, $manifestItem);
            }
            if( $item['children']){
                $this->ParseManifest($item['children']);
            }
        }
      }
      
      // Decompose the URN to local files
      private function DecomposeURN( $encodedURN ){
        $urn = str_replace('"', '\"', $encodedURN);
        $path = new PathInfo();
        $path->URN = $encodedURN;
        $path->RootFileName = substr(strrchr($urn, "/"), 1);
        $path->BasePath     = substr($urn, 0, strrpos($urn, "/")+1 );
        $path->LocalPath    = substr($path->BasePath, strpos($path->BasePath, "/")+1 );
        $path->LocalPath    = preg_replace("/^output\//", "", $path->LocalPath);

        return $path;
      }
}
