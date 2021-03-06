<?

include "includes/start.inc.php";

#######################
# Quick Sanity check, to abort, if running to often

if ($db->getOne("SELECT fetcher_id FROM {$db->table_fetcher} WHERE fetched>DATE_SUB(NOW(),INTERVAL 1 HOUR) LIMIT 1")) {
	die("already run recently, dying.\n");
}

$fetched = 0;

#######################
# Download any images possible via 'query'

include "includes/fetching.inc.php";

if (!empty($CONF['query'])) {

	$start = 0;

	do {
		$row = $db->getRow("SELECT *,IF(fetched>DATE_SUB(NOW(),INTERVAL 7 DAY),1,0) `recent` FROM {$db->table_fetcher} WHERE `start` = $start AND `query` = ".$db->quote($CONF['query']) );

		if (!empty($row) && $row['recent'] && $row['images'] == 1000) {
			//recently fetched!
			$start = $row['last']+1;
		} else {
			list($total,$images,$last) = getImages($CONF['query'],$start);
			$fetched+=$images;

                	$updates= array();
			$updates['query'] = $CONF['query'];
			$updates['images'] = $images;
			$updates['start'] = $start;
			$updates['last'] = $last;
			$updates['total'] = $total;
			$updates['fetched'] = "NOW()";
			
	                $sql = $db->updates_to_insertupdate($db->table_fetcher,$updates);

			print_r($updates);
			print "<br>\n";
        	        $db->query($sql);
			flush();

			if (empty($images) || $images < 1000)
				break;
			
			if ($start)
				sleep(5);

			$start = $last+1;
		}
		$c++;
	} while ($fetched < 10000);
}

######################
# If fetched anything need to update our stats

if (!empty($fetched)) {
	print "Fetched $fetched images<br>\n";
	update_square_images();
	update_image_point();
}

######################
# Check if there are any squares without lat/long, so can plot maps
# /todo this could be moved perhaps to admin-squares.php

if ($db->getOne("SELECT square_id FROM {$db->table_square} WHERE wgs84_lat=0 LIMIT 1")) {
	include "includes/conversionslatlong.class.php";
	$conv = new ConversionsLatLong();
	$updates=array();
	foreach ($db->getAll("SELECT square_id,e,n,grid_reference FROM {$db->table_square} WHERE wgs84_lat=0") as $row) {
		$func = (strlen($row['grid_reference'])==5)?'irish_to_wgs84':'osgb36_to_wgs84';
		list($updates['wgs84_lat'],$updates['wgs84_long']) = $conv->{$func}($row['e'],$row['n']);		

	        $sql = $db->updates_to_update($db->table_square,$updates,'square_id',$row['square_id']);

		print ". ";
		$db->query($sql);
	}
}

######################
# All done

print "<hr/>.\n";


