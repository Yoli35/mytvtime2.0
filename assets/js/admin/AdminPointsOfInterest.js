import {Location} from 'Location';

export class AdminPointsOfInterest {
    constructor() {
        this.init();
    }

    init() {
        console.log('AdminPointsOfInterest initialized');
        const jsonGlobsObject = JSON.parse(document.querySelector('div#globs-map').textContent);
        const data = jsonGlobsObject.data || {};
        console.log('Data for points of interest:', data);
        const mapDiv = document.querySelector('.map-controller');
        if (mapDiv) {
            new Location(data, ['crud-type', 'crud-id', 'name', 'address', 'city', 'country', 'description', 'latitude', 'longitude', 'created_at'], mapDiv);
        }
    }
}