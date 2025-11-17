let gThis = null;

export class SeriesStatistics {
    constructor() {
        gThis = this;
        this.inlinePaddingWidth = 64;
        this.gapWidth = 16;
    }

    init() {

        console.log('Initializing series statistics...');
        this.adjustLayout();
        window.addEventListener('resize', this.adjustLayout);
    }

    adjustLayout() {
        const homeContentDiv = document.querySelector('.home-content');
        const seriesStatusDiv = homeContentDiv.querySelector('.series-status');
        const statusTitleDiv = seriesStatusDiv.querySelector('.status-title');
        const statusItemsDiv = seriesStatusDiv.querySelector('.status-items');
        const statusItemDivs = statusItemsDiv.querySelectorAll('.status-item');
        const itemCount = statusItemDivs.length;
        const gapCount = itemCount - 1;
        const homeContentDivWidth = homeContentDiv.getBoundingClientRect().width;
        const statusTitleDivWidth = statusTitleDiv.getBoundingClientRect().width;
        const statusItemsDivMaxWidth = homeContentDivWidth - statusTitleDivWidth;
        const statusItemDivWidths = [];
        const line1Array = [];
        const line2Array = [];
        let statusItemDivsWidth = (gapCount * gThis.gapWidth) + gThis.inlinePaddingWidth;
        statusItemDivs.forEach((statusItemDiv, index) => {
            const width = statusItemDiv.getBoundingClientRect().width;
            statusItemDivWidths[index] = width;
            statusItemDivsWidth += width;
        });

        // Si la somme des largeurs des items dépasse celle de leur container, on essaie de faire deux lignes de largeur semblable.
        if (statusItemDivsWidth > statusItemsDivMaxWidth) {
            if (statusItemsDiv.querySelector('.status-item-line')) return;

            const middleIndex = Math.floor(itemCount / 2)
            let firstLineWidth = 0;
            let lastLineWidth = 0;
            for (let i = 0; i < middleIndex; i++) {
                line1Array.push(i);
                firstLineWidth += statusItemDivWidths[i] + (i > 0 ? 16 : 0);
                line2Array.unshift(itemCount - 1 - i);
                lastLineWidth += statusItemDivWidths[itemCount - 1 - i] + (i > 0 ? 16 : 0);
            }
            if (itemCount % 2) {
                if (firstLineWidth > lastLineWidth) {
                    line2Array.unshift(middleIndex);
                } else {
                    line1Array.push(middleIndex);
                }
            }

            const line1Div = statusItemsDiv.querySelector('.status-item-line.line1') ?? document.createElement('div');
            line1Div.classList.add('status-item-line', 'line1');
            const line2Div = statusItemsDiv.querySelector('.status-item-line.line2') ?? document.createElement('div');
            line2Div.classList.add('status-item-line', 'line2');
            statusItemsDiv.appendChild(line1Div);
            statusItemsDiv.appendChild(line2Div);
            const statusItemDivArray = Array.from(statusItemDivs);
            line1Array.forEach(index => {
                line1Div.appendChild(statusItemDivArray[index]);
            });
            line2Array.forEach(index => {
                line2Div.appendChild(statusItemDivArray[index]);
            });
        } else {
            const line1Div = statusItemsDiv.querySelector('.status-item-line.line1');
            const line2Div = statusItemsDiv.querySelector('.status-item-line.line2');
            if (line1Div) {
                while (line1Div.firstChild) {
                    statusItemsDiv.appendChild(line1Div.firstChild);
                }
                line1Div.remove();
                while (line2Div.firstChild) {
                    statusItemsDiv.appendChild(line2Div.firstChild);
                }
                line2Div.remove();
            }
        }
    }
}