import mapboxgl from 'mapbox-gl';
// import MapboxLanguage from '@mapbox/mapbox-gl-language';
// import MapboxGeocoder from '@mapbox/mapbox-gl-geocoder';

// import 'mapbox-gl/dist/mapbox-gl.css';
// import '@mapbox/mapbox-gl-geocoder/dist/mapbox-gl-geocoder.css';

let gThis = null;

export class Map {
    constructor() {
        console.log('Map starting');
        gThis = this;
        const globsData = document.querySelector('#globs-map');
        /*console.log(globsData);*/
        const data = JSON.parse(globsData.textContent);
        this.locations = data.locations || [];
        /*console.log(this.locations);*/
        this.bounds = data.bounds;
        this.pointsOfInterest = data.pointsOfInterest || [];
        this.albumName = data.albumName || null;
        this.photos = data.photos || [];
        // this.latLngs = locations.map(location => [location.latitude, location.longitude]);
        this.locale = document.querySelector('html').getAttribute('lang');
        this.map = null;
        this.circles = [];
        this.init();
    }

    init() {
        console.log('Map init');
        console.log(`Mapbox GL JS v${mapboxgl.version}`);
        mapboxgl.accessToken = 'pk.eyJ1IjoiaWJveTQ0IiwiYSI6ImNtNTZqcXo4ZjAxYzIyaXM3cWZ5dnNheWkifQ.yY-zdieRm3Dhlrj3vYh9hg';
        mapboxgl.setRTLTextPlugin('https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-rtl-text/v0.2.3/mapbox-gl-rtl-text.js');

        const initializeMap = () => {
            this.map = new mapboxgl.Map({
                container: 'map',
                /*style: 'mapbox://styles/mapbox/outdoors-v12',*/
                /*cooperativeGestures: true,*/
                projection: 'globe',
                pitch: 45,
                bearing: 0,
                bounds: this.bounds,
                fitBoundsOptions: {
                    padding: 15
                }
            });
            console.log('Map initialized', this.map);
            this.map.on('style.load', () => {
                gThis.map.setFog({
                    "range": [0.8, 1.2],
                    "color": "#E0861F",
                    "horizon-blend": 0.125,
                    "high-color": "#112a67",
                    "space-color": "#000000",
                    "star-intensity": 0.15
                });

                gThis.map.setConfigProperty('basemap', 'show3dObjects', true);

                /*gThis.map.addSource('mapbox-dem', {
                    'type': 'raster-dem',
                    'url': 'mapbox://mapbox.terrain-rgb'
                });

                gThis.map.setTerrain({
                    'source': 'mapbox-dem',
                    'exaggeration': 1.5
                });*/
            });
            this.map.on('click', (e) => {
                navigator.clipboard.writeText(`${e.lngLat.lat}, ${e.lngLat.lng}`).then(r => console.log(`A click event has occurred /*at ${e.lngLat}*/ ${r}`));
            });

            /*const language = new MapboxLanguage();
            this.map.on('style.load', () => {
                gThis.map.setStyle(language.setLanguage(gThis.map.getStyle(), this.locale));
            });*/
            /*this.map.addControl(new MapboxGeocoder({
                    accessToken: mapboxgl.accessToken,
                    mapboxgl: mapboxgl
                })
            );*/
            this.map.addControl(new mapboxgl.NavigationControl());
            this.map.addControl(new mapboxgl.GeolocateControl({
                positionOptions: {
                    enableHighAccuracy: true
                },
                trackUserLocation: true,
                showUserHeading: true
            }));
            this.map.addControl(new mapboxgl.ScaleControl());

            this.locations.forEach(location => {
                let marker = new mapboxgl.Marker({color: "#B46B18FF"})
                    .setLngLat([location.longitude, location.latitude])
                    .setPopup(new mapboxgl.Popup().setHTML('<div class="leaflet-popup-content-title">' + location.title + '</div><div class="leaflet-popup-content-description">' + location.description + '</div><div class="leaflet-popup-content-image"><img src="/images/map' + location['still_path'] + '" alt="' + location['title'] + '" style="height: auto; width: 100%"></div>'))
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
            console.log({circles: this.circles});
            if (this.circles.length) {
                this.map.on('style.load', () => {
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
                });
            }

            this.pointsOfInterest.forEach((point, index) => {
                let marker = new mapboxgl.Marker({color: "#196c00"})
                    .setLngLat([point.longitude, point.latitude])
                    .setPopup(new mapboxgl.Popup().setHTML('<div class="leaflet-popup-content-title poi">' + point.name + '</div><div class="leaflet-popup-content-description poi">' + point.address + '</div><div class="leaflet-popup-content-image"><img src="/images/poi' + point['still_path'] + '" alt="' + point['name'] + '" style="height: auto; width: 100%"></div>'))
                    .addTo(this.map);
                let markerIcon = marker.getElement();
                markerIcon.setAttribute('data-id', point.id);
                markerIcon.setAttribute('data-latitude', point.latitude);
                markerIcon.setAttribute('data-longitude', point.longitude);
                markerIcon.setAttribute('data-index', index);
            });

            this.photos.forEach((photo, index) => {
                this.addPhotoMarker(photo, index);
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

    addPhotoMarker(photo, index = 0) {
        if (photo.latitude && photo.longitude) {
            let marker = new mapboxgl.Marker({color: "#196c00"})
                .setLngLat([photo.longitude, photo.latitude])
                .setPopup(new mapboxgl.Popup().setHTML('<div class="leaflet-popup-content-title photo">' + this.albumName + '</div>' + (photo.caption ? '<div class="leaflet-popup-content-description poi">' + photo.caption + '</div>' : '') + '<div class="leaflet-popup-content-image"><img src="/albums/576p' + photo.image_path + '" alt="' + photo.caption + '"></div>'))
                .addTo(this.map);
            let markerIcon = marker.getElement();
            markerIcon.setAttribute('data-id', photo.id);
            markerIcon.setAttribute('data-latitude', photo.latitude);
            markerIcon.setAttribute('data-longitude', photo.longitude);
            if (index)
                markerIcon.setAttribute('data-index', index);
        }
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