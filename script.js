window.__revealReady = true;

/* CLONING CONFIG — ciudad, cobertura y WhatsApp. El HTML visible debe repetir estos mismos datos. */
const BUSINESS = {
  name: "Aire Copiapó",
  city: "Copiapó",
  region: "Atacama",
  country: "CL",
  domain: "https://www.aireacondicionadocopiapo.cl/",
  /* BUSINESS_PHONE */
  phone: "+56 9 2365 0596",
  /* BUSINESS_EMAIL */
  email: "eduardorojas@outlook.cl",
  years: 13,
  sectors: [
    "Centro de Copiapó",
    "El Palomar",
    "Paipote",
    "Llanos de Ollantay",
    "Rosario",
    "Juan Pablo II",
    "Pedro León Gallo",
    "San Fernando"
  ],
  scheduledCities: ["Tierra Amarilla", "Caldera"]
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

const trackWhatsAppLead = (serviceType, params = {}) => {
  const service = serviceType || "consulta-general";
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({
    event: "whatsapp_lead",
    service_type: service,
    page_location: window.location.href,
    link_url: params.link_url || "",
    sector: params.sector || "",
    recinto: params.recinto || ""
  });
  if (typeof gtag === "function") {
    gtag("event", "generate_lead", {
      event_category: "whatsapp",
      service_type: service,
      page_location: window.location.href,
      sector: params.sector || "",
      recinto: params.recinto || ""
    });
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
      trackWhatsAppLead(link.getAttribute("data-service-type"), {
        link_url: link.href,
        sector: link.getAttribute("data-sector") || "",
        recinto: link.getAttribute("data-recinto") || ""
      });
    }
  });

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute("data-wa-form")) return;
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
const phoneDigits = (phone) => phone.replace(/\D/g, "");

const PATH_WA_HINT = {
  "/": "cotizar aire acondicionado en Copiapó",
  "/tierra-amarilla/": "cotizar aire acondicionado en Tierra Amarilla",
  "/caldera/": "cotizar aire acondicionado en Caldera",
  "/bahia-inglesa/": "cotizar aire acondicionado en Bahía Inglesa",
  "/calefaccion/": "cotizar calefacción en Copiapó",
  "/ducteria/": "cotizar ductería HVAC en Copiapó",
  "/cobre-soldadura-plata/": "cotizar cobre y soldadura plata en Copiapó",
  "/gasfiteria-ppr/": "cotizar gasfitería y PPR en Copiapó",
  "/mantencion-aire-acondicionado/": "agendar mantención de aire acondicionado en Copiapó",
  "/reparacion-carga-gas/": "diagnosticar / reparar aire acondicionado en Copiapó",
  "/calculadora-btu/": "cotizar instalación según el resultado de la calculadora de BTU",
  "/guias/": "consultar sobre climatización en Copiapó",
  "/guias/cuantos-btu-necesito/": "cotizar instalación tras revisar la guía de BTU",
  "/guias/aire-acondicionado-polvo-desierto/": "agendar mantención por polvo en Copiapó",
  "/guias/inverter-vs-on-off-atacama/": "cotizar un inverter en Copiapó",
  "/gracias.html": "confirmar horario de atención"
};

const waHintForPath = (pathname) => {
  if (PATH_WA_HINT[pathname]) return PATH_WA_HINT[pathname];
  if (pathname.indexOf("/guias/") === 0) return "consultar sobre climatización en Copiapó";
  return "cotizar con Aire Copiapó";
};

const defaultWaText = (pathname) => {
  const city = BUSINESS.city || "Copiapó";
  if (pathname === "/" || pathname === "/index.html") {
    return "Hola, necesito cotizar aire acondicionado en " + city + ". Sector: ";
  }
  return "Hola, quiero " + waHintForPath(pathname) + ".";
};

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

  const digits = phoneDigits(BUSINESS.phone);
  document.querySelectorAll("[data-business-whatsapp]").forEach((node) => {
    node.hidden = false;
    const custom = node.getAttribute("data-wa-text");
    const msg = custom || defaultWaText(window.location.pathname);
    node.href = "https://wa.me/" + digits + "?text=" + encodeURIComponent(msg);
  });
};

const initWhatsAppFloat = () => {
  if (!BUSINESS.phone) return;
  if (document.querySelector(".whatsapp-float")) return;
  if (window.location.pathname.indexOf("/cotizador/") === 0) return;

  const digits = phoneDigits(BUSINESS.phone);
  const msg = defaultWaText(window.location.pathname);
  const link = document.createElement("a");
  link.className = "whatsapp-float";
  link.href = "https://wa.me/" + digits + "?text=" + encodeURIComponent(msg);
  link.target = "_blank";
  link.rel = "noopener";
  link.setAttribute("data-service-type", "consulta-general");
  link.setAttribute("aria-label", "Escribir por WhatsApp");
  link.innerHTML = '<span class="whatsapp-float__label" aria-hidden="true">WhatsApp</span>';
  document.body.appendChild(link);
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

const ZONE_TO_COMUNA = {
  copiapo: "Copiapó",
  "tierra-amarilla": "Tierra Amarilla",
  caldera: "Caldera",
  "bahia-inglesa": "Caldera",
  vallenar: "Vallenar",
  "diego-de-almagro": "Diego de Almagro"
};

const formValue = (form, name) => {
  const field = form.elements.namedItem(name);
  if (!field || typeof field.value !== "string") return "";
  return field.value.trim();
};

const ensureHidden = (form, name, value) => {
  let input = form.querySelector('input[name="' + name + '"]');
  if (!input) {
    input = document.createElement("input");
    input.type = "hidden";
    input.name = name;
    form.appendChild(input);
  }
  input.value = value;
  return input;
};

const buildQuickWaMessage = (form) => {
  const city = BUSINESS.city || "Copiapó";
  const servicio = formValue(form, "Servicio") || "venta e instalación";
  const sector = formValue(form, "Sector") || "";
  const tipo = formValue(form, "Tipo");
  let message = "Hola, necesito cotizar " + servicio + " de aire acondicionado en " + city + ". Sector: " + sector;
  if (tipo) message += ". Tipo: " + tipo;
  if (form.dataset.prefillNote) message += ". " + form.dataset.prefillNote;
  return message + ".";
};

const buildLeadWhatsAppMessage = (form) => {
  if (form.querySelector('[name="Sector"]')) return buildQuickWaMessage(form);
  const comuna = formValue(form, "Comuna") || formValue(form, "Zona") || "No indicada";
  const servicio = formValue(form, "Servicio") || "No indicado";
  const nombre = formValue(form, "Nombre");
  const telefono = formValue(form, "Teléfono");
  const mensaje = formValue(form, "Mensaje");
  const btu = formValue(form, "Capacidad_BTU");
  const m2 = formValue(form, "Metros_cuadrados");
  const tipo =
    formValue(form, "Tipo_recinto") ||
    formValue(form, "Tipo_proyecto") ||
    formValue(form, "Tipo_trabajo") ||
    formValue(form, "Necesidad") ||
    formValue(form, "Cantidad_equipos") ||
    formValue(form, "Sintoma");
  const extended = Boolean(EXTENDED_COMMUNAS[comuna]);
  const anillo = formValue(form, "Anillo") || (extended ? "Ruta Extendida" : "Anillo Local");
  const lines = [
    "Hola, quiero cotizar aire acondicionado con Aire Copiapó.",
    "[" + anillo + "]",
    "Comuna: " + comuna,
    "Servicio: " + servicio
  ];
  if (tipo) lines.push("Detalle técnico: " + tipo);
  if (btu) lines.push("Capacidad BTU: " + btu);
  if (m2) lines.push("Metros: " + m2);
  if (nombre) lines.push("Nombre: " + nombre);
  if (telefono) lines.push("Teléfono: " + telefono);
  if (mensaje) lines.push("Detalle: " + mensaje);
  return lines.join("\n");
};

const syncLeadRing = (form) => {
  const select = form.querySelector("#lead-sector") || form.querySelector("#lead-comuna");
  const note = form.querySelector("#lead-ring-note");
  const anilloInput = form.querySelector("#lead-anillo");
  if (!select) return "Anillo Local";

  const option = select.options[select.selectedIndex];
  const ringAttr = option && option.getAttribute("data-ring");
  const extended = ringAttr === "extended" || ringAttr === "scheduled" || Boolean(EXTENDED_COMMUNAS[select.value]);
  const label = extended ? "Ruta Extendida" : "Anillo Local";

  if (anilloInput) anilloInput.value = label;
  if (note) note.hidden = !extended;
  return label;
};

const openFormWhatsApp = (form) => {
  if (!form.reportValidity()) return;
  if (form.id === "lead-form") syncLeadRing(form);

  const message = buildLeadWhatsAppMessage(form);
  const phone = BUSINESS.phone ? phoneDigits(BUSINESS.phone) : "";
  const serviceSelect = form.querySelector('[name="Servicio"]');
  const selected = serviceSelect && serviceSelect.selectedOptions ? serviceSelect.selectedOptions[0] : null;
  const serviceType = (selected && selected.getAttribute("data-service-type")) || "consulta-general";

  trackEvent("click_whatsapp_lead", {
    page_location: window.location.href,
    comuna: formValue(form, "Comuna") || formValue(form, "Sector") || formValue(form, "Zona"),
    anillo: formValue(form, "Anillo"),
    form_id: form.id,
    service_type: serviceType
  });
  trackWhatsAppLead(serviceType, {
    sector: formValue(form, "Sector") || formValue(form, "Comuna") || formValue(form, "Zona"),
    recinto: formValue(form, "Tipo")
  });

  if (phone) {
    window.open("https://wa.me/" + phone + "?text=" + encodeURIComponent(message), "_blank", "noopener");
    return;
  }

  const extra = form.querySelector("#lead-mensaje") || form.querySelector('textarea[name="Mensaje"]');
  if (extra && !extra.value.trim()) {
    extra.value = message;
  }
  form.requestSubmit();
};

const ensureWhatsAppButton = (form) => {
  let waBtn = form.querySelector("[data-wa-fallback]");
  if (waBtn) return waBtn;

  const actions = form.querySelector(".form-actions");
  waBtn = document.createElement("button");
  waBtn.type = "button";
  waBtn.className = "btn btn--ghost";
  waBtn.id = form.id === "lead-form" ? "lead-whatsapp" : form.id + "-whatsapp";
  waBtn.setAttribute("data-wa-fallback", "");
  waBtn.textContent = "WhatsApp con estos datos";

  if (actions) {
    actions.appendChild(waBtn);
  } else {
    const submit = form.querySelector('button[type="submit"]');
    if (submit && submit.parentNode) {
      const wrap = document.createElement("div");
      wrap.className = "form-actions";
      submit.parentNode.insertBefore(wrap, submit);
      wrap.appendChild(submit);
      wrap.appendChild(waBtn);
    } else {
      form.appendChild(waBtn);
    }
  }
  return waBtn;
};

const initLeadFormCoverage = () => {
  const forms = [
    document.getElementById("lead-form"),
    document.getElementById("cotizar")
  ].filter(Boolean);

  forms.forEach((form) => {
    ensureHidden(form, "Pagina_origen", window.location.pathname);

    if (form.hasAttribute("data-wa-form")) {
      const sector = form.querySelector("#lead-sector");
      if (sector) {
        sector.addEventListener("change", () => syncLeadRing(form));
        syncLeadRing(form);
      }
      form.addEventListener("submit", (event) => {
        event.preventDefault();
        openFormWhatsApp(form);
      });
      return;
    }

    if (form.id === "lead-form") {
      const comuna = form.querySelector("#lead-comuna");
      if (comuna) {
        comuna.addEventListener("change", () => syncLeadRing(form));
        syncLeadRing(form);
      }
    }

    const waBtn = ensureWhatsAppButton(form);
    waBtn.addEventListener("click", () => openFormWhatsApp(form));
  });
};

const selectOptionByValue = (select, value) => {
  if (!select || !value) return false;
  const wanted = value.toLowerCase();
  for (let i = 0; i < select.options.length; i += 1) {
    const opt = select.options[i];
    if (opt.value.toLowerCase() === wanted || opt.text.toLowerCase() === wanted) {
      select.selectedIndex = i;
      return true;
    }
  }
  for (let i = 0; i < select.options.length; i += 1) {
    const opt = select.options[i];
    if (opt.value.toLowerCase().indexOf(wanted) !== -1 || opt.text.toLowerCase().indexOf(wanted) !== -1) {
      select.selectedIndex = i;
      return true;
    }
  }
  return false;
};

const initFormPrefill = () => {
  const params = new URLSearchParams(window.location.search);
  const zona = params.get("zona") || "";
  const servicio = params.get("servicio") || "";
  const btu = params.get("btu") || "";
  const m2 = params.get("m2") || "";
  if (!zona && !servicio && !btu && !m2) return;

  const form = document.getElementById("lead-form") || document.getElementById("cotizar");
  if (!form) return;

  const comunaLabel = ZONE_TO_COMUNA[zona] || zona;
  if (comunaLabel) {
    const sectorSelect = form.querySelector("#lead-sector");
    const comunaSelect = form.querySelector("#lead-comuna") || form.elements.namedItem("Comuna");
    if (sectorSelect) {
      selectOptionByValue(sectorSelect, comunaLabel);
      syncLeadRing(form);
    } else if (comunaSelect && comunaSelect.tagName === "SELECT") {
      selectOptionByValue(comunaSelect, comunaLabel);
      syncLeadRing(form);
    }
  }

  if (servicio) {
    const servicioSelect = form.querySelector("#lead-servicio") || form.elements.namedItem("Servicio");
    if (servicioSelect && servicioSelect.tagName === "SELECT") {
      selectOptionByValue(servicioSelect, decodeURIComponent(servicio));
    }
  }

  if (btu) ensureHidden(form, "Capacidad_BTU", btu);
  if (m2) ensureHidden(form, "Metros_cuadrados", m2);

  const mensaje = form.querySelector("#lead-mensaje") || form.querySelector('textarea[name="Mensaje"]');
  const parts = [];
  if (btu) parts.push("Capacidad estimada: " + Number(btu).toLocaleString("es-CL") + " BTU");
  if (m2) parts.push(m2 + " m²");
  if (comunaLabel) parts.push("Zona: " + comunaLabel);
  if (parts.length) {
    const note = "Resultado calculadora BTU — " + parts.join(" · ");
    form.dataset.prefillNote = note;
    if (mensaje && !mensaje.value.trim()) mensaje.value = note + ".";
  }

  if (btu || m2) {
    let badge = form.querySelector(".form-prefill-badge");
    if (!badge) {
      badge = document.createElement("p");
      badge.className = "form-prefill-badge";
      badge.setAttribute("role", "status");
      const title = form.querySelector(".quick-form__title");
      if (title && title.parentNode) {
        title.insertAdjacentElement("afterend", badge);
      } else {
        form.insertBefore(badge, form.firstChild);
      }
    }
    const bits = [];
    if (btu) bits.push(Number(btu).toLocaleString("es-CL") + " BTU");
    if (m2) bits.push(m2 + " m²");
    if (comunaLabel) bits.push(comunaLabel);
    badge.textContent = "Capacidad pre-cargada: " + bits.join(" · ");
  }

  if (window.location.hash === "#lead-form" || window.location.hash === "#cotizar") {
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    window.requestAnimationFrame(() => {
      form.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "center" });
    });
  }
};

const initThanksPage = () => {
  if (window.location.pathname.indexOf("gracias") === -1) return;
  const params = new URLSearchParams(window.location.search);
  const servicio = params.get("servicio") || "";
  const ubicacion = params.get("ubicacion") || "";
  const summary = document.getElementById("thanks-summary");
  if (summary && (servicio || ubicacion)) {
    const bits = [];
    if (servicio) bits.push(servicio);
    if (ubicacion) bits.push(ubicacion);
    summary.textContent = "Recibimos tu solicitud de " + bits.join(" en ") + ". Eduardo te contactará a la brevedad.";
    summary.hidden = false;
  }

  const wa = document.getElementById("thanks-whatsapp");
  if (wa && BUSINESS.phone) {
    const digits = phoneDigits(BUSINESS.phone);
    let msg = "Hola Eduardo, acabo de enviar una solicitud por la web";
    if (servicio) msg += " de " + servicio;
    if (ubicacion) msg += " en " + ubicacion;
    msg += ". ¿Me puedes confirmar horario?";
    wa.href = "https://wa.me/" + digits + "?text=" + encodeURIComponent(msg);
    wa.hidden = false;
  }
};

document.addEventListener("DOMContentLoaded", () => {
  setText("[data-year]", new Date().getFullYear());
  initBusiness();
  initWhatsAppFloat();
  initFormFeedback();
  initLeadTracking();
  initLeadFormCoverage();
  initFormPrefill();
  initThanksPage();
  initScrollEffects();
});
