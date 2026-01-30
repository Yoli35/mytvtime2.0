import {Location} from 'Location';

export class AdminPointsOfInterest {
    constructor() {
        this.init();
    }

    init() {
        console.log('AdminPointsOfInterest initialized');
        const jsonGlobsObject = JSON.parse(document.querySelector('div#globs-map').textContent);
        console.log('Data for points of interest:', jsonGlobsObject);
        new Location('poi', jsonGlobsObject, ['crud-type', 'crud-id', 'name', 'address', 'city', 'country', 'description', 'latitude', 'longitude', 'created_at']);
    }
}