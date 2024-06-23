export class NavBar {
  constructor() {
    this.root = document.documentElement;
  }

  navBarColor(hsl) {

    this.root.style.setProperty("--navbar-bg", "hsl(" + hsl.h + ", " + hsl.s + "%, " + (hsl.l - 10) + "%)");
    this.root.style.setProperty("--navbar-bg-50", "hsla(" + hsl.h + ", " + hsl.s + "%, " + hsl.l + "%, .5)");
    this.root.style.setProperty("--navbar-bg-75", "hsla(" + hsl.h + ", " + hsl.s + "%, " + hsl.l + "%, .75)");

    const navbarLinks = document.querySelectorAll(".navbar a");
    const footer = document.querySelector(".home-footer");
    if (hsl.l > 50) {
      navbarLinks?.forEach(link => {
        link.classList.add("dark");
      });
      footer?.classList.add("dark");
    }
  }
}