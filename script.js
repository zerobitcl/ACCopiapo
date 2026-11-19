window.__revealReady = true;

const BUSINESS = {
  name: "Aire Copiapó",
  /* BUSINESS_PHONE: dejar "" hasta tener un número real. Al completarlo se activan los tel: */
  phone: "",
  /* BUSINESS_EMAIL */
  email: "eduardorojas@outlook.cl"
};

/* GTM async desde script.js (defer): no entra al critical path del HTML. */
(function () {
  const id = "GTM-5XZTNHWJ";
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({ "gtm.start": new Date().getTime(), event: "gtm.js" });
  window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
  window.gtag("js", new Date());
  const s = document.createElement("script");
  s.async = true;
  s.src = "https://www.googletagmanager.com/gtm.js?id=" + id;
  document.head.appendChild(s);
})();

const trackEvent = (name, params = {}) => {
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push(Object.assign({ event: name }, params));
  if (typeof window.gtag === "function") {
    window.gtag("event", name, params);
  }
};

const initLeadTracking = () => {
  document.addEventListener("click", (event) => {
    const link = event.target.closest("a[href]");
    if (!link) return;
    const raw = link.getAttribute("href") || "";
    if (raw.indexOf("tel:") === 0) {
      trackEvent("phone_click", { link_url: raw, page_location: window.location.href });
      return;
    }
    let host = "";
    try {
      host = new URL(link.href, window.location.origin).hostname;
    } catch (err) {
      return;
    }
    if (host === "wa.me" || host === "api.whatsapp.com") {
      trackEvent("click_whatsapp", { link_url: link.href, page_location: window.location.href });
    }
  });

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.id !== "lead-form" && form.id !== "cotizar") return;

    event.preventDefault();
    let sent = false;
    const submitForm = () => {
      if (sent) return;
      sent = true;
      HTMLFormElement.prototype.submit.call(form);
    };
    trackEvent("generate_lead", {
      form_id: form.id,
      page_location: window.location.href,
      transport_type: "beacon",
      event_callback: submitForm,
      event_timeout: 800
    });
    window.setTimeout(submitForm, 900);
  });
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

/* CRO: anillo operativo + WhatsApp prearmado sin depender de librerías */
const EXTENDED_COMMUNAS = {
  Vallenar: 1,
  Chañaral: 1,
  "Diego de Almagro": 1,
  Taltal: 1,
  "Otra localidad": 1
};

const formValue = (form, name) => {
  const field = form.elements.namedItem(name);
  if (!field || typeof field.value !== "string") return "";
  return field.value.trim();
};

const buildLeadWhatsAppMessage = (form) => {
  const comuna = formValue(form, "Comuna") || "No indicada";
  const servicio = formValue(form, "Servicio") || "No indicado";
  const nombre = formValue(form, "Nombre");
  const telefono = formValue(form, "Teléfono");
  const mensaje = formValue(form, "Mensaje");
  const extended = Boolean(EXTENDED_COMMUNAS[comuna]);
  const anillo = extended ? "[Ruta Extendida]" : "[Anillo Local]";
  const lines = [
    "Hola, quiero cotizar aire acondicionado con Aire Copiapó.",
    anillo,
    "Comuna: " + comuna,
    "Servicio: " + servicio
  ];
  if (nombre) lines.push("Nombre: " + nombre);
  if (telefono) lines.push("Teléfono: " + telefono);
  if (mensaje) lines.push("Detalle: " + mensaje);
  return lines.join("\n");
};

const syncLeadRing = (form) => {
  const select = form.querySelector("#lead-comuna");
  const note = form.querySelector("#lead-ring-note");
  const anilloInput = form.querySelector("#lead-anillo");
  if (!select) return "Anillo Local";

  const option = select.options[select.selectedIndex];
  const ringAttr = option && option.getAttribute("data-ring");
  const extended = ringAttr === "extended" || Boolean(EXTENDED_COMMUNAS[select.value]);
  const label = extended ? "Ruta Extendida" : "Anillo Local";

  if (anilloInput) anilloInput.value = label;
  if (note) note.hidden = !extended;
  return label;
};

const initLeadFormCoverage = () => {
  const form = document.getElementById("lead-form");
  if (!form) return;

  const waBtn = document.getElementById("lead-whatsapp");
  const comuna = form.querySelector("#lead-comuna");

  const refresh = () => syncLeadRing(form);
  if (comuna) {
    comuna.addEventListener("change", refresh);
    refresh();
  }

  if (!waBtn) return;

  waBtn.addEventListener("click", () => {
    if (!form.reportValidity()) return;

    syncLeadRing(form);
    const message = buildLeadWhatsAppMessage(form);
    const phone = BUSINESS.phone ? BUSINESS.phone.replace(/\D/g, "") : "";

    trackEvent("click_whatsapp_lead", {
      page_location: window.location.href,
      comuna: formValue(form, "Comuna"),
      anillo: formValue(form, "Anillo")
    });

    if (phone) {
      window.open("https://wa.me/" + phone + "?text=" + encodeURIComponent(message), "_blank", "noopener");
      return;
    }

    /* Fallback CRO: sin número WA configurado, conserva el lead por correo */
    const extra = form.querySelector("#lead-mensaje");
    if (extra && !extra.value.trim()) {
      extra.value = message;
    }
    form.requestSubmit();
  });
};

document.addEventListener("DOMContentLoaded", () => {
  setText("[data-year]", new Date().getFullYear());
  initBusiness();
  initFormFeedback();
  initLeadTracking();
  initLeadFormCoverage();
  initScrollEffects();
});
