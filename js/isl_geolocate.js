var isl_map;

jQuery( document ).ready(function() {
	// Get the input field
	var loc = document.getElementById('loc_lookup');

	// Execute a function when the user releases a key on the keyboard
	loc.addEventListener("keyup", function(event) {
		// Number 13 is the "Enter" key on the keyboard
		if (event.keyCode === 13) {
			// Cancel the default action, if needed
			event.preventDefault();
			// Trigger the button element with a click
			document.getElementById("isl_geo_btn").click();
		}
	});
});


function isl_geo() { 
	jQuery("isl_icon_search2").hide();
	jQuery("#isl_icon_wait").show();

	var loc = document.getElementsByName('loc_lookup')[0].value;
	console.log('loc='+loc);

	var data = {
		'action': 'isl_ajax_nominatim_query',
		'find_this': loc
	};

//console.log(ajax_object.ajax_url);
//console.log(data);
	jQuery.post(ajax_object.ajax_url, data, function(response) {

		var obj = JSON.parse(response);

		var stores_list = '<div class="stores_closest">'+obj.Message;
		if (obj.Count > 0) {
			var idx = 0;
			stores_list = '<div class="stores_closest">';
			for (idx = 0; idx < obj.Count; idx++) {
				stores_list += '<div class="store"><button onclick="islFlyTo('+obj.Stores[idx].lat+','+obj.Stores[idx].lng+')"><h5>'+obj.Stores[idx].name+'</h5></button><p class="distance">'+obj.Stores[idx].distance+' kms away</p><p class="addr">'+obj.Stores[idx].addr+'</p><p class="town">'+obj.Stores[idx].town+'</p><p class="phone"><a href="tel:'+obj.Stores[idx].phone+'">'+obj.Stores[idx].phone+'</a></p></div>';
			}
		}
		stores_list += '</div>';
		
		document.getElementById("isl_nearest_list").innerHTML = stores_list;
	});


	jQuery("#isl_icon_wait").hide();
	jQuery("#isl_icon_search").show();
}

function islFlyTo(lat, lng) {
	if (typeof isl_map === 'undefined') {
    console.log('isl_map not defined');
	} else {
		isl_map.flyTo([lat, lng],12);
	}
}