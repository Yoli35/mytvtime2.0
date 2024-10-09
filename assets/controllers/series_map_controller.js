import {Controller} from '@hotwired/stimulus';
let gThis;
export default class extends Controller {

    connect() {
        gThis = this;
        console.log(gThis)
        this.markers = [];
        this.element.addEventListener('ux:map:pre-connect', this._onPreConnect);
        this.element.addEventListener('ux:map:connect', this._onConnect);
        this.element.addEventListener('ux:map:marker:before-create', this._onMarkerBeforeCreate);
        this.element.addEventListener('ux:map:marker:after-create', this._onMarkerAfterCreate);
        this.element.addEventListener('ux:map:info-window:before-create', this._onInfoWindowBeforeCreate);
        this.element.addEventListener('ux:map:info-window:after-create', this._onInfoWindowAfterCreate);
    }

    disconnect() {
        // You should always remove listeners when the controller is disconnected to avoid side effects
        this.element.removeEventListener('ux:map:pre-connect', this._onPreConnect);
        this.element.removeEventListener('ux:map:connect', this._onConnect);
        this.element.removeEventListener('ux:map:marker:before-create', this._onMarkerBeforeCreate);
        this.element.removeEventListener('ux:map:marker:after-create', this._onMarkerAfterCreate);
        this.element.removeEventListener('ux:map:info-window:before-create', this._onInfoWindowBeforeCreate);
        this.element.removeEventListener('ux:map:info-window:after-create', this._onInfoWindowAfterCreate);
    }

    _onPreConnect(event) {
        // The map is not created, yet
        // You can use this event to configure the map before it is created
        // console.log(event.detail.options);
    }

    _onConnect(event) {
        // The map, markers and infoWindows are created
        // The instances depend on the renderer you are using
        // console.log(event.detail.map);
        // console.log(event.detail.markers);
        // console.log(event.detail.infoWindows);

        const markerCount = event.detail.markers.length;
        const seriesMapTitle = document.querySelector('#series-map-title').querySelector('.title');
        seriesMapTitle.textContent += ` (${markerCount})`;

        // const firstInfoWindow = event.detail.infoWindows[0];
        // firstInfoWindow.options.opened = true;
    }

    _onMarkerBeforeCreate(event) {
        // The marker is not created, yet
        // You can use this event to configure the marker before it is created
        console.log(event.detail);
        const id = event.detail.definition.extra.id;
        gThis.markers[id] = event.detail.definition;
    }

    _onMarkerAfterCreate(event) {
        // The marker is created
        // The instance depends on the renderer you are using
        // console.log(event.detail.marker);
    }

    _onInfoWindowBeforeCreate(event) {
        // The infoWindow is not created, yet
        // You can use this event to configure the infoWindow before it is created
        // console.log(event.detail.definition);
        // The associated marker instance is also available
        // console.log(event.detail.marker);
    }

    _onInfoWindowAfterCreate(event) {
        // The infoWindow is created
        // The instance depends on the renderer you are using
        // console.log(event.detail.infoWindow);
        // The associated marker instance is also available
        // console.log(event.detail.marker);
        // console.log(event);
    }
}