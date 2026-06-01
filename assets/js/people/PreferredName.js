export class PreferredName {
    syncFromSessionStorage() {
        const keys = [];
        for (let i = 0; i < sessionStorage.length; i++) {
            keys.push(sessionStorage.key(i));
        }

        for (const key of keys) {
            if (!key || !key.startsWith('preferred_name_')) {
                continue;
            }

            const peopleId = key.replace('preferred_name_', '');
            const preferredName = sessionStorage.getItem(key);
            if (!preferredName) {
                continue;
            }

            const peopleCard = document.querySelector('#cast-' + peopleId) || document.querySelector('#crew-' + peopleId);
            if (!peopleCard) {
                continue;
            }

            let preferredNameDiv = peopleCard.querySelector('.preferred-name');
            if (!preferredNameDiv) {
                preferredNameDiv = document.createElement('div');
                preferredNameDiv.classList.add('preferred-name');
                const nameDiv = peopleCard.querySelector('.name');
                if (nameDiv) {
                    nameDiv.before(preferredNameDiv);
                } else {
                    const infosDiv = peopleCard.querySelector('.infos');
                    infosDiv?.prepend(preferredNameDiv);
                }
            }

            preferredNameDiv.innerHTML = preferredName;
            preferredNameDiv.classList.add('update');
            setTimeout(() => {
                preferredNameDiv.classList.remove('update');
            }, 1000);

            sessionStorage.removeItem(key);
        }
    }
}
