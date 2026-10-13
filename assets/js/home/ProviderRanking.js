let self;
const THROTTLE_DELAY = 500;

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
        this.initSlider(this.sliderMonth, 'month');
        this.initSlider(this.sliderEvo, 'evo');
    }

    initSlider(slider, type) {
        // Pendant le déplacement du curseur : au plus un appel à l'API toutes les THROTTLE_DELAY ms,
        // puis un dernier appel immédiat au relâchement ('change') avec la valeur finale.
        const state = {timer: null, lastValue: slider.value, controller: null};

        slider.addEventListener('input', () => {
            this.updateFill(slider);
            if (state.timer) return;
            state.timer = setTimeout(() => {
                state.timer = null;
                this.fetchRanking(slider, type, state);
            }, THROTTLE_DELAY);
        });
        slider.addEventListener('change', () => {
            clearTimeout(state.timer);
            state.timer = null;
            this.fetchRanking(slider, type, state);
        });
        this.updateFill(slider);
    }

    fetchRanking(slider, type, state) {
        if (slider.value === state.lastValue) return;
        state.lastValue = slider.value;

        // Une réponse plus ancienne ne doit pas écraser une plus récente
        state.controller?.abort();
        state.controller = new AbortController();

        fetch('/api/provider/ranking/get', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                type: type,
                percent: slider.value,
            }),
            signal: state.controller.signal,
        })
            .then(response => response.status === 204 ? null : response.json())
            .then(data => {
                if (!data?.block) return;
                const chartContent = slider.closest('.provider-ranking').querySelector('.ranking-content');
                // data['block'] contient le nouveau HTML de l'élément chartContent
                chartContent.outerHTML = data.block;
            })
            .catch(error => {
                if (error.name !== 'AbortError') console.error(error);
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