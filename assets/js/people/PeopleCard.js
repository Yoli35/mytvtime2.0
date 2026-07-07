import {PeopleShow} from "PeopleShow";
import {PreferredName} from "PreferredName";

export class PeopleCard {
    constructor() {
        this.currentPeopleCard = null;
        this.peopleShow = null;
        this.preferredName = new PreferredName();
        this.handleCardClick = this.handleCardClick.bind(this);
        this.fetchData = this.fetchData.bind(this);
        this.openCard = this.openCard.bind(this);
        this.closeCard = this.closeCard.bind(this);
        this.closeOnEscapeKey = this.closeOnEscapeKey.bind(this);
        this.init();
    }

    init() {
        console.log('PeopleCard initialized');
        const peopleCards = document.querySelectorAll('.people-card');
        peopleCards.forEach(peopleCard => {
            peopleCard.addEventListener('click', this.handleCardClick);
        });
    }

    handleCardClick(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        this.currentPeopleCard = e.currentTarget;
        const peopleCard = e.currentTarget;
        const id = peopleCard.getAttribute('data-id');
        this.openCard(peopleCard);
        this.fetchData(id);
    }

    fetchData(id) {
        fetch('/api/people/card/show', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({id: id})
        })
            .then(response => response.json())
            .then(data => {
                console.log('PeopleCard data', data);
                const peopleBigCard = document.querySelector('.people-big-card');
                const peopleBigCardContent = peopleBigCard.querySelector('.people-big-card-content');
                const newImg = peopleBigCardContent.querySelector('img');
                setTimeout(() => {
                    peopleBigCardContent.classList.add('fade-out');
                    setTimeout(() => {
                        if (newImg) {
                            peopleBigCardContent.removeChild(newImg);
                        }
                        peopleBigCardContent.innerHTML = data['view'];
                        this.addCloseButton(peopleBigCardContent);
                        peopleBigCardContent.classList.remove('fade-out');

                        setTimeout(() => {
                            if (this.peopleShow === null) {
                                this.peopleShow = new PeopleShow(data['globs']);
                            } else {
                                this.peopleShow.start();
                            }
                        }, 300);
                    }, 300);
                }, 0);
            })
            .catch(error => {
                console.error('Error fetching data:', error);
            });
    }

    openCard(peopleCard) {
        const id = peopleCard.getAttribute('data-id');
        console.log('PeopleCard card clicked', id);
        const photoDiv = peopleCard.querySelector('.photo');
        const bounds = photoDiv.getBoundingClientRect();
        console.log('PeopleCard card bounds', bounds);

        const body = document.querySelector('body');
        body.classList.add('frozen');
        const scrollY = window.scrollY;
        const peopleBigCard = document.createElement('div');
        peopleBigCard.classList.add('people-big-card');
        peopleBigCard.style.left = `${bounds.left}px`;
        peopleBigCard.style.top = `${bounds.top + scrollY}px`;
        peopleBigCard.style.width = `${bounds.width}px`;
        peopleBigCard.style.height = `${bounds.height}px`;
        document.addEventListener('keydown', this.closeOnEscapeKey)
        document.body.appendChild(peopleBigCard);

        setTimeout(() => {
            peopleBigCard.classList.add('growable');
        }, 0);
        setTimeout(() => {
            peopleBigCard.style.left = '1rem';
            peopleBigCard.style.top = `${16 + scrollY}px`;
            peopleBigCard.style.width = `calc(100vw - 2rem)`;
            peopleBigCard.style.height = `calc(100vh - 2rem)`;
        }, 0);

        const peopleBigCardContent = document.createElement('div');
        peopleBigCardContent.classList.add('people-big-card-content');
        const peoplePhotoImg = peopleCard.querySelector('img');
        if (peoplePhotoImg === null) {
            peopleBigCard.appendChild(peopleBigCardContent);
            return;
        }
        peopleBigCardContent.appendChild(peoplePhotoImg.cloneNode(true));
        peopleBigCardContent.querySelector('img').classList.add('growing-image');
        peopleBigCard.appendChild(peopleBigCardContent);
    }

    addCloseButton(div) {
        const peopleBigCardCloseButton = document.createElement('button');
        const svgCancel = document.querySelector('#svgs svg#cancel');
        const svg = svgCancel.cloneNode(true);
        svg.removeAttribute('id');
        peopleBigCardCloseButton.classList.add('people-big-card-close-button');
        peopleBigCardCloseButton.appendChild(svg);
        peopleBigCardCloseButton.addEventListener('click', this.closeCard);
        div.appendChild(peopleBigCardCloseButton);
    }

    closeCard() {
        const peopleBigCard = document.querySelector('.people-big-card');
        const peopleCard = this.currentPeopleCard;
        const photoDiv = peopleCard.querySelector('.photo');
        const bounds = photoDiv.getBoundingClientRect();
        const scrollY = window.scrollY;
        const profileDiv = peopleBigCard.querySelector('.profile');

        if (profileDiv) {
            profileDiv.style.position = 'absolute';
            profileDiv.style.left = '0';
            profileDiv.style.top = '0';
            profileDiv.style.width = '100%';
            profileDiv.style.height = '100%';
            profileDiv.style.transition = 'all 0.3s ease-in-out';
        }

        peopleBigCard.style.left = `${bounds.left}px`;
        peopleBigCard.style.top = `${bounds.top + scrollY}px`;
        peopleBigCard.style.width = `${bounds.width}px`;
        peopleBigCard.style.height = `${bounds.height}px`;

        setTimeout(() => {
            peopleBigCard.classList.remove('growable');
            document.body.removeChild(peopleBigCard);
        }, 300);
        document.removeEventListener('keydown', this.closeOnEscapeKey)
        body.classList.remove('frozen');
        this.currentPeopleCard = null;

        this.preferredName.syncFromSessionStorage();
    }

    closeOnEscapeKey(e) {
        if (e.key === 'Escape') {
            this.closeCard();
        }
    }
}