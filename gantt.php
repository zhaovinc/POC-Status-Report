<?php
//Report any errors
ini_set ("display_errors", "1");
error_reporting(E_ERROR);
ini_set('html_errors', 1); 
ini_set('error_reporting', E_ERROR); 
ini_set('display_errors', 1);

main();

function main()
{
  //Set the correct content type 
  header('content-type: image/png');
  date_default_timezone_set('Asia/Shanghai');
  
  fill_data();
  process_data();
  generate_image();
}

function read_excel($filename, $project_name)
{
  require_once 'excel_reader2.php';

  global $projects;

  $data = new Spreadsheet_Excel_Reader($filename);  
  $linecount = $data->rowcount();
  
  $format = 'n/j/Y';

  for($row = 2; $row < $linecount; $row++){
    if($data->bold($row, 'A')){
      $phase_name = trim($data->val($row, 'A')); 
      if(!empty($phase_name)){
	$start = $data->val($row, 'C');
	$finish = $data->val($row, 'D');
	if(!empty($start) && !empty($finish)){
		$projects[$project_name][$phase_name]['start'] = DateTime::createFromFormat($format, $start);
		$projects[$project_name][$phase_name]['finish'] = DateTime::createFromFormat($format, $finish);
	}
      }
    }
  }

  //dump_projects($projects);
}

function dump_projects($projs)
{
    foreach ($projs as $project_name => $project){
      echo $project_name . "<br>";
      foreach ($project as $phase_name => $phase){
	echo $phase_name . " start: " . $phase['start']->format(DATE_ATOM) . "<br>";
	echo $phase_name . " finish: " . $phase['finish']->format(DATE_ATOM) . "<br>";
      }
    }
}

function fill_data()
{
   
  $project_root = 'Research Projects';
  if ($dh = opendir($project_root)) {
    while (($filename = readdir($dh)) != false) {
      
      if ($filename == "." || $filename == "..") {
	continue;
      }
      if(!is_dir("${project_root}/$filename")){
	continue;
      }
      
      $project_name = $filename;

      if($dh2 = opendir("${project_root}/${project_name}")){
	while(($filename2 = readdir($dh2)) != false){
	  if(eregi('^project plan.*\.xls', $filename2)){
	    read_excel("${project_root}/${project_name}/${filename2}", $project_name);
	  }
	}
      }
      closedir($dh2);
    }
  }
  
  closedir($dh);
  /*
  
  // test data
  global $projects;

  $projects['automatic performance testing']['preparation']['start'] = new DateTime('2009-6-4');
  $projects['automatic performance testing']['preparation']['finish'] = new DateTime('2009-6-5');

  $projects['automatic performance testing']['POC Phase 1']['start'] = new DateTime('2009-6-8');
  $projects['automatic performance testing']['POC Phase 1']['finish'] = new DateTime('2009-6-19');

  $projects['automatic performance testing']['POC Phase 2']['start'] = new DateTime('2009-6-22');
  $projects['automatic performance testing']['POC Phase 2']['finish'] = new DateTime('2009-6-26');

  $projects['automatic performance testing']['POC Phase 3']['start'] = new DateTime('2009-6-29');
  $projects['automatic performance testing']['POC Phase 3']['finish'] = new DateTime('2009-7-16');
  */
}

function process_data()
{
  global $earlist_date, $latest_date, $projects;

  $earlist_date = new DateTime();
  $latest_date = new DateTime();

  foreach ($projects as $project){
    foreach ($project as $phase){
 
      if($phase['start']->format('N') == 1){
	// expand monday backward to sunday
	$phase['start']->sub(new DateInterval("P1D"));
      }

      if($phase['start']->format('N') == 5){
	// move friday to next monday
	$phase['start']->add(new DateInterval("P2D"));
      }      

      if($phase['finish']->format('N') == 5){
	// expand friday forward to sunday
	 $phase['finish']->add(new DateInterval("P2D"));
      }     
      
      if($phase['start'] < $earlist_date){
	$earlist_date = clone $phase['start'];
      }
      if($phase['finish'] > $latest_date){
	$latest_date = clone $phase['finish'];
      }
      
    }
  }  
}

function date_dif($from, $to)
{
    $ts1 = $from->format('Y-m-d');
    $ts2 = $to->format('Y-m-d');
    $dif = abs(strtotime($ts1)-strtotime($ts2));
 
    $dif /= 3600*24;
    
    return $dif;
}

function generate_image()
{
  global $earlist_date, $latest_date, $projects;
  
  $image_width = 1200;
  $image_height = 670;
  $hmargin = 40;
  $vmargin = 60;
  $axis_x_desc_width = 150;
  $axis_y_desc_width = 50;
  $bar_height = 30;
  $bar_spacing = 20;
 
  $phase_color_origin = array(
                  array(246, 58, 58),
                  array(18, 21, 164),   
                  array(15, 166, 241),  
                         			      array(153, 204, 0),
              			      array(96, 160, 192),   
            			      array(255, 204, 0),           
                         array(255, 160, 64), 

                   array(176, 46, 224),                  
                  array(160, 255, 32),   
 			      array(255, 153, 0),
                  array(64, 192, 128),    
                  array(255, 192, 32),

		          array(238, 238, 34)
			      );
  $global_alpha = 68;
 
  $effective_width = $image_width - $hmargin * 2 - $axis_x_desc_width; 
  $width_per_day = $effective_width / date_dif($earlist_date, $latest_date);
  
  $image = imagecreatetruecolor($image_width, $image_height);
  
  $back_color = imagecolorallocate($image, 210, 210, 210);
  $border_color = imagecolorallocate($image, 0, 0, 0);
  
  $client_region_color = imagecolorallocate($image, 252, 252, 252);
  $client_region_border_color = imagecolorallocate($image, 109, 109, 109);

  // image border
  imagefilledrectangle($image, 0, 0, $image_width - 1, $image_height - 1, $back_color);
  imagerectangle($image, 0, 0, $image_width - 1, $image_height - 1, $border_color);

  // client region
  imagefilledrectangle($image, 
		       $hmargin / 2  + $axis_x_desc_width, 
		       $vmargin / 2 + $axis_y_desc_width, 
		       $image_width - $hmargin / 2, 
		       $image_height - $vmargin / 2, 
		       $client_region_color);

  imagerectangle($image, 
		       $hmargin / 2 + $axis_x_desc_width, 
		       $vmargin / 2 + $axis_y_desc_width, 
		       $image_width - $hmargin / 2, 
		       $image_height - $vmargin / 2, 
		       $client_region_border_color);
   
  imagealphablending($image, true);
  
  $axis_color = imagecolorallocatealpha($image, 50, 50, 50, 70);
  $text_color = imagecolorallocatealpha($image, 0, 0, 0, 20);
  $text_font = 'C:/WINDOWS/Fonts/arial.ttf';
  $font_size = 8;

  // y axises  
  $sunday = $earlist_date;
  while(($sunday = next_sunday($sunday)) < $latest_date){
    $axis_x = $hmargin + $axis_x_desc_width + date_dif($earlist_date, $sunday) * $width_per_day;
    $axis_top = $vmargin / 2 + $axis_y_desc_width - 10;
    imagedashedline($image, 
		  $axis_x, 
		  $axis_top,
		  $axis_x, 
		  $image_height - $vmargin / 2,
		  $axis_color);
    
    $textbox = imagettfbbox($font_size, 0, $text_font, $sunday->format('n/j'));
    imagettftext($image, 
		 $font_size,
		 0, 
		 $axis_x - $textbox[2] / 2, 
		 $axis_top - ($textbox[3] - $textbox[5]),
		 $text_color, 
		 $text_font, 
		 $sunday->format('n/j'));
  }
  
  // today
  $today = new DateTime('now');
  $today_color = imagecolorallocatealpha($image, 255, 0, 0, 0);
  $axis_x = $hmargin + $axis_x_desc_width + date_dif($earlist_date, $today) * $width_per_day;
  $axis_top = $vmargin / 2 + $axis_y_desc_width - 40;
  imageline($image, 
	    $axis_x, 
	    $axis_top,
	    $axis_x, 
	    $image_height - $vmargin / 2,
	    $today_color);
  
  $textbox = imagettfbbox($font_size, 0, $text_font, 'today');
  imagettftext($image, 
	       $font_size,
	       0, 
	       $axis_x + 3, 
	       $axis_top + 4,
	       $today_color, 
	       $text_font, 
	       'today');
  
  // main part
  foreach($phase_color_origin as $color){
    $phase_color[] = imagecolorallocatealpha($image, $color[0], $color[1], $color[2], $global_alpha);
    $color_hsv = rgb2hsv($color);
    $color_hsv[1] += 0.05;
    $color_hsv = hsv2rgb($color_hsv);
    $phase_border_color[] = imagecolorallocatealpha($image, $color_hsv[0], $color_hsv[1], $color_hsv[2], $global_alpha);
  }

  $xoffset = 0;
  $yoffset = $vmargin + $axis_y_desc_width;
  
  $project_back_color = array(
			      imagecolorallocatealpha($image, 228, 228, 228, 60),
			      imagecolorallocatealpha($image, 255, 255, 255, 60));

  $line = 0;

  foreach($projects as $project_name => $project){  
    
    // background 
    imagefilledrectangle($image, 
			 $hmargin / 2  + $axis_x_desc_width + 1,
			 $yoffset - $bar_spacing / 2, 
			 $image_width - $hmargin / 2 - 1, 
			 $yoffset + $bar_height + $bar_spacing / 2, 
			 $project_back_color[++$line % 2]);     
    $index = 0;

    // phases
    foreach($project as $phase){
      $xoffset = $hmargin + $axis_x_desc_width + date_dif($earlist_date, $phase['start']) * $width_per_day;
      $phase_width = date_dif($phase['start'], $phase['finish']) * $width_per_day;
      
      imagefilledrectangle($image, 
			   $xoffset, 
			   $yoffset, 
			   $xoffset + $phase_width, 
			   $yoffset + $bar_height, 
			   $phase_color[$index]);     
      
      imagerectangle($image, 
			   $xoffset, 
			   $yoffset, 
			   $xoffset + $phase_width, 
			   $yoffset + $bar_height, 
			   $phase_border_color[$index]);     
      
      $index++;
    }
    
    // x axises
    
    imageline($image, 
	      $hmargin, 
	      $yoffset + $bar_height + $bar_spacing / 2,
	      $hmargin / 2  + $axis_x_desc_width, 
	      $yoffset + $bar_height + $bar_spacing / 2,
	      $axis_color);
    
    // project name
    printWordWrapped($image, 
		     $yoffset + 5,
		     $hmargin,  
		     $axis_x_desc_width - 15, 
		     $text_font,
		     $text_color,
		     $project_name,
		     12); // center aligned
   
    $yoffset += $bar_height + $bar_spacing;
  }

    // legend
      $legend_width = 100;
      $legend_height = 80;
      $legend_margin = 7;
      
        imagerectangle($image, 
			   $hmargin, 
			   $image_height  - $vmargin - $legend_height + 25, 
			   $hmargin + $legend_width, 
			   $image_height  - $vmargin + 25, 
			   $border_color);  
        
        $phase_names = array(
            "Preparation", 
            "Phase 1", 
            "Phase 2", 
            "Phase 3", 
            "Phase 4", 
            "Phase 5");
       
        $x = $hmargin  + $legend_margin;
        $y = $image_height  - $vmargin - $legend_height + 35;
       
        for($i = 0; $i < 5; $i++){
                  imagefilledrectangle($image, 
                    $x, 
                    $y, 
                    $x + 20, 
                    $y + 6, 
                    $phase_color[$i]);   
                  
                    imagettftext($image, 
                    $font_size,
                    0, 
                    $x + 25, 
                    $y  + 8,
                    $text_color, 
                    $text_font, 
                    $phase_names[$i]);
                    
                    $y += (6 + $legend_margin);
          }
  imagepng($image);
  
  //Clear up memory used
  imagedestroy($image);
}

function rgb2hsv($c) {
 list($r,$g,$b)=$c;
 $v=max($r,$g,$b);
 $t=min($r,$g,$b);
 $s=($v==0)?0:($v-$t)/$v;
 if ($s==0)
  $h=-1;
 else {
  $a=$v-$t;
  $cr=($v-$r)/$a;
  $cg=($v-$g)/$a;
  $cb=($v-$b)/$a;
  $h=($r==$v)?$cb-$cg:(($g==$v)?2+$cr-$cb:(($b==$v)?$h=4+$cg-$cr:0));
  $h=60*$h;
  $h=($h<0)?$h+360:$h;
 }
 return array($h,$s,$v);
}

// $c = array($hue, $saturation, $brightness)
// $hue=[0..360], $saturation=[0..1], $brightness=[0..1]
function hsv2rgb($c) {
 list($h,$s,$v)=$c;
 if ($s==0)
  return array($v,$v,$v);
 else {
  $h=($h%=360)/60;
  $i=floor($h);
  $f=$h-$i;
  $q[0]=$q[1]=$v*(1-$s);
  $q[2]=$v*(1-$s*(1-$f));
  $q[3]=$q[4]=$v;
  $q[5]=$v*(1-$s*$f);
  //return(array($q[($i+4)%5],$q[($i+2)%5],$q[$i%5]));
  return(array($q[($i+4)%6],$q[($i+2)%6],$q[$i%6])); //[1]
 }
} 

function next_sunday($day)
{
  $week_day = $day->format('N');
  $week_day = $week_day == 7 ? 7 : 7 - $week_day;

  $sunday = clone $day;
  $sunday->add(new DateInterval("P${week_day}D"));
  
  return $sunday;
}

function printWordWrapped(&$image, $top, $left, $maxWidth, $font, $color, $text, $textSize) {
  $words = explode(' ', strip_tags($text)); // split the text into an array of single words
  $line = '';
  while (count($words) > 0) {
    $dimensions = imagettfbbox($textSize, 0, $font, $line.' '.$words[0]);
    $lineWidth = $dimensions[2] - $dimensions[0]; // get the length of this line, if the word is to be included
    if ($lineWidth > $maxWidth) { // if this makes the text wider that anticipated
      $lines[] = $line; // add the line to the others
      $line = ''; // empty it (the word will be added outside the loop)
    }
    $line .= ' '.$words[0]; // add the word to the current sentence
    $words = array_slice($words, 1); // remove the word from the array
  }
  if ($line != '') { $lines[] = $line; } // add the last line to the others, if it isn't empty
  $lineHeight = $dimensions[1] - $dimensions[7]; // the height of a single line
  $height = count($lines) * $lineHeight; // the height of all the lines total
  // do the actual printing
  $i = 0;
  foreach ($lines as $line) {
    imagettftext($image, $textSize, 0, $left, $top + $lineHeight * $i, $color, $font, $line);
    $i++;
  }
  return $height;
}


?>