<?php
 
/*  Copyright 2012 Code for the People Ltd

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

/**
 *
 * @package WPEngine Clear URL Cache
 * @since 0.1
 */
class CFTP_OS_Map_Walk extends CFTP_OS_Map_Walk_Plugin {
	
	/**
	 * A version for cache busting, DB updates, etc.
	 *
	 * @var string
	 **/
	public $version;
	
	/**
	 * Let's go!
	 *
	 * @return void
	 **/
	public function __construct() {
		$this->setup( 'os-map-walk', 'plugin' );

		$this->add_action( 'wp_enqueue_scripts' );
		$this->add_action( 'the_content' );

		$this->version = 1;
		$this->errors = array();
	}
	
	// HOOKS
	// =====
	
	/**
	 * Hooks the WP wp_enqueue_scripts action.
	 * 
	 * @return void
	 */
	function wp_enqueue_scripts() {
		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Hooks the WP filter the_content to hurriedly shove
	 * things into the content with very little thought.
	 * 
	 * @param string $content The content
	 * @return string The content
	 */
	function the_content( $content ) {
?>
	<div id="map" style="height: 500px; border: 1px solid black;"></div>
	<script type="text/javascript" src="http://openspace.ordnancesurvey.co.uk/osmapapi/openspace.js?key=7A50CEDC27C52CF7E0405F0AF06062D9"></script>
	<script type="text/javascript">
	var osMap;
	function map_init() {
		var controls = [
			new OpenLayers.Control.Navigation(),
			new OpenLayers.Control.KeyboardDefaults(),
			new OpenSpace.Control.CopyrightCollection(),
			new OpenLayers.Control.ArgParser()
		];
		osMap = new OpenSpace.Map( 'map', { controls: controls } );
 
  		osMap.setCenter( new OpenSpace.MapPoint( 406830, 397400 ), 8 );
        osMap.addControl(new OpenSpace.Control.SmallMapControl());

		var points = [];
		points.push( { lon: 406850, lat: 398325, pos: 'tl' } );
		points.push( { lon: 407036, lat: 398321, pos: 'br' } );
		points.push( { lon: 407165, lat: 398505, pos: 'tr' } );
		points.push( { lon: 408185, lat: 397720, pos: 'tr' } );

		var vectorLayer = new OpenLayers.Layer.Vector("Vector Layer");

		var features = [];

		var walk_line_style = { strokeColor: "#CC0000", strokeOpacity: 0.25, strokeWidth: 2 };
		var walk_line_string = new OpenLayers.Geometry.LineString(points);
		var walk_line = new OpenLayers.Feature.Vector( walk_line_string, null, walk_line_style );
		features.push( walk_line );

		var waypoint_style = { pointRadius: 8, fillOpacity: 0, strokeColor: "#CC0000", strokeOpacity: 0.8, strokeWidth: 2 };
		for ( var i = 0; i < points.length; i++ ) {
			features.push( new OpenLayers.Feature.Vector( new OpenLayers.Geometry.Point( points[i].x, points[i].y ), null, waypoint_style ) );
		}

		vectorLayer.addFeatures( features );
		
		osMap.addLayer(vectorLayer);

		var marker_ids = [];
		for ( var i = 0; i < points.length; i++ ) {
			popup = new OpenLayers.Popup(
				'marker_' + i,
				new OpenLayers.LonLat( points[i].lon, points[i].lat ),
				new OpenLayers.Size( 66, 66 ),
				'<div class="osmap_marker_container" style=""><div class="osmap_marker_num osmap_marker_num_' + points[i].pos + '" style="">' + (i + 1) + '</div><div class="osmap_marker_circle" style=""></div></div>',
				false
			);
			osMap.addPopup( popup );
			marker_ids.push( '#marker_' + i );
		}
		jQuery( marker_ids.join( ',' ) )
			.css( { backgroundColor: 'transparent', margin: '-33px 0 0 -33px' } )
			.find( '.olPopupContent' )
				.css( { padding: 0 } );

		osMap.events.register('click', null, function(evt, a, b, c) {
			var click_point = osMap.getMapPointFromViewPortPx( evt.xy );
			console.log( "points.push(new OpenLayers.Geometry.Point( " + click_point.lon + ", " + click_point.lat + " ));" );
		});
	}
	jQuery( document ).ready( map_init );
	</script>
	<style>
		.osmap_marker_container {
			position: relative;height: 100%; width: 100%; margin: 0;
		}
		.osmap_marker_num {
			position: absolute; height: 20px; width: 20px; border-radius: 20px; background-color: #000; color: #fff; line-height: 20px; text-align: center;
		}
		.osmap_marker_num_tr {
			top: 0; right: 0;
		}
		.osmap_marker_num_tl {
			top: 0; left: 0;
		}
		.osmap_marker_num_bl {
			bottom: 0; left: 0;
		}
		.osmap_marker_num_br {
			bottom: 0; right: 0;
		}
		.osmap_marker_circle {
			width:26px;height:26px;border: 2px solid #000; border-radius: 20px; position: absolute; top: 50%; left: 50%; margin: -15px 0 0 -15px;
		}
	</style>
<?php
		return $content;
	}
	
	// CALLBACKS
	// =========
	
}

$GLOBALS[ 'cftp_os_map_walk' ] = new CFTP_OS_Map_Walk;

