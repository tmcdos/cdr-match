<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title>Cost of phone calls</title>
<meta http-equiv="content-type" Content="text/html;charset=utf-8">
</head>
<body topmargin="3" leftmargin="3" rightmargin="3" bottommargin="3">
<?php
include "DBASE.PHP";
$conn2 = mysql_connect(VOIP_HOST,VOIP_USER,VOIP_PASS) or die ('Cannot connect to Asterisk!');
$db2 = mysql_select_db('asterisk',$conn2);

function sec_time($a)
{
	if($a<60) return $a.' sec';
	elseif($a<3600) return round($a/60,1).' min';
	else return round($a/3600,1).' hours';
}

// preload departments
$query = 'SELECT ID,DEPARTMENT FROM DEPARTMENT';
$result = mysql_query($query,$conn) or trigger_error($query.'<br>'.mysql_error($conn),E_ERROR);
while($row = mysql_Fetch_array($result,MYSQL_NUM)) $depart[$row[0]] = $row[1];

if($_REQUEST['beg']!='') $beg = $_REQUEST['beg'];
  else $beg = date('1-m-Y');
if($_REQUEST['end']!='') $end = $_REQUEST['end'];
  else $end = date('t-m-Y');

echo '<h3 align="center">Cost of phone calls from <font color="red">'.$beg.'</font> to <font color="red">'.$end.'</font>'.($_REQUEST['dep']!=0 ? ' in department <font color="blue">'.$depart[$_REQUEST['dep']].'</font>' : '').'</h3>';

// preload department users
$user_list = Array();
if($_REQUEST['dep']!=0)
{
  $query = 'SELECT id FROM CLIENT WHERE DEPART_ID='.$_REQUEST['dep'];
  $result = mysql_query($query,$conn) or trigger_error($query.'<br>'.mysql_error($conn),E_USER_ERROR);
  while($row = mysql_fetch_array($result,MYSQL_NUM)) $user_list[] = $row[0];
}

$query = 'SELECT DATE_FORMAT(calldate,"%d-%m-%Y %H:%i:%s") datum,clid,1 depart_id,user_id,src,dst,cost,SEC_TO_TIME(duration) vreme,duration,uniqueid
  FROM asteriskcdrdb.cdr WHERE src<800 AND dst>800 AND billsec<>0 AND calldate BETWEEN "'.GDate($beg).'" AND "'.GDate($end).'"';
if($_REQUEST['dep']!=0) $query.=' AND user_id IN ('.(count($user_list)>0 ? implode(',',$user_list) : '-1').')';
$query.= ' ORDER BY calldate';
$result = mysql_query($query,$conn2) or trigger_error($query.'<br>'.mysql_error($conn2),E_USER_ERROR);
?>
  <TABLE ALIGN="CENTER" BORDER="1" CELLSPACING="0" CELLPADDING="4" BORDERCOLOR="black" style="font-size:9pt;font-family:Arial">
		<col align="center"><col><col><col><col align="center"><col><col align="center"><col align="right">
		<thead>
		<TR BGCOLOR="#00E099">
			<th>Timestamp</th>
			<th>Department</th>
			<th>Name from Asterisk</th>
			<th>Name from ERP</th>
			<th>Internal phone</th>
			<th>Destination</th>
			<th>Duration</th>
			<th>Cost</th>
		</tr>
		</theaD>
		<tbody>
<?php

$con_i = mysqli_connect(DATABASE_HOST,DATABASE_USER,DATABASE_PASSWORD) or die ('Cannot connect to MySQL!');
mysqli_select_db($con_i,DATABASE_NAME);
mysqli_query($con_i,'SET NAMES utf8');

$stm_1a = mysqli_stmt_init($con_i);
$query = 'SELECT DEPARTMENT FROM CLIENT LEFT JOIN DEPARTMENT D ON D.ID=DEPART_ID WHERE CLIENT.ID=?';
if(!mysqli_stmt_prepare($stm_1a,$query)) trigger_error('Failed to prepare - '.$query.mysqli_error($con_i),E_USER_ERROR);

$stm_1b = mysqli_stmt_init($con_i);
$query = 'SELECT ID,CLIENT FROM CLIENT WHERE TEL_INT=? ORDER BY DELETED LIMIT 1';
if(!mysqli_stmt_prepare($stm_1b,$query)) trigger_error('Failed to prepare - '.$query.mysqli_error($con_i),E_USER_ERROR);

$stm_1c = mysqli_stmt_init($con_i);
$query = 'SELECT CLIENT FROM CLIENT WHERE ID=?';
if(!mysqli_stmt_prepare($stm_1c,$query)) trigger_error('Failed to prepare - '.$query.mysqli_error($con_i),E_USER_ERROR);

$con_i2 = mysqli_connect(VOIP_HOST,VOIP_USER,VOIP_PASS) or die ('Cannot connect to Asterisk!');
mysqli_select_db($con_i2,'asterisk');
//mysqli_query($con_i2,'SET NAMES utf8');

$stm_2a = mysqli_stmt_init($con_i2);
$query = 'UPDATE asteriskcdrdb.cdr SET user_id=? WHERE uniqueid=?';
if(!mysqli_stmt_prepare($stm_2a,$query)) trigger_error('Failed to prepare - '.$query.mysqli_error($con_i2),E_USER_ERROR);

while($row = mysql_fetch_array($result,MYSQL_NUM))
{
  $tel_name = preg_replace('/\"([^\"]+)\"\s?\<[^\>]+\>/','$1',$row[1]);
  echo '<tr><td>'.$row[0].'</td><td>';
  if($row[3]!=0)
  {
		mysqli_stmt_bind_param($stm_1a,'i',$row[3]);
		mysqli_stmt_execute($stm_1a);
		mysqli_stmt_store_result($stm_1a);
		if(mysqli_stmt_num_rows($stm_1a))
		{
  		mysqli_stmt_bind_result($stm_1a,$user_dep);
  		$x = mysqli_stmt_fetch($stm_1a);
  		echo $user_dep;
  	}
    else echo '&nbsp;';
  }
  else
  {
		mysqli_stmt_bind_param($stm_1b,'i',$row[4]);
		mysqli_stmt_execute($stm_1b);
		mysqli_stmt_store_result($stm_1b);
    if(mysqli_stmt_num_rows($stm_1b))
    {
  		mysqli_stmt_bind_result($stm_1b,$user_id,$user_name);
  		$x = mysqli_stmt_fetch($stm_1b);
      $row[3] = $user_id;
      $crm[$row[3]] = $user_name;
  		mysqli_stmt_bind_param($stm_2a,'is',$row[3],$row[9]);
  		mysqli_stmt_execute($stm_2a);

  		mysqli_stmt_bind_param($stm_1a,'i',$row[3]);
  		mysqli_stmt_execute($stm_1a);
  		mysqli_stmt_store_result($stm_1a);
  		if(mysqli_stmt_num_rows($stm_1a))
      {
    		mysqli_stmt_bind_result($stm_1a,$user_dep);
    		$x = mysqli_stmt_fetch($stm_1a);
    		echo $user_dep;
      }
      else echo '&nbsp;';
    }
    else echo '&nbsp;';
  }
  echo '</td><td>'.$tel_name.'</td><td>';
  if($row[3]!=0)
  {
    $crm_name = $crm[$row[3]];
    if($crm_name!='') 
    {
      if($crm_name!=$tel_name) echo $crm_name;
        else echo '&nbsp;';
    }
    else
    {
  		mysqli_stmt_bind_param($stm_1c,'i',$row[3]);
  		mysqli_stmt_execute($stm_1c);
  		mysqli_stmt_store_result($stm_1c);
  		mysqli_stmt_bind_result($stm_1c,$crm_name);
  		$x = mysqli_stmt_fetch($stm_1c);
      $crm[$row[3]] = $crm_name;
      if($crm_name!=$tel_name) echo $crm_name;
        else echo '&nbsp;';
    }
  }
  else echo '&nbsp;';
  echo '</td><td>'.$row[4].'</td><td>'.substr($row[5],1).'</td><td>'.$row[7].'</td><td>'.($row[6]>0 ? number_format($row[6],4,'.','') : '0').'</td></tr>'.chr(13).chr(10);
  $suma += $row[6];
  $vreme+= $row[8];
}
?>
		</tbody>
		<tfoot>
		  <tr bgcolor="yellow" align="right" style="font-weight:bold">
		    <td colspan="6">TOTAL:</td>
		    <td><?php echo sec_time($vreme); ?></td>
		    <td><?php echo number_format($suma,2,'.',''); ?></td>
		  </tr>
	</table>
</body></html>