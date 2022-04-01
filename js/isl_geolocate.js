var isl_map;

jQuery( document ).ready(function() {
	// Get the input field
	var loc = document.getElementById('loc_lookup');

	if (loc) {
		// Execute a function when the user releases a key on the keyboard
		loc.addEventListener("keyup", function(event) {
			// Number 13 is the "Enter" key on the keyboard
			if (event.keyCode === 13) {
				// Cancel the default action, if needed
				event.preventDefault();
				// Trigger the button element with a click
				document.getElementById("isl_geo_store_search_btn").click();
			}
		});
	}

	// Make the dropdown auto-select
	var store_dropdown = document.getElementById('store_cat_lookup');
	if (store_dropdown) {
		store_dropdown.onchange();
	}
	
});


function isl_geo(country, max_stores, cats, tags) { 
	if (jQuery('#isl_icon_search').length > 0) {
		jQuery("#isl_icon_search").hide();
		jQuery("#isl_icon_wait").show();
	}

	var loc = '';
	var extra_data = '';

	if (jQuery('#loc_lookup').length > 0) {
		loc = document.getElementsByName('loc_lookup')[0].value;
	} else if (jQuery('#isl_street_address1').length > 0) {
		loc = document.getElementsByName('isl_street_address1')[0].value;

		// If addr2 is specified, use this in preference to addr1
		extra_data = document.getElementsByName('isl_street_address2')[0].value;
		if (extra_data.length > 0) {
			loc= extra_data;
		}

		extra_data = document.getElementsByName('isl_town')[0].value;
		if (extra_data.length > 0) {
			loc += ' '+extra_data;
		}
		extra_data = document.getElementsByName('isl_state')[0].value;
		if (extra_data.length > 0) {
			loc += ' '+extra_data;
		}
		extra_data = document.getElementsByName('isl_postcode')[0].value;
		if (extra_data.length > 0) {
			loc += ' '+extra_data;
		}
		extra_data = document.getElementsByName('isl_country')[0].value;
		if (extra_data.length > 0) {
			loc += ' '+extra_data;
		}
	}

	if (jQuery('#isl_chkCats').length > 0) {
		// Only check of the checkboxes are displayed
		cats = '';

		jQuery('.isl_chkCats:checkbox:checked').each(function () {
			cats += (this.checked ? jQuery(this).val()+"," : "");
		});
		
		if (cats.endsWith(",")) {
			cats = cats.substring(0, cats.length-1);
		}
	}
	console.log('cats='+cats);

	if (jQuery('#isl_chkTags').length > 0) {
		// Only check of the checkboxes are displayed
		tags = '';

		jQuery('.isl_chkTags:checkbox:checked').each(function () {
			tags = (this.checked ? jQuery(this).val()+"," : "");
		});
		
		if (tags.endsWith(",")) {
			tags = tags.substring(0, tags.length-1);
		}
	}
	console.log('tags='+tags);

	console.log('loc='+loc);

	var data = {
		'action': 'isl_ajax_geoloc_query',
		'find_this': loc,
		'country': country,
		'max_stores': max_stores,
		'cats': cats,
		'tags': tags,
	};

//console.log(ajax_object.ajax_url);
//console.log(data);
	jQuery.post(ajax_object.ajax_url, data, function(response) {

		var obj = JSON.parse(response);
//console.log(obj);
		if ( (max_stores == 0) && (document.getElementById("isl_lat")) ) {
			document.getElementById("isl_lat").value = obj.Stores[0].lat;
			document.getElementById("isl_lng").value = obj.Stores[0].lng;

		} else {
			var stores_list = '<div class="stores_closest">'+obj.Message;
			if (obj.Count > 0) {
				var idx = 0;
				stores_list = '<div class="stores_closest">';
				for (idx = 0; idx < obj.Count; idx++) {
					stores_list += '<div class="store"><button onclick="islFlyTo('+obj.Stores[idx].lat+','+obj.Stores[idx].lng+')"><h5>'+obj.Stores[idx].name+'</h5></button><p class="distance">'+obj.Stores[idx].distance+' kms away</p><p class="addr">'+obj.Stores[idx].addr+'</p><p class="town">'+obj.Stores[idx].town+'</p>';
					
					var web_url = obj.Stores[idx].web;
					if (web_url) {
						stores_list += '<p class="web"><a href="'+web_url+'" target="_blank">'+web_url+'</a></p>';
					}

					stores_list += '<p class="phone"><a href="tel:'+obj.Stores[idx].phone+'">'+obj.Stores[idx].phone+'</a></p></div>';
				}
			}
			stores_list += '</div>';
			
			document.getElementById("isl_nearest_list").innerHTML = stores_list;
		}
	});

	if (jQuery('#isl_icon_search').length > 0) {
		jQuery("#isl_icon_wait").hide();
		jQuery("#isl_icon_search").show();
	}
}

function islFlyTo(lat, lng) {
	if (typeof isl_map === 'undefined') {
    console.log('isl_map not defined');
	} else {
		isl_map.flyTo([lat, lng],12);
	}
}






function isl_get_stores_by_cat() { 

	var cat = '';
	var extra_data = '';

	if (jQuery('#store_cat_lookup').length > 0) {
		cat = document.getElementById('store_cat_lookup').value;
	}

	console.log('cat='+cat);

	var data = {
		'action': 'isl_ajax_list_stores',
		'find_this': cat,
	};

//console.log(ajax_object.ajax_url);
//console.log(data);
	jQuery.post(ajax_object.ajax_url, data, function(response) {
		var obj = JSON.parse(response);
		var stores_list = obj.html;
		
		document.getElementById("isl_store_list").innerHTML = stores_list;
	});

}