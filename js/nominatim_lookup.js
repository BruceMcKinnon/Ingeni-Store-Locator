function nominatim_geolocate(location, restrictToCountry, outputId ) {

	// we are using MapQuest's Nominatim service
	var geocodeUrl = 'https://nominatim.openstreetmap.org/search?format=json&q=' + location + ',' + restrictToCountry;

//console.log(geocodeUrl);

	// use jQuery to call the API and get the JSON results
	jQuery.getJSON(geocodeUrl, function(data) {

		// get lat + lon from first match
		var latlng = [data[0].lat, data[0].lon]
		console.log(latlng);

		// let's stringify it
		var latlngAsString = latlng.join(',');
		//console.log(latlngAsString);

		// the full results JSON
		//console.log(data);

		document.getElementById(outputId).innerHTML = 'returns<br/>'+latlngAsString;
	});
}