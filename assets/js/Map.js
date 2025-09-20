import mapboxgl from 'mapbox-gl';
import { MapboxAddressAutofill, MapboxSearchBox, MapboxGeocoder, config } from '@mapbox/search-js-web'

let gThis = null;

export class Map {
    constructor(options) {
        console.log('Map starting');
        gThis = this;
        const globsData = document.querySelector('#globs-map');
        this.data = JSON.parse(globsData.textContent);
        this.locations = this.data.locations || [];
        this.bounds = this.data.bounds;
        this.id = this.data.id || null;
        this.albumName = this.data.albumName || null;
        this.photos = this.data.photos || [];
        this.locale = document.querySelector('html').getAttribute('lang');
        this.map = null;
        this.circles = [];
        this.init(options);
    }

    init(options) {
        console.log('Map init');
        console.log(`Mapbox GL JS v${mapboxgl.version}`);
        mapboxgl.accessToken = 'pk.eyJ1IjoiaWJveTQ0IiwiYSI6ImNtNTZqcXo4ZjAxYzIyaXM3cWZ5dnNheWkifQ.yY-zdieRm3Dhlrj3vYh9hg';
        mapboxgl.setRTLTextPlugin('https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-rtl-text/v0.2.3/mapbox-gl-rtl-text.js');

        const initializeMap = () => {
            this.map = new mapboxgl.Map({
                container: 'map',
                cooperativeGestures: options.cooperativeGesturesOption,
                projection: 'globe',
                pitch: 45,
                bearing: 0,
                bounds: this.bounds,
                fitBoundsOptions: {
                    padding: 45
                }
            });
            console.log('Map initialized', this.map);
            this.map.on('style.load', () => {
                this.map.setFog({
                    "range": [0.8, 1.2],
                    "color": "#E0861F",
                    "horizon-blend": 0.125,
                    "high-color": "#112a67",
                    "space-color": "#000000",
                    "star-intensity": 0.15
                });

                this.map.setConfigProperty('basemap', 'show3dObjects', true);

                const searchBox = new MapboxSearchBox();
                // set the mapbox access token, search box API options
                searchBox.accessToken = mapboxgl.accessToken;
                searchBox.options = {
                    types: 'address,poi',
                    language: 'fr',
                    proximity: 'auto',
                };
                // set the mapboxgl library to use for markers and enable the marker functionality
                searchBox.mapboxgl = mapboxgl;
                searchBox.marker = true;
                searchBox.componentOptions = { allowReverse: true, flipCoordinates: true };
                this.map.addControl(searchBox);

                this.map.addControl(new mapboxgl.NavigationControl());
                this.map.addControl(new mapboxgl.GeolocateControl({
                    positionOptions: {
                        enableHighAccuracy: true
                    },
                    trackUserLocation: true,
                    showUserHeading: true
                }));
                this.map.addControl(new mapboxgl.ScaleControl());

                /************************************************************************
                 * Fetch pois[, locations, photos ]                                     *
                 ************************************************************************/
                fetch('/api/pois/get')
                    .then(res => res.json())
                    .then(data => {
                        // console.log(data);
                        const list = data['pois']['list'];
                        list.forEach((point, index) => {
                            this.addPoiMarker(point, index);
                        })
                    });

                this.locations.forEach(location => {
                    let marker = new mapboxgl.Marker({color: "#B46B18FF"})
                        .setLngLat([location.longitude, location.latitude])
                        .setPopup(new mapboxgl.Popup({closeOnMove: true}).setMaxWidth("24rem").setHTML('<div class="leaflet-popup-content-title">' + location.title + '</div><div class="leaflet-popup-content-description">' + location.description + '</div><div class="leaflet-popup-content-image"><img src="/images/map' + location['still_path'] + '" alt="' + location['title'] + '" style="height: auto; width: 100%"></div>'))
                        .addTo(this.map);
                    let markerIcon = marker.getElement();
                    markerIcon.setAttribute('data-target-id', location.id);
                    markerIcon.setAttribute('data-tmdb-id', location.tmdb_id);
                    // markerIcon.setAttribute('data-country', location.country);
                    markerIcon.setAttribute('data-latitude', location.latitude);
                    markerIcon.setAttribute('data-longitude', location.longitude);

                    if (location.radius) {
                        this.circles.push(this.createCircle([location.longitude, location.latitude], location.radius / 1000));
                    }
                });

                if (this.circles.length) {
                    this.map.addSource('circles', {
                        type: 'geojson',
                        data: {
                            type: 'FeatureCollection',
                            features: this.circles
                        }
                    });
                    this.map.addLayer({
                        id: 'circle-layer',
                        type: 'fill',
                        source: 'circles',
                        paint: {
                            'fill-color': '#875012',
                            'fill-opacity': 0.125,
                            'fill-outline-color': '#E0861F'
                        }
                    });
                    this.map.addLayer({
                        id: 'circle-outline-layer',
                        type: 'line',
                        source: 'circles',
                        paint: {
                            'line-color': '#E0861F',
                            'line-width': 4
                        }
                    });
                }

                this.photos.forEach((photo, index) => {
                    this.addPhotoMarker(photo, index);
                });
            });
            this.map.on('click', (e) => {
                navigator.clipboard.writeText(`${e.lngLat.lat}, ${e.lngLat.lng}`).then(r => console.log(`A click event has occurred /*at ${e.lngLat}*/ ${r}`));
            });
        }

        const initializeMapHandle = () => {
            const mapDiv = document.getElementById('map');
            const mapboxglControlContainerDiv = mapDiv.querySelector('.mapboxgl-control-container');
            const handleDiv = document.createElement('div');
            const heightHandle = (e) => {
                e.preventDefault();
                let startY = e.clientY;
                let startHeight = mapDiv.clientHeight;
                const moveHandler = (e) => {
                    const diff = e.clientY - startY;
                    mapDiv.style.height = startHeight + diff + 'px';
                }
                document.addEventListener('mousemove', moveHandler);
                document.addEventListener('mouseup', () => {
                    document.removeEventListener('mousemove', moveHandler);
                    handleDiv.removeEventListener('mousedown', heightHandle);

                    if (gThis.map) gThis.map.remove();
                    initializeMap();
                    initializeMapHandle();
                });
            }
            handleDiv.classList.add('height-handle');
            mapboxglControlContainerDiv.appendChild(handleDiv);
            // Use this handler to modify the map height
            handleDiv.addEventListener('mousedown', heightHandle);
        };

        initializeMap();
        initializeMapHandle();

        if (document.querySelector("#map-index")) {
            const locations = this.data['locations'];
            const countryBounds = this.data['countryLatLngs'];
            const countryLocationIds = this.data['countryLocationIds'];
            const latLngs = locations.map(location => [location.latitude, location.longitude]);

            /** @param {Event|number} selectedValue */
            const gotoLocation = (selectedValue) => {
                /*const select = document.getElementById('fl-series-map-select');
                const id = select.options[select.selectedIndex].value;
                const locationH4 = document.querySelector(`h4[data-tmdb-id="${id}"]`);
                locationH4.scrollIntoView({behavior: 'instant', block: 'center'});*/
                const mapDiv = document.getElementById('map');
                const allSeriesLocations = document.querySelector('.all-series-locations');
                /** @type {HTMLSelectElement} */
                const select = document.getElementById('fl-series-map-select');
                const value = typeof selectedValue == "number" ? selectedValue : select.options[select.selectedIndex].value;
                let country;
                if (value !== "all") {
                    const markerToHide = mapDiv.querySelectorAll(`.mapboxgl-marker:not([data-tmdb-id="${value}"])`);
                    markerToHide.forEach(marker => {
                        if (!marker.classList.contains('poi-marker'))
                            marker.style.display = 'none';
                    });
                    const markerToShow = mapDiv.querySelectorAll(`.mapboxgl-marker[data-tmdb-id="${value}"]`);
                    markerToShow.forEach(marker => {
                        if (!marker.classList.contains('poi-marker'))
                            marker.style.display = 'block';
                    });
                    const seriesLocationsToHide = allSeriesLocations.querySelectorAll(`.series-locations:not([data-tmdb-id="${value}"])`);
                    const serieLocations = document.querySelector(`.series-locations[data-tmdb-id="${value}"]`);
                    seriesLocationsToHide.forEach(locationItem => {
                        locationItem.style.display = 'none';
                    });
                    serieLocations.style.display = "flex";
                    country = serieLocations?.getAttribute('data-country') || "";
                } else {
                    const allSeriesLocationDivs = allSeriesLocations.querySelectorAll('.series-locations');
                    const allMarkers = mapDiv.querySelectorAll('.mapboxgl-marker');
                    allMarkers.forEach(marker => {
                        marker.style.display = 'block';
                    });
                    allSeriesLocationDivs.forEach(loc => {
                        loc.style.display = "flex";
                    });
                    country = "";
                }
                // console.log(country);
                const flCountryBbSelect = document.getElementById('fl-country-bb-select');
                flCountryBbSelect.selectedIndex = getSelectIndex(flCountryBbSelect, country);
                zoomToCountry();

                // Lorsqu'on change de series, on veut que le bouton "Zoom to" soit actif pour les pays
                const zoomToCountryButtons = document.querySelectorAll('.to-country');
                zoomToCountryButtons.forEach(button => {
                    button.classList.add('active');
                });
                const zoomToMarkersButtons = document.querySelectorAll('.to-markers');
                zoomToMarkersButtons.forEach(button => {
                    button.classList.remove('active');
                });
            }

            const getSelectIndex = (select, value) => {
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].value === value) {
                        return i;
                    }
                }
                return 0;
            }

            const zoomToSeriesCountry = (e) => {
                const target = e.currentTarget;
                const locationsButton = target.parentElement.querySelector('.to-markers');
                locationsButton.classList.remove('active');
                target.classList.add('active');
                const countryCode = target.getAttribute('data-country');
                const select = document.getElementById('fl-country-bb-select');
                select.selectedIndex = getSelectIndex(select, countryCode);
                zoomToCountry();
            }

            const zoomToSeriesLocations = (e) => {
                const target = e.currentTarget;
                const countryButton = target.parentElement.querySelector('.to-country');
                countryButton.classList.remove('active');
                target.classList.add('active');
                let minLat = parseFloat(target.getAttribute('data-min-lat'));
                let maxLat = parseFloat(target.getAttribute('data-max-lat'));
                let minLng = parseFloat(target.getAttribute('data-min-lng'));
                let maxLng = parseFloat(target.getAttribute('data-max-lng'));
                minLat -= 0.1;
                maxLat += 0.1;
                minLng -= 0.1;
                maxLng += 0.1;
                this.map.fitBounds([[minLng, minLat], [maxLng, maxLat]], {padding: 45});
            }

            const zoomToLocation = () => {
                /** @type {HTMLSelectElement} */
                const select = document.getElementById('fl-country-map-select');
                const country = select.options[select.selectedIndex].value;
                const leafletMarkerIcons = document.querySelectorAll('.leaflet-marker-icon');
                const leafletMarkerShadows = document.querySelectorAll('.leaflet-marker-shadow');

                if (country) {
                    const latLngs = countryBounds[country];
                    this.map.fitBounds(latLngs, {padding: 45});

                    const locationIds = countryLocationIds[country];
                    leafletMarkerIcons.forEach(markerIcon => {
                        const tmdbId = markerIcon.getAttribute('data-tmdb-id');
                        if (locationIds.includes(parseInt(tmdbId))) {
                            markerIcon.style.display = 'block';
                        } else {
                            markerIcon.style.display = 'none';
                        }
                    });
                    leafletMarkerShadows.forEach(markerShadow => {
                        const tmdbId = markerShadow.getAttribute('data-tmdb-id');
                        if (locationIds.includes(parseInt(tmdbId))) {
                            markerShadow.style.display = 'block';
                        } else {
                            markerShadow.style.display = 'none';
                        }
                    });
                } else {
                    this.map.fitBounds(latLngs, {padding: 45});
                    leafletMarkerIcons.forEach(markerIcon => {
                        markerIcon.style.display = 'block';
                    });
                }
            }

            const zoomToCountry = () => {
                /** @type {HTMLSelectElement} */
                const select = document.getElementById('fl-country-bb-select');
                const country = select.options[select.selectedIndex];
                if (country.value === "") {
                    const center = this.map.getCenter();
                    this.map.setZoom(3);
                    this.map.easeTo({center, duration: 1000, easing: (n) => n});
                } else {
                    // dans la base les latitudes et longitudes sont inversées
                    const lat1 = country.getAttribute('data-lat1');
                    const lng1 = country.getAttribute('data-lng1');
                    const lat2 = country.getAttribute('data-lat2');
                    const lng2 = country.getAttribute('data-lng2');
                    this.map.fitBounds([[lat1, lng1], [lat2, lng2]], {padding: 45});
                }
            }

            const selectedFilmingLocation = parseInt(document.querySelector('.series-location-selected').textContent) || 0;
            const flSeriesMapSelect = document.getElementById('fl-series-map-select');
            const flCountryMapSelect = document.getElementById('fl-country-map-select');
            const flCountryBbSelect = document.getElementById('fl-country-bb-select');

            flSeriesMapSelect.addEventListener('change', gotoLocation);
            flCountryMapSelect.addEventListener('change', zoomToLocation);
            flCountryBbSelect.addEventListener('change', zoomToCountry);

            const allSeriesLocations = document.querySelector('.all-series-locations');
            const seriesLocations = allSeriesLocations.querySelectorAll('.series-locations');
            seriesLocations.forEach(loc => {
                const toCountry = loc.querySelector('.to-country');
                const toMarkers = loc.querySelector('.to-markers');
                toCountry.addEventListener('click', zoomToSeriesCountry);
                toMarkers.addEventListener('click', zoomToSeriesLocations);
            });

            if (selectedFilmingLocation > 0) {
                flSeriesMapSelect.value = selectedFilmingLocation;
                gotoLocation(selectedFilmingLocation);
            }
        }

        const thumbnails = document.querySelectorAll('.thumbnail');
        thumbnails.forEach(thumbnail => {
            thumbnail.addEventListener('click', this.setMapStyle);
        });

        const targetMapDivs = document.querySelectorAll('.target-map');
        targetMapDivs.forEach(targetMapDiv => {
            targetMapDiv.addEventListener('click', (event) => {
                event.preventDefault();
                const locId = targetMapDiv.getAttribute('data-loc-id');
                const lat = targetMapDiv.getAttribute('data-lat');
                const lng = targetMapDiv.getAttribute('data-lng');
                // Center map to location (lat, lng)
                this.map.flyTo({center: [lng, lat], duration: 3000, zoom: 15, curve: 2,/* speed: 0.2,*/ easing: (n) => n});
                const markerElement = document.querySelector('div[data-target-id="' + locId + '"]');
                if (markerElement) {
                    markerElement.click();
                }
            });
        })

        const seriesLocationImages = document.querySelectorAll('.series-location-image');
        seriesLocationImages.forEach(image => {
            image.addEventListener('mouseenter', () => {
                image.addEventListener('mousemove', gThis.getPosition);
            });
            image.addEventListener('mouseleave', () => {
                image.removeEventListener('mousemove', gThis.getPosition);
                const imageList = image.querySelector('.image-list');
                image.setAttribute('data-position', "0");
                const imageSrc = imageList.children[0].getAttribute('src');
                image.querySelector('img').setAttribute('src', imageSrc);
            });
        });

        const toggleCooperativeGesturesButton = document.querySelector('.toggle-cooperative-gestures');
        if (toggleCooperativeGesturesButton) {
            toggleCooperativeGesturesButton.addEventListener('click', () => {
                const state = gThis.toggleCooperativeGestures();
                if (state) {
                    toggleCooperativeGesturesButton.classList.add('active');
                } else {
                    toggleCooperativeGesturesButton.classList.remove('active');
                }
            });
        }
    }

    addPoiMarker(point, index) {
        const el = document.createElement('div');
        el.classList.add('poi-marker');
        el.style.backgroundImage = 'url(' + '"/images/poi' + point['still_path'] + '")';
        let marker = new mapboxgl.Marker({element: el, offset: [0, -40]}) /* {color: "#196c00"} */
            .setLngLat([point.longitude, point.latitude])
            .setPopup(new mapboxgl.Popup({closeOnMove: true}).setMaxWidth("24rem").setHTML('<div class="leaflet-popup-content-title poi">' + point.name + '</div><div class="leaflet-popup-content-description poi">' + point.address + '</div><div class="leaflet-popup-content-image"><img src="/images/poi' + point['still_path'] + '" alt="' + point['name'] + '" style="height: auto; width: 100%"></div>'))
            .addTo(this.map);
        let markerIcon = marker.getElement();
        markerIcon.setAttribute('data-id', point.id);
        markerIcon.setAttribute('data-latitude', point.latitude);
        markerIcon.setAttribute('data-longitude', point.longitude);
        markerIcon.setAttribute('data-index', index);
    }

    addPhotoMarker(photo, index = 0) {
        if (photo.latitude && photo.longitude) {
            const path = '/albums/' + this.id + '/576p';
            let marker = new mapboxgl.Marker({color: "#196c00", scale: .5})
                .setLngLat([photo.longitude, photo.latitude])
                .setPopup(new mapboxgl.Popup({closeOnMove: true}).setMaxWidth("24rem").setHTML('<div class="leaflet-popup-content-title photo">' + this.albumName + '</div>' + (photo.caption ? '<div class="leaflet-popup-content-description poi">' + photo.caption + '</div>' : '') + '<div class="leaflet-popup-content-image"><img src="' + path + photo.image_path + '" alt="' + photo.caption + '"></div>'))
                .addTo(this.map);
            /** @type HTMLDivElement markerIcon */
            let markerIcon = marker.getElement();
            markerIcon.classList.add('photo-marker');
            markerIcon.setAttribute('data-id', photo.id);
            markerIcon.setAttribute('data-latitude', photo.latitude);
            markerIcon.setAttribute('data-longitude', photo.longitude);
            if (index)
                markerIcon.setAttribute('data-index', index);
        }
    }

    adjustBounds(type) {
        // type:
        //   → photo: 'photo-marker'
        //   → point of interest: 'poi-marker'
        const markers = document.querySelectorAll('.mapboxgl-marker.' + type);
        if (!markers.length) {
            return;
        }
        if (markers.length === 1) {
            const lat = parseFloat(markers[0].getAttribute('data-latitude'));
            const lng = parseFloat(markers[0].getAttribute('data-longitude'));
            this.map.setCenter([lng, lat], {padding: 30});
            // this.map.setZoom(12);
            return;
        }
        let minLat = 90, maxLat = -90, minLng = 180, maxLng = -180;
        markers.forEach(marker => {
            const lat = parseFloat(marker.getAttribute('data-latitude'));
            const lng = parseFloat(marker.getAttribute('data-longitude'));
            minLat = Math.min(lat, minLat);
            maxLat = Math.max(lat, maxLat);
            minLng = Math.min(lng, minLng);
            maxLng = Math.max(lng, maxLng);
        });
        this.map.fitBounds([[minLng, minLat], [maxLng, maxLat]], {padding: 45});
    }

    getPosition(e) {
        const image = e.currentTarget;
        const imageList = image.querySelector('.image-list');
        const count = imageList.children.length;
        const imageWidth = image.clientWidth;
        const position = Math.floor(e.offsetX / (imageWidth / count));
        const oldPosition = parseInt(image.getAttribute('data-position'));
        if (oldPosition !== position) {
            image.setAttribute('data-position', position);
            const imageSrc = imageList.children[position].getAttribute('src');
            image.querySelector('img').setAttribute('src', imageSrc);
            const toolTips = document.querySelector('.tool-tips.show');
            const toolTipsImg = toolTips?.querySelector('img');
            if (toolTipsImg) {
                toolTipsImg.style.filter = 'brightness(0.5)';
                toolTipsImg.setAttribute('src', imageSrc);
                toolTipsImg.onload = () => {
                    toolTipsImg.style.filter = 'none';
                };
            }
        }
    }

    setMapStyle(event) {
        const thumbnails = document.querySelectorAll('.thumbnail');
        thumbnails.forEach(thumbnail => {
            thumbnail.classList.remove('selected');
        });
        const thumbnail = event.currentTarget;
        thumbnail.classList.add('selected');
        const style = thumbnail.getAttribute('data-style');
        gThis.map.setStyle(style);
    }

    toggleCooperativeGestures() {
        if (this.map) {
            this.map._cooperativeGestures = !this.map._cooperativeGestures;
            return this.map._cooperativeGestures;
        }
        return false;
    }

    createCircle(center, radiusInKm) {
        const points = 64; // Nombre de points pour lisser le cercle
        const coords = [];
        const distanceX = radiusInKm / (111.32 * Math.cos(center[1] * Math.PI / 180));
        const distanceY = radiusInKm / 110.574;

        for (let i = 0; i < points; i++) {
            const angle = (i * 360) / points;
            const x = center[0] + distanceX * Math.cos(angle * Math.PI / 180);
            const y = center[1] + distanceY * Math.sin(angle * Math.PI / 180);
            coords.push([x, y]);
        }
        coords.push(coords[0]); // Fermer le polygone

        return {
            type: 'Feature',
            geometry: {
                type: 'Polygon',
                coordinates: [coords]
            }
        };
    }
}