export class CopyName {
    constructor(parentElement) {
        const spans = parentElement.querySelectorAll('span');
        spans.forEach(span => {
            const copyBadge = document.createElement('div');
            copyBadge.classList.add("copy-badge");
            const svg = document.querySelector('#svgs').querySelector('svg[id="copy"]').cloneNode(true);
            svg.removeAttribute('id');
            copyBadge.appendChild(svg);
            span.appendChild(copyBadge);
            copyBadge.addEventListener('click', () => {
                copyBadge.classList.add("spin");
                const text = span.innerText;
                navigator.clipboard.writeText(text).then(() => {
                    console.log('Copied to clipboard: ' + text);
                    setTimeout(() => {
                        copyBadge.classList.remove("spin");
                    }, 500);
                }).catch(err => {
                    console.error('Error copying text: ', err);
                });
            });
        });
    }
}