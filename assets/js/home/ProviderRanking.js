let self;

export class ProviderRanking {
    constructor() {
        self = this;
        this.sliderMonth = document.getElementById('time-machine-slider-month');
        this.sliderEvo = document.getElementById('time-machine-slider-evo');
        this.minValue = 0;
        this.maxValue = 0;
    }

    init() {
        this.initDoubleRange();
        this.sliderMonth.addEventListener('input', this.handleSliderChangeMonth.bind(this));
        this.sliderEvo.addEventListener('input', this.handleSliderChangeEvo.bind(this));
        this.updateFill(this.sliderMonth);
        this.updateFill(this.sliderEvo);
    }

    handleSliderChangeEvo() {
        this.updateFill(this.sliderEvo);
        console.log('Slider Evo value changed:', this.sliderEvo.value);
        fetch('/api/provider/ranking/get', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                type: 'evo',
                percent: this.sliderEvo.value,
            }),
        })
            .then(response => response.json())
            .then(data => {
                console.log('API response:', data);
                const chartContent = this.sliderEvo.closest('.provider-ranking').querySelector('.ranking-content');
                chartContent.outerHTML = data.block;
            });
    }

    handleSliderChangeMonth() {
        this.updateFill(this.sliderMonth);
        console.log('Slider Month value changed:', this.sliderMonth.value);

        fetch('/api/provider/ranking/get', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                type: 'month',
                percent: this.sliderMonth.value,
            }),
        })
            .then(response => response.json())
            .then(data => {
                console.log('API response:', data);
                const chartContent = self.sliderMonth.closest('.provider-ranking').querySelector('.ranking-content');
                // data['block'] contient le nouveau HTML de l'élément charContent
                chartContent.outerHTML = data.block;
            });
    }

    updateFill(slider) {
        const min = Number(slider.min) || 0;
        const max = Number(slider.max || 100);
        slider.style.setProperty('--ratio', max === min ? 1 : (slider.value - min) / (max - min));
    }

    initDoubleRange() {
        this.createDoubleRange();
        const sliderDivs = document.querySelectorAll(".double-range-slider");
        sliderDivs.forEach(sliderDiv => {
            const sliders = sliderDiv.querySelectorAll("input");
            sliders.forEach(slider => {
                slider.addEventListener('input', () => {
                    const minRange = sliderDiv.querySelector('input[id^="minRange-"]');
                    const maxRange = sliderDiv.querySelector('input[id^="maxRange-"]');
                    let min = Number(minRange.value);
                    let max = Number(maxRange.value);
                    if (min > max) {
                        minRange.value = max;
                        maxRange.value = min;
                        min = Number(minRange.value);
                        max = Number(maxRange.value);
                    }
                    self.minValue = min;
                    self.maxValue = max;
                    console.log({min}, {max});
                });
            });
        });
    }

    createDoubleRange() {
        // <div class="double-range-slider">
        //      <label for="[data-id]"><input type="range" id="[data-id]" value="[data-value]" min="[data-min]" max="[data-max]" step="[data-step]"></label>
        //      <label for="[data-id]"><input type="range" id="[data-id]" value="[data-value]" min="[data-min]" max="[data-max]" step="[data-step]"></label>
        // </div>
        const jsDoubleRangeDivs = document.querySelectorAll(".js-double-range");
        jsDoubleRangeDivs.forEach((jsDiv, index) => {
            const dataset = jsDiv.dataset;
            const doubleRangeSlider = document.createElement("div");
            doubleRangeSlider.classList.add("double-range-slider");
            const labelMax = document.createElement("label");
            labelMax.setAttribute("for", `maxRange-${index}`);
            labelMax.innerHTML = `<input type="range" id="maxRange-${index}" value="${dataset.maxValue}" min="${dataset.min}" max="${dataset.max}" step="${dataset.step}">`;
            doubleRangeSlider.appendChild(labelMax);
            const labelMin = document.createElement("label");
            labelMin.setAttribute("for", `minRange-${index}`);
            labelMin.innerHTML = `<input type="range" id="minRange-${index}" value="${dataset.minValue}" min="${dataset.min}" max="${dataset.max}" step="${dataset.step}">`;
            doubleRangeSlider.appendChild(labelMin);
            jsDiv.replaceWith(doubleRangeSlider);
        })
    }
}