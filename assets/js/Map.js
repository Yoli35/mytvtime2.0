import MapboxLanguage from '@mapbox/mapbox-gl-language';
import mapboxgl from 'mapbox-gl';

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
        this.map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/outdoors-v12',
            projection: 'globe', // Display the map as a globe, since satellite-v9 defaults to Mercator
            bounds: this.bounds,
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

        mapboxgl.setRTLTextPlugin('https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-rtl-text/v0.2.3/mapbox-gl-rtl-text.js');
        const language = new MapboxLanguage();
        this.map.on('style.load', () => {
            gThis.map.setStyle(language.setLanguage(gThis.map.getStyle(), this.locale));
        });

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