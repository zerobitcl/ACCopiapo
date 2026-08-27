const BUSINESS = {
  name: "Aire Copiapó",
  phone: "+56 9 9428 4048",
  whatsapp: "56994284048",
  address: "Copiapó, Región de Atacama, Chile",
  email: "contacto@airecopiapo.cl"
};

const DEFAULT_MESSAGE = "Hola, necesito cotizar aire acondicionado en Copiapó / Atacama.";

const onlyDialable = (value) => value.replace(/[^\d+]/g, "");

const openWhatsApp = (message = DEFAULT_MESSAGE) => {
  const url = `https://wa.me/${BUSINESS.whatsapp}?text=${encodeURIComponent(message)}`;
  window.open(url, "_blank", "noopener,noreferrer");
};

const setText = (selector, value) => {
  document.querySelectorAll(selector).forEach((node) => {
    node.textContent = value;
  });
};

const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

let toastTimer;
const showToast = (message) => {
  const toast = document.querySelector("[data-toast]");
  if (!toast) return;
  toast.textContent = message;
  toast.hidden = false;
  requestAnimationFrame(() => toast.classList.add("is-visible"));
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    toast.classList.remove("is-visible");
    setTimeout(() => { toast.hidden = true; }, 300);
  }, 3200);
};

const animateCount = (el) => {
  const target = Number(el.dataset.count) || 0;
  const suffix = el.dataset.suffix || "";
  if (prefersReducedMotion) {
    el.textContent = target.toLocaleString("es-CL") + suffix;
    return;
  }
  const duration = 1400;
  const start = performance.now();
  const step = (now) => {
    const progress = Math.min((now - start) / duration, 1);
    // easeOutExpo para un conteo con desaceleración natural
    const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
    el.textContent = Math.round(target * eased).toLocaleString("es-CL") + suffix;
    if (progress < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
};

const initScrollEffects = () => {
  const header = document.querySelector(".site-header");
  if (header) {
    const onScroll = () => header.classList.toggle("is-scrolled", window.scrollY > 40);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  const revealTargets = document.querySelectorAll("[data-reveal]");
  const counters = document.querySelectorAll("[data-count]");

  if (!("IntersectionObserver" in window)) {
    revealTargets.forEach((el) => el.classList.add("is-visible"));
    counters.forEach(animateCount);
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

  const countObserver = new IntersectionObserver((entries, obs) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      animateCount(entry.target);
      obs.unobserve(entry.target);
    });
  }, { threshold: 0.6 });
  counters.forEach((el) => countObserver.observe(el));
};

document.addEventListener("DOMContentLoaded", () => {
  setText("[data-year]", new Date().getFullYear());
  setText("[data-business-name]", BUSINESS.name);
  setText("[data-business-phone]", BUSINESS.phone);
  setText("[data-business-whatsapp]", BUSINESS.whatsapp);
  setText("[data-business-address]", BUSINESS.address);

  document.querySelectorAll("[data-business-email]").forEach((link) => {
    link.textContent = BUSINESS.email;
    link.href = `mailto:${BUSINESS.email}`;
  });

  document.querySelectorAll("[data-phone-link]").forEach((link) => {
    link.href = `tel:${onlyDialable(BUSINESS.phone)}`;
  });

  document.querySelectorAll("[data-whatsapp-button]").forEach((button) => {
    button.addEventListener("click", () => openWhatsApp());
  });

  document.querySelectorAll("[data-quote-form]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      event.preventDefault();

      const data = new FormData(form);
      const name = String(data.get("name") || "").trim();
      const phone = String(data.get("phone") || "").trim();
      const service = String(data.get("service") || "").trim();
      const city = String(data.get("city") || "").trim();
      const message = String(data.get("message") || "").trim();

      const lead = [
        "Hola, quiero cotizar aire acondicionado en Copiapó.",
        name && `Nombre: ${name}`,
        phone && `Teléfono: ${phone}`,
        service && `Servicio: ${service}`,
        city && `Comuna: ${city}`,
        message && `Mensaje: ${message}`
      ].filter(Boolean).join("\n");

      showToast("Abriendo WhatsApp con tu solicitud...");
      openWhatsApp(lead);
    });
  });

  initScrollEffects();
});
