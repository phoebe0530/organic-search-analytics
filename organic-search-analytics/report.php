<?php $titleTag = "Reporting | Organic Search Analytics"; ?>

<?php $displayReport = ( count( $_GET ) > 0 ); ?>
<?php $displayReportToggleHide = ( $displayReport ? ' style="display:none;"' : '' ); ?>

<?php include_once('inc/html/_head.php'); ?>

<?php $reports = new Reports(); ?>

<?php
	if( isset( $_GET['savedReport'] ) ) {
		/* Used save report parameters */
		/* Load Reporting Class */
		$reports = new Reports();
		/* Get parameters for report */
		$reportParams = $reports->getSavedReport($_GET['savedReport']);
	} else {
		/* Use query parameters */
		$reportParams = $_GET;
	}
?>

	<?php include_once('inc/html/_alert.php'); ?>
	<h1>Organic Search Analytics Reporting</h1>

	<div id="reportSettings" class="expandable col col50">
		<h2>Report Settings</h2>
		<div class="expandingBox"<?php echo $displayReportToggleHide ?>>
			<?php include_once( 'inc/html/reportSettings.php' ); ?>
		</div>
	</div>

	<div id="reportQuickLinks" class="expandable col col50">
		<h2>Report Custom Links</h2>
		<div class="expandingBox"<?php echo $displayReportToggleHide ?>>
			<p>To add a report to Quick Links, generate a report using the parameters above and choose the <i>Save this Report to Quick Links</i> link.</p>
			<?php echo $reports->getSavedReportsByCategoryHtml( $reports->getSavedReportsByCategory() ); ?>
		</div>
	</div>
	<div class="clear"></div>

	<?php
	if( $reportParams ) {
		$reportDetails = $reports->getReportQueryAndHeading( $reportParams );
		$groupBy = $reportDetails['groupBy'];
	}
	?>

	<?php if( isset( $reportDetails ) ) { ?>
		<h2><?php echo implode( ", ", $reportDetails['pageHeadingItems'] ); ?></h2>

		<?php
		$reports = new Reports(); //Load Reporting Class

		/* Get saved report categories */
		$reportCategories = '<select name="reportCatExisting">';
		foreach( $reports->getSavedReportCategories() as $key => $category ) {
			$reportCategories .= '<option value="' . $key . '">'. $category['name'] . '</option>';
		}
		$reportCategories .= '</select>';

		/* Get save report form and insert dynamic values */
		$saveReportContent = file_get_contents( $GLOBALS['basedir'] . "/inc/html/_saveReport.php" );
		$saveReportContent = preg_replace( '/{{report_params}}/', urlencode( json_encode( $reportParams ) ), $saveReportContent );
		$saveReportContent = preg_replace( '/{{report_categories}}/', $reportCategories, $saveReportContent );
		?>

		<?php if( ! isset( $_GET['savedReport'] ) ) { ?>
			<?php echo $saveReportContent; ?>
		<?php } ?>

		<?php
			$reportQuery = "SELECT " . $groupBy . ", count(" . ( $groupBy != "query" ? 'DISTINCT ' : '' ) . "query) as 'queries', sum(impressions) as 'impressions', sum(clicks) as 'clicks', avg(avg_position) as 'avg_position' FROM ".$mysql::DB_TABLE_SEARCH_ANALYTICS." " . $reportDetails['whereClauseTable'] . "GROUP BY " . $groupBy . " ORDER BY " . $reportDetails['sortBy'] . " ASC";

			/* Get MySQL Results */
			$outputTable = $outputChart = array();
			if( $resultTable = $GLOBALS['db']->query($reportQuery) ) {
				while ( $rowsTable = $resultTable->fetch_assoc() ) {
					$outputTable[] = $rowsTable;
				}
			}

			/* If Results */
			if( count($outputTable) > 0 ) {
				/* Put MySQL Results into an array */
				$rows = array();
				for( $r=0; $r < count($outputTable); $r++ ) {
					$rows[ $outputTable[$r][$groupBy] ] = array( "queries" => $outputTable[$r]["queries"], "impressions" => $outputTable[$r]["impressions"], "clicks" => $outputTable[$r]["clicks"], "avg_position" => $outputTable[$r]["avg_position"] );
				}

				/* Build an array for chart data */
				$jqData = array( $groupBy => array(), "impressions" => array(), "clicks" => array(), "ctr" => array(), "avg_position" => array() );

				foreach ( $rows as $index => $values ) {
					$jqData[$groupBy][] = $index;
					$jqData['impressions'][] = $values["impressions"];
					$jqData['clicks'][] = $values["clicks"];
					$jqData['ctr'][] = ( $values["clicks"] / $values["impressions"] ) * 100;
					$jqData['avg_position'][] = $values["avg_position"];
				}

				$num = count( $jqData[$groupBy] );
				$posString = "";
				$posMax = 0;
				for( $c=0; $c<$num; $c++ ) {
					if( $c != 0 ) {
						$posString .= ",";
					}
					$posString .= "['".$jqData[$groupBy][$c]."',".$jqData['avg_position'][$c]."]";
					if( $jqData['avg_position'][$c] > $posMax ) { $posMax = $jqData['avg_position'][$c]; }
				}
				?>

				<div id="reportchart"></div>
				<div id="reportChartContainer">
					<div class="button" id="zoomReset">Reset Zoom</div>
					<div id="chartDataCallout"></div>
				</div>
				<div class="clear"></div>

				<script type="text/javascript">
				$(document).ready(function(){
					<?php if( preg_match( '/date/', $groupBy ) ) { ?>
							var line1=[<?php echo $posString ?>];
							var plot2 = $.jqplot('reportchart', [line1], {
									title:'Average Position<?php echo ( strlen( $reportDetails['chartLabel'] ) > 0 ?" | " . $reportDetails['chartLabel'] . "":"") ?>',
									axes:{
										xaxis:{
											renderer:$.jqplot.DateAxisRenderer,
											tickRenderer: $.jqplot.CanvasAxisTickRenderer,
											tickOptions:{
												formatString:'%m-%d-%y',
												angle: -30
											},
										},
										yaxis:{
											max: 1,
											min: <?php echo $posMax ?>,
											tickOptions:{
												formatString:'%i'
											},
											label:'SERP Position',
											labelRenderer: $.jqplot.CanvasAxisLabelRenderer
										}
									},
									highlighter: {
										show: true,
										tooltipAxes: 'xy',
										useAxesFormatters: true,
										showTooltip: true
									},
									series:[{lineWidth:4, markerOptions:{style:'square'}}],
									cursor:{
										show: true,
										zoom: true,
									}
							});
							
					<?php } elseif( $groupBy ==  "query" ) { ?>
							var plot2 = $.jqplot('reportchart', [[<?php echo $posString ?>]], {
									title:'Default Bar Chart',
									// animate: !$.jqplot.use_excanvas,
									seriesDefaults:{
											renderer:$.jqplot.BarRenderer,
											rendererOptions: {
												varyBarColor: true,
												showDataLabels: true
											},
									},
									legent: {
										show: true
									},
									axesDefaults: {
											tickRenderer: $.jqplot.CanvasAxisTickRenderer ,
											tickOptions: {
												angle: 30,
												fontSize: '10pt'
											}
									},
									axes:{
										xaxis:{
											renderer: $.jqplot.CategoryAxisRenderer,
											pointLabels: { show: true }
										}
									},
									cursor:{
										show: true,
										zoom: true,
									},
									highlighter: {
										show: true,
										tooltipAxes: 'xy',
										useAxesFormatters: false,
										showTooltip: true,
										tooltipFormatString: '%s'
									}
							});

							/* Click displays information on HTML page */
							$('#reportchart').bind('jqplotDataClick', 
									function (ev, seriesIndex, pointIndex, data) {
											$('#chartDataCallout').html('series: '+seriesIndex+', point: '+pointIndex+', data: '+data);
									}
							);
					<?php } ?>

					/* Zoom Reset */
					$('#zoomReset').click(function() { plot2.resetZoom() });
				});
				</script>


				<?php if( $reportDetails['sortDir'] == 'desc' ) { $rows = array_reverse( $rows ); } ?>

				<?php
					if( preg_match( '/\(date\)/', $groupBy ) ) {
						$colHeadingPrimary = substr( $groupBy, 0, strpos( $groupBy, '(' ) );
					} else {
						$colHeadingPrimary = $groupBy;
					}
				?>

				<table class="sidebysidetable sidebysidetable_col sidebysidetable_col1">
					<tr>
						<td><?php echo ucfirst( strtolower( $colHeadingPrimary ) ) ?></td>
					</tr>
					<?php
					foreach ( $rows as $index => $values ) {
						echo '<tr><td>' . $index . '</td></tr>';
					}
					?>
				</table>

				<table class="sidebysidetable sidebysidetable_col sidebysidetable_col2">
					<tr>
						<td>Queries</td><td>Impressions</td><td>Clicks</td><td>Avg Position</td>
					</tr>
					<?php
					foreach ( $rows as $index => $values ) {
						echo '<tr><td>' . number_format( $values["queries"] ) . '</td><td>' . number_format( $values["impressions"] ) . '</td><td>' . number_format( $values["clicks"] ) . '</td><td>' . number_format( $values["avg_position"], 2 ) . '</td></tr>';
					}
					?>
				</table>

				<table class="sidebysidetable sidebysidetable_col sidebysidetable_col3">
					<tr>
						<td>CTR</td>
					</tr>
					<?php
					foreach ( $rows as $index => $values ) {
						echo '<tr><td>' . number_format( ( $values["clicks"] / $values["impressions"] ) * 100, 2 ) . '%</td></tr>';
					}
					?>
				</table>
			<?php
			}
		?>
	<?php } ?>
	<div class="clear"></div>

<?php include_once('inc/html/_foot.php'); ?>