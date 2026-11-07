window.__revealReady = true;

const BUSINESS = {
  name: "Aire Copiapó",
  /* BUSINESS_PHONE: dejar "" hasta tener un número real. Al completarlo se activan los tel: */
  phone: "",
  /* BUSINESS_EMAIL */
  email: "eduardorojas@outlook.cl"
};

const setText = (selector, value) => {
  document.querySelectorAll(selector).forEach((node) => {
    node.textContent = value;
  });
};

const phoneHref = (phone) => "tel:" + phone.replace(/[^\d+]/g, "");

const initBusiness = () => {
  setText("[data-business-name]", BUSINESS.name);

  if (BUSINESS.email) {
    document.querySelectorAll("[data-business-email]").forEach((node) => {
      node.href = "mailto:" + BUSINESS.email;
      node.textContent = BUSINESS.email;
    });
  }

  if (!BUSINESS.phone) return;

  document.querySelectorAll("[data-business-phone-row]").forEach((row) => {
    row.hidden = false;
  });

  document.querySelectorAll("[data-business-phone]").forEach((node) => {
    node.hidden = false;
    node.href = phoneHref(BUSINESS.phone);
    if (node.closest("[data-business-phone-row]")) {
      node.textContent = BUSINESS.phone;
    }
  });

  const digits = BUSINESS.phone.replace(/\D/g, "");
  document.querySelectorAll("[data-business-whatsapp]").forEach((node) => {
    node.hidden = false;
    node.href = "https://wa.me/" + digits;
  });
};

const initFormFeedback = () => {
  const error = new URLSearchParams(window.location.search).get("error");
  if (!error) return;

  const messages = {
    campos: "Revisa nombre, teléfono y servicio antes de enviar.",
    envio: "No pudimos enviar la solicitud. Inténtalo de nuevo en unos minutos.",
    config: "El formulario no está disponible en este momento. Escríbenos por correo."
  };
  const text = messages[error];
  if (!text) return;

  const toast = document.createElement("p");
  toast.className = "toast";
  toast.setAttribute("role", "status");
  toast.textContent = text;
  document.body.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add("is-visible"));

  const form = document.getElementById("lead-form") || document.getElementById("cotizar");
  if (form) {
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    form.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "center" });
  }

  window.setTimeout(() => {
    toast.classList.remove("is-visible");
    window.setTimeout(() => toast.remove(), 400);
  }, 6000);
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
  initBusiness();
  initFormFeedback();
  initScrollEffects();
});
