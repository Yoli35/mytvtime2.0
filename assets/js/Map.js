import mapboxgl from 'mapbox-gl';
import MapboxLanguage from '@mapbox/mapbox-gl-language';
// import MapboxGeocoder from '@mapbox/mapbox-gl-geocoder';

// import 'mapbox-gl/dist/mapbox-gl.css';
// import '@mapbox/mapbox-gl-geocoder/dist/mapbox-gl-geocoder.css';

let gThis = null;

export class Map {
    constructor() {
        console.log('Map starting');
        gThis = this;
        const globsData = document.querySelector('#globs-map');
        console.log(globsData);
        const data = JSON.parse(globsData.textContent);
        this.locations = data.locations;
        this.bounds = data.bounds;
        // this.latLngs = locations.map(location => [location.latitude, location.longitude]);
        this.locale = document.querySelector('html').getAttribute('lang');
        this.map = null;
        this.init();
    }

    init() {
        console.log('Map init');
        mapboxgl.accessToken = 'pk.eyJ1IjoiaWJveTQ0IiwiYSI6ImNtNTZqcXo4ZjAxYzIyaXM3cWZ5dnNheWkifQ.yY-zdieRm3Dhlrj3vYh9hg';
        mapboxgl.setRTLTextPlugin('https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-rtl-text/v0.2.3/mapbox-gl-rtl-text.js');

        const initializeMap = () => {
            this.map = new mapboxgl.Map({
                container: 'map',
                style: 'mapbox://styles/mapbox/outdoors-v12',
                projection: 'globe',
                bounds: this.bounds,
                fitBoundsOptions: {
                    padding: 15
                }
            });
            this.map.on('style.load', () => {
                gThis.map.setFog({
                    "range": [0.8, 1.2],
                    "color": "#E0861F",
                    "horizon-blend": 0.125,
                    "high-color": "#112a67",
                    "space-color": "#000000",
                    "star-intensity": 0.15
                }); // Set the default atmosphere style
            });
            this.map.on('click', (e) => {
                navigator.clipboard.writeText(`${e.lngLat.lat}, ${e.lngLat.lng}`).then(r => console.log(`A click event has occurred at ${e.lngLat}`));
            });

            const language = new MapboxLanguage();
            this.map.on('style.load', () => {
                gThis.map.setStyle(language.setLanguage(gThis.map.getStyle(), this.locale));
            });
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
                markerIcon.setAttribute('data-tmdb-id', location.tmdb_id);
                // markerIcon.setAttribute('data-country', location.country);
                markerIcon.setAttribute('data-latitude', location.latitude);
                markerIcon.setAttribute('data-longitude', location.longitude);
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
}