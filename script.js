const BUSINESS = {
  name: "Aire Copiapó"
};

const setText = (selector, value) => {
  document.querySelectorAll(selector).forEach((node) => {
    node.textContent = value;
  });
};

const initScrollEffects = () => {
  const header = document.querySelector(".site-header");
  if (header) {
    const onScroll = () => header.classList.toggle("is-scrolled", window.scrollY > 40);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  const revealTargets = document.querySelectorAll("[data-reveal]");
  if (!("IntersectionObserver" in window)) {
    revealTargets.forEach((el) => el.classList.add("is-visible"));
    return;
  }

  const revealObserver = new IntersectionObserver((entries, obs) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add("is-visible");
      obs.unobserve(entry.target);
    });
  }, { threshold: 0.15, rootMargin: "0px 0px -40px 0px" });
  revealTargets.forEach((el) => revealObserver.observe(el));
};

document.addEventListener("DOMContentLoaded", () => {
  setText("[data-year]", new Date().getFullYear());
  setText("[data-business-name]", BUSINESS.name);

  initScrollEffects();
});
