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
        //$urn = "urn_example"; // string | The Base64 (URL Safe) encoded design URN
        $accept_encoding = "accept_encoding_example"; // string | If specified with `gzip` or `*`, content will be compressed and returned in a GZIP format.
        
        try {
            $Manifest = $derivativeApi->getManifest($urn);
            $this->ParseManifest($Manifest['derivatives']);
            // var_dump(($this->urns));

            foreach($this->urns as $key=>$item){
                // if($item->MIME != 'application/autodesk-f2d' && $key>40 && $key<50){
                //     continue;
                // }
                switch($item->MIME){
                    case "application/autodesk-svf":
                        //$item->Path->Files = $this->SVFDerivates($item, $accessToken->getAccessToken());
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
            // var_dump(($this->urns));
            

        } catch (Exception $e) {
            echo 'Exception when calling DerivativesApi->getManifest: ', $e->getMessage(), PHP_EOL;
        }

      }

      private function GetDerivative($manifest, $accessToken){
          // prepare to download the manifest
        // Get cURL resource
        $curl = curl_init();
        // Set some options - we are passing in a useragent too here
        $endpoint = self::$BASE_URL . self::$DERIVATIVE_PATH . $manifest;
        $token = "authorization: Bearer " . $accessToken;
        curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                $token,
                "Accept-Encoding: gzip, deflate",
              ),
          ));
        // Send the request & save response to $resp
        $resp = curl_exec($curl);
        if( !$resp ){
            echo curl_error($ch);
        }
        // Close request to clear up some resources
        curl_close($curl);
        var_dump($manifest);
        var_dump($resp);

        // $ret = stream_read($resp);
        $content;
        while( !streamWrapper::stream_eof($resp)){
            $content = $content . streamWrapper::stream_read($resp);
        }
        
        // $content = stream_get_contents($resp);
        var_dump($content);
        die;


        // $header_array = get_headers($endpoint, true);
        // $size = $header_array['Content-Length'];//获取远程app的大小
        // header("Content-type: application/octet-stream");
        // header("Accept-Encoding:  gzip, deflate");
        // header( $token );
        // header('Content-Disposition: attachment; filename="' . basename($endpoint) . '"');
        // header("Content-Length: ".$size);//. filesize($file)
        // readfile($endpoint);

        ///////////////////////////
        // $derivativeApi = new DerivativesApi( $accessToken);
        // $derivativeApi->apiClient
      
      }

      private function SVFDerivates($ManifestItem, $accessToken){
        $manifest = $this->GetDerivative($ManifestItem->Path->URN, $accessToken);

        $files = array();
        array_push($files, $MainifestItem->Path->BasePath); // add the BasePath
        array_push($files, GetAssets($manifest));

        return $files;
      }

      private function F2DDerivates($ManifestItem, $accessToken){
        $manifest = $this->GetDerivative($ManifestItem->Path->BasePath . "manifest.json.gz", $accessToken);
        $files = array();
        array_push($files, "manifest.json.gz");
        array_push($files, GetAssets($manifest));

        return $files;
      }

      private function GetAssets($mainfest){
          // TBD
        // List<string> files = new List<string>();

        // // for each "asset" on the manifest, add to the list of files (skip embed)
        // foreach (JObject asset in manifest["assets"])
        // {
        // System.Diagnostics.Debug.WriteLine(asset["URI"].Value<string>());
        // if (asset["URI"].Value<string>().Contains("embed:/")) continue;
        // files.Add(asset["URI"].Value<string>());
        // }

        // return files;          
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
        //   $path->LocalPath    = str_replace( "[/]?output/", "", $path->LocalPath );
        //TBD How to replace "/" ?   
        $path->LocalPath    = preg_replace("/output/", "", $path->LocalPath);

        return $path;
      }
}
