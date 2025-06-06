export class AdminPointsOfInterest {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
    }

    bindEvents() {
        document.querySelectorAll('.delete-point-of-view').forEach(button => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                this.deletePointOfView(event.target);
            });
        });
    }

    deletePointOfView(button) {
        const pointOfViewId = button.getAttribute('data-id');
        if (confirm('Are you sure you want to delete this point of view?')) {
            fetch(`/admin/points-of-view/${pointOfViewId}`, {
                method: 'DELETE',
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        button.closest('.point-of-view-item').remove();
                        alert('Point of view deleted successfully.');
                    } else {
                        alert('Error deleting point of view: ' + data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    }
}