<?php
//exit;
ini_set("display_errors", "1");
error_reporting(E_ALL);

$link1 = mysqli_connect("localhost","root","eLs862paX7","willie_photos");
/*
$query1 = "select * from ps4_media_galleries  where gallery_id=13";
$res1 = mysqli_query($link1,$query1);
while ($details = mysqli_fetch_array($res1)) {
  $gmedia_id = $details['gmedia_id'];
  $del_query = "delete from ps4_media_prints where media_id='$gmedia_id'";
  echo $del_query;
  mysqli_query($link1,$del_query);
}

exit;



// for matching file names with the ktools database files.
$trans = array("=" => "");

$link1 = mysqli_connect("localhost","root","eLs862paX7","willie_photos");
$date="2015-10-28";
if(!$link1)
{
    die("Error:".mysqli_error($link1));
}
// generate gallery script
$replace_array = array("-","--","---");
//use this for 104.131.33.235
$category = array('1' =>'10', 
                  '2' =>'4',
                  '3' =>'9',
                  '4' =>'5',
                  '5' =>'6',
                  '7' =>'7',
                  '8' =>'8',
                  '9' =>'14',
                  '11' =>'11',
                  '201' =>'12',
                  '99' =>'13',
                  "fav"=>'3'
                 );

$panCat = array( 
  '1' => 20010,
  '2' => 20011,
  '3' => 20012,
  '4' => 20013,
  '5' => 20015,
  '6' => 20016,
  '7' => 20017,
  '8' => 20018,
  '9' => 20019,
  '10' => 20020,
  '12' => 20022,
  '13' => 20023,
  '14' => 20024,
  '15' => 20025
 );

$panGallery = array( 
  '1' => 28,
  '2' => 29,
  '3' => 30,
  '4' => 26,
  '5' => 31,
  '6' => 32,
  '7' => 33,
  '8' => 34,
  '9' => 35,
  '10' => 36,
  '12' => 37,
  '13' => 38,
  '14' => 39,
  '15' => 40
 );

// for linking the media to print size galleres.
$query3 = "SELECT * FROM photos_oldsite";
$res3 = mysqli_query($link1, $query3);

while($data3 = mysqli_fetch_array($res3))
{
    
    $old1 = str_replace("=", "", $data3['ID']);
    $file_id = preg_replace('/-+/','_', $old1);
    $file_path = "assets/library/".$date."/originals/".$file_id.".jpg";
    $new_file_name = $file_id.".jpg";
    $query4 = "SELECT * FROM ps4_media WHERE filename='$new_file_name'";
   
    $res4 = mysqli_query($link1,$query4);
    $data4 = mysqli_fetch_array($res4);

    
    if(file_exists($file_path) && !empty($data3['Format']))
    {
      //  print_r($file_path);
      //  echo "<br>";
        $gmedia_id = $data4['media_id'];
        if($data3['Format'] == 1)
        {
          $gallery_id = 24;
        }
        elseif ($data3['Format'] == 2)
        {
          $gallery_id = 25;
        }
        elseif ($data3['Format'] == 3)
        {
          $panratio = $data3['panratio'];
          echo $panratio;
          echo " : ";
          $gallery_id = $panGallery[$panratio];

          echo $gallery_id;
          echo "<br>"; 
        }

        $query7 = "SELECT * FROM ps4_media_galleries WHERE gmedia_id='$gmedia_id' AND gallery_id='$gallery_id'";
        echo $query7."<br>";
        $res7 = mysqli_query($link1,$query7);
        $data7 = mysqli_fetch_array($res7);
        if(empty($data7))
        {
            $query8 = "INSERT INTO ps4_media_galleries (gmedia_id,gallery_id) VALUES('$gmedia_id', '$gallery_id')";
            mysqli_query($link1, $query8);
        }
        

    }

}

exit;




*/
















/*
// use this for 104.236.234.131
$category = array('1' =>'11', 
                  '2' =>'5',
                  '3' =>'10',
                  '4' =>'6',
                  '5' =>'7',
                  '7' =>'8',
                  '8' =>'9',
                  '9' =>'15',
                  '11' =>'12',
                  '201' =>'13',
                  '99' =>'14'
                 );

// for updating all dates to Jan 1 2000
$update = "UPDATE ps4_media SET `date_added`='2000-01-01'";
mysqli_query($link1, $update);
*/
$query = "SELECT * FROM photos_oldsite";
$res = mysqli_query($link1, $query);
while($data = mysqli_fetch_array($res))
{
  $old = str_replace("=", "", $data['ID']);
  $filename = preg_replace('/-+/','_', $old);
  $query2 = "SELECT media_id FROM ps4_media WHERE filename LIKE '" . $filename . "%'";
  // echo $query2;
  // echo "<br>";
  //  echo $data['Description']." : ".$data['Date'];
  //  echo "<br>";
  $res2 = mysqli_query($link1,$query2);
  $data2 = mysqli_fetch_array($res2);

  $media_id = $data2['media_id'];
 // echo $data['Date'];
  if(!empty($data['Date']))
    {
      $pic_date = $data['Date'];
    }
    else
    {
      $pic_date = "2000-01-01"; 
    }
            
    $pic_description =$data['Description']; 
    $pic_Views = $data['Views'];
    $title = str_replace("_", " ", $filename);
    if(!empty($pic_description))
    {
        $insert_description = "UPDATE ps4_media SET date_added='$pic_date', title='$title', description='$pic_description', views='$pic_Views' WHERE media_id='$media_id'";
    //  print_r($insert_description);
     //  echo "<br>";
        mysqli_query($link1,$insert_description);

    }
    else
    {
      echo "Description missing for:";
      echo "<br>";
      echo $media_id." : ".$filename;
      echo "<br>";
    }

  



}


exit;




$missing_files2= array();

// for linking files in media table with media gallery table.

$query3 = "SELECT * FROM photos_oldsite";
$res3 = mysqli_query($link1, $query3);

while($data3 = mysqli_fetch_array($res3))
{
    
    $old1 = str_replace("=", "", $data3['ID']);
    $file_id = preg_replace('/-+/','_', $old1);
    $file_path = "assets/library/".$date."/originals/".$file_id.".jpg";
    $new_file_name = $file_id.".jpg";
    $query4 = "SELECT * FROM ps4_media WHERE filename='$new_file_name'";
   
    $res4 = mysqli_query($link1,$query4);
    $data4 = mysqli_fetch_array($res4);

    
    if(file_exists($file_path) && !empty($data3['Cat']))
    {
      //  print_r($file_path);
      //  echo "<br>";
        $gmedia_id = $data4['media_id'];
        $gallery_id = $category[$data3['Cat']];
        $query5 = "SELECT * FROM ps4_media_galleries WHERE gmedia_id='$gmedia_id' AND gallery_id='$gallery_id'";
        $res5 = mysqli_query($link1,$query5);
        $data5 = mysqli_fetch_array($res5);
        if(empty($data5))
        {
            $query6 = "INSERT INTO ps4_media_galleries (gmedia_id,gallery_id) VALUES('$gmedia_id', '$gallery_id')";
            mysqli_query($link1, $query6);
        }
        

    }
    else
    {
        $missed_file2 = $new_file_name;
        if(!empty($file_id))
        {
          $missing_files2[] = $missed_file2;  
        }
        
    }
// echo strpos($data3['description'], "favorite");
    // For adding photos to favorite gallery
    if(file_exists($file_path) && stripos($data3['Description'], "favorite") !== false)
    {
      //  print_r($file_path);
      //  echo "<br>";
        $gmedia_id = $data4['media_id'];
        $gallery_id = $category['fav'];
        $query5 = "SELECT * FROM ps4_media_galleries WHERE gmedia_id='$gmedia_id' AND gallery_id='$gallery_id'";
        $res5 = mysqli_query($link1,$query5);
        $data5 = mysqli_fetch_array($res5);
        if(empty($data5))
        {
            $query6 = "INSERT INTO ps4_media_galleries (gmedia_id,gallery_id) VALUES('$gmedia_id', '$gallery_id')";
            mysqli_query($link1, $query6);
        }
        

    }
}












 echo "<pre>";
 print("Missed files:");
 print_r($missing_files2);
 echo "</pre>";
 exit;
