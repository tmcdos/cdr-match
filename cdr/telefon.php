<?php 
include "DBASE.PHP";

$con2 = mysqli_connect(VOIP_HOST,VOIP_USER,VOIP_PASS) or die ('Cannot connect to Asterisk!');
$db2 = mysqli_select_db($con2,'asterisk');

$stm2 = mysqli_stmt_init($con2);
$query = 'SELECT uniqueid,src,cost
  FROM asteriskcdrdb.cdr CDR WHERE calldate BETWEEN ? AND ? AND dst RLIKE ? ORDER BY cost<>0,ABS(billsec-?) LIMIT 1';
if(!mysqli_stmt_prepare($stm2,$query)) trigger_error('Failed to prepare - '.$query.mysqli_error($con2),E_USER_ERROR);

$stm3 = mysqli_stmt_init($con2);
$query = 'UPDATE asteriskcdrdb.cdr SET cost=?,ext_num=? WHERE uniqueid = ?';
if(!mysqli_stmt_prepare($stm3,$query)) trigger_error('Failed to prepare - '.$query.mysqli_error($con2),E_USER_ERROR);


if(isset($_POST['up_gold']))
{
  $f = $_FILES['csv_gold'];
  if($f['error']==UPLOAD_ERR_OK AND $f['size']!=0)
  {
    $gold = fopen($f['tmp_name'],'r');
    $first = true;
    while(!feof($gold))
    {
      $input = fgetcsv($gold);
      if($first OR $input[9]==0)
      {
        $first = false;
        continue;
      }
      if(count($input)<5) break;
      $stamp = explode('/',substr($input[2],0,strpos($input[2],' ')));
      $cdr_stamp = $stamp[2].'-'.$stamp[0].'-'.$stamp[1];
      $beg_stamp = $cdr_stamp.' 00:00:00';
      $end_stamp = $cdr_stamp.' 23:59:59';
      $ext_num = $input[1]; // outbound CLIP
      $dst_ctr = $input[5]; // destination country
      $dst_phone = $input[6]; // destination phone number (withour country code)
      $cdr_tel = $dst_ctr.$dst_phone;
      $cdr_cost = $input[9]; // cost of phone call
      $cdr_duration = (int)$input[8]; // duration of phone call
      $rlike = '^9'.substr($dst_phone,1).'|^90'.$dst_phone.'|^900'.$cdr_tel;
			mysqli_stmt_bind_param($stm2,'sssi',$beg_stamp,$end_stamp,$rlike,$cdr_duration);
			mysqli_stmt_execute($stm2);
			mysqli_stmt_store_result($stm2);
			if(mysqli_stmt_num_rows($stm2))
			{
  			mysqli_stmt_bind_result($stm2,$uid,$int,$cena);
  			$x = mysqli_stmt_fetch($stm2);
		    if($cena==0)
		    {
    			mysqli_stmt_bind_param($stm3,'dss',$cdr_cost,$ext_num,$uid);
    			mysqli_stmt_execute($stm3);
    		}
		  }
		  else 
		  {
		    echo 'Not found Gold Telecom (date = '.$cdr_stamp.substr($input[2],10).', tel = +'.$cdr_tel.', duration = '.(int)$cdr_duration.')<br>';
		    ob_flush();
		  }
    }
    fclose($gold);
  }
}

if(isset($_POST['up_mtel']))
{
  $f = $_FILES['csv_mtel'];
  if($f['error']==UPLOAD_ERR_OK AND $f['size']!=0)
  {
    // 0 = date-time
    // 1 = outgoing CLIP number - our source
    // 2 = destination number (probably without country, at least for now)
    // 3 = description
    // 4 = answered duration
    // 5 = cost
    $mtel = fopen($f['tmp_name'],'r');
    $first = true;
    while(!feof($mtel))
    {
      $input = fgetcsv($mtel);
      if($first)
      {
        $first = false;
        continue;
      }
      if(count($input)<5) break;
      $datum = trim($input[0],' "');
      $datum = (2000+substr($datum,6,2)).'-'.substr($datum,3,2).'-'.substr($datum,0,2).substr($datum,8);
      $beg_stamp = substr($datum,0,10).' 00:00:00';
      $end_stamp = substr($datum,0,10).' 23:59:59';
      $ext_num = trim($input[1],' "'); // outbound CLIP
      $dest = trim($input[2],' "');
      $dest = str_replace('VMS#','0',$dest); // destination phone number
      $vreme = (int)trim($input[4],' "'); // duration of phone call
      $cost = trim($input[5],' "'); // cost of phone call
      if(!is_numeric($dest))
      {
        $err = 'This is not valid CSV file - there is invalid number ('.$dest.')';
        break;
      }
      $rlike = '^9'.$dest;
			mysqli_stmt_bind_param($stm2,'sssi',$beg_stamp,$end_stamp,$rlike,$vreme);
			mysqli_stmt_execute($stm2);
			mysqli_stmt_store_result($stm2);
			if(mysqli_stmt_num_rows($stm2))
			{
  			mysqli_stmt_bind_result($stm2,$uid,$int,$cena);
  			$x = mysqli_stmt_fetch($stm2);
		    if($cena==0)
		    {
    			mysqli_stmt_bind_param($stm3,'dss',$cost,$ext_num,$uid);
    			mysqli_stmt_execute($stm3);
    		}
		  }
		  else 
		  {
		    echo 'Not found MTel (date = '.$datum.', tel = '.$dest.', duration = '.$vreme.')<br>';
		    ob_flush();
		  }
    }
    fclose($mtel);
  }
}

	if($b = @file_get_contents('telefon.htm'))
	{
		if($err!='') $z = 'alert("'.mysql_real_escape_string($err).'");';
			else $z = '';
		$b = str_replace('<!--{ERROR}-->',$z,$b);

    $can_save = ($_SERVER['REMOTE_ADDR']=='11.22.33.44' OR substr($_SERVER['REMOTE_ADDR'],0,5)=='10.0.');

		// show phonebook - incoming
	 	$query = 'SELECT extension,destination,description FROM incoming WHERE LENGTH(extension)>6 ORDER BY extension';
	 	$res = mysqli_query($con2,$query) or trigger_error($query.'<br>'.mysqli_error($con2),E_USER_ERROR);
		$z = '';
		while($row = mysqli_fetch_array($res,MYSQL_NUM))
		{
		  list($who,$tel,$inf) = explode(',',$row[1]);
		  if($tel==0) continue;
			$z.='<tr><td>0'.$row[0].'</td><td>'.$tel.'</td><td>'.$row[2].'</td></tr>';
		}
		$b = str_replace('<tr><td>{INCOME}</td></tr>',$z,$b);
		// show phonebook - outgoing
	 	$query = 'SELECT extension,name,outboundcid FROM users WHERE outboundcid<>"" ORDER BY extension';
	 	$res = mysqli_query($con2,$query) or trigger_error($query.'<br>'.mysqli_error($con2),E_USER_ERROR);
		$ext = Array();
		while($row = mysqli_fetch_array($res,MYSQL_NUM))
		{
      $i = strpos($row[2],'<');
      $cid = substr($row[2],$i+1,strpos($row[2],'>')-$i-1);
  	 	$ext[$cid][] = Array($row[0],$row[1]);
		}
		$z = '';
    if(is_array($ext)) 
    {
      ksort($ext);
      foreach($ext as $cid=>$v)
      {
        $z.='<tr><td>'.$cid.'</td><td>';
        foreach($v as $w)
          $z.= $w[0].' = '.$w[1].'<br>';
        $z.='</td><td>&nbsp;</td></tr>';
      }
    }
		$b = str_replace('<tr><td>{OUTCOME}</td></tr>',$z,$b);
		
		// CDR report
		$query = 'SELECT ID,DEPARTMENT FROM DEPARTMENT ORDER BY DEPARTMENT';
	 	$result = mysql_query($query,$conn) or trigger_error($query.'<br>'.mysql_error($conn),E_USER_ERROR);
	 	$z = '<option value="0"> </option>';
	 	while($row = mysql_Fetch_array($result,MYSQL_NUM))
	 	  $z.='<option value="'.$row[0].'">'.$row[1].'</option>';
		$b = str_replace('<option value="0">{DEPART_ID}</option>',$z,$b);

		$b = str_replace('{VIS_RW}',$can_save ? '' : 'display:none',$b);

		echo $b;
	}
	else die('Could not find template - telefon.htm');
?>