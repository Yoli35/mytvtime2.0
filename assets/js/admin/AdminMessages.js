export class AdminMessages {
    constructor() {
        this.selectAll = document.querySelector('.admin__messages .select-all');
        this.checkboxes = document.querySelectorAll('.admin__messages .select-one');
        this.bulkButtons = document.querySelectorAll('.admin__messages .bulk-action');
        this.selection = document.querySelector('.admin__messages__selection');

        this.init();
        console.log('AdminMessages initialized');
    }

    init() {
        this.selectAll?.addEventListener('change', () => {
            this.checkboxes.forEach(checkbox => checkbox.checked = this.selectAll.checked);
            this.update();
        });
        this.checkboxes.forEach(checkbox => checkbox.addEventListener('change', () => this.update()));

        // Confirmation avant suppression (ligne ou sélection)
        document.querySelectorAll('.admin__messages button[data-confirm]').forEach(button => {
            button.addEventListener('click', e => {
                if (!confirm(button.dataset.confirm)) {
                    e.preventDefault();
                }
            });
        });

        this.update();
    }

    update() {
        const count = [...this.checkboxes].filter(checkbox => checkbox.checked).length;

        this.bulkButtons.forEach(button => button.disabled = count === 0);
        if (this.selection) {
            this.selection.textContent = count + ' ' + this.selection.dataset.label;
        }
        if (this.selectAll) {
            this.selectAll.checked = count > 0 && count === this.checkboxes.length;
            this.selectAll.indeterminate = count > 0 && count < this.checkboxes.length;
        }
    }
}
