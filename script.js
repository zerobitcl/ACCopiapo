const BUSINESS = {
  name: "BUSINESS_NAME",
  phone: "+56 9 XXXX XXXX",
  whatsapp: "569XXXXXXXX",
  address: "BUSINESS_ADDRESS",
  email: "BUSINESS_EMAIL"
};

const DEFAULT_MESSAGE = "Hola, necesito cotizar aire acondicionado y climatización en Copiapó.";

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

      openWhatsApp(lead);
    });
  });
});
