export class CopyName {
    constructor(parentElement) {
        const spans = parentElement.querySelectorAll('span');
        console.log({spans});
        spans.forEach(span => {
            const copySvg = document.querySelector('#svgs').querySelector('svg[id="copy"]').cloneNode(true);
            copySvg.removeAttribute('id');
            span.appendChild(copySvg);
            copySvg.addEventListener('click', () => {
                const text = span.innerText;
                navigator.clipboard.writeText(text).then(() => {
                    console.log('Copied to clipboard: ' + text);
                }).catch(err => {
                    console.error('Error copying text: ', err);
                });
            });
        });
    }
}