(() => {
  const BASE_BTU_PER_M2 = 600;
  const EXTRA_PERSON_BTU = 600;
  const KITCHEN_BTU = 4000;
  const COMMERCIAL = [9000, 12000, 18000, 24000, 36000];

  const ZONE_FACTOR = {
    copiapo: 1.15,
    "tierra-amarilla": 1.15,
    caldera: 0.95,
    "bahia-inglesa": 0.95,
    vallenar: 1.1,
    "diego-de-almagro": 1.1
  };

  const ZONE_LABEL = {
    copiapo: "Copiapó (interior)",
    "tierra-amarilla": "Tierra Amarilla (interior)",
    caldera: "Caldera (costa)",
    "bahia-inglesa": "Bahía Inglesa (costa)",
    vallenar: "Vallenar",
    "diego-de-almagro": "Diego de Almagro"
  };

  const form = document.getElementById("btu-form");
  const output = document.getElementById("btu-result");
  if (!form || !output) return;

  const fmt = (n) => n.toLocaleString("es-CL");

  const pickCommercial = (btu) => {
    for (let i = 0; i < COMMERCIAL.length; i += 1) {
      if (COMMERCIAL[i] >= btu) return COMMERCIAL[i];
    }
    return COMMERCIAL[COMMERCIAL.length - 1];
  };

  const readState = () => {
    const m2 = Math.max(0, Number(form.m2.value) || 0);
    const zone = form.zona.value || "copiapo";
    const people = Math.max(1, Number(form.personas.value) || 1);
    const west = form.orientacion.value === "poniente";
    const topFloor = form.techo.checked;
    const kitchen = form.cocina.checked;
    const glass = form.ventanales.checked;
    return { m2, zone, people, west, topFloor, kitchen, glass };
  };

  const calc = (s) => {
    const lines = [];
    let btu = s.m2 * BASE_BTU_PER_M2;
    lines.push({ label: `Base ${s.m2} m² × ${BASE_BTU_PER_M2} BTU`, value: s.m2 * BASE_BTU_PER_M2 });

    const zf = ZONE_FACTOR[s.zone] || 1.15;
    const zoneDelta = Math.round(btu * (zf - 1));
    btu = Math.round(btu * zf);
    lines.push({
      label: `Zona ${ZONE_LABEL[s.zone] || s.zone} (${zf > 1 ? "+" : ""}${Math.round((zf - 1) * 100)}%)`,
      value: zoneDelta
    });

    if (s.west) {
      const d = Math.round(btu * 0.15);
      btu += d;
      lines.push({ label: "Orientación poniente (+15%)", value: d });
    }

    if (s.topFloor) {
      const d = Math.round(btu * 0.2);
      btu += d;
      lines.push({ label: "Último piso / techo de zinc (+20%)", value: d });
    }

    if (s.glass) {
      const d = Math.round(btu * 0.1);
      btu += d;
      lines.push({ label: "Ventanales amplios (+10%)", value: d });
    }

    const extraPeople = Math.max(0, s.people - 1);
    if (extraPeople > 0) {
      const d = extraPeople * EXTRA_PERSON_BTU;
      btu += d;
      lines.push({ label: `Personas extra (${extraPeople} × ${EXTRA_PERSON_BTU})`, value: d });
    }

    if (s.kitchen) {
      btu += KITCHEN_BTU;
      lines.push({ label: "Cocina / equipos con calor", value: KITCHEN_BTU });
    }

    const commercial = pickCommercial(btu);
    return { btu, commercial, lines };
  };

  const updateWhatsApp = (commercial, zoneLabel) => {
    const phone = typeof BUSINESS !== "undefined" && BUSINESS.phone ? BUSINESS.phone : "";
    const link = document.querySelector("[data-calc-whatsapp]");
    if (!link) return;
    if (!phone) {
      link.hidden = true;
      return;
    }
    const digits = phone.replace(/\D/g, "");
    const msg = `Hola, usé la calculadora de BTU. Me salió aproximadamente ${fmt(commercial)} BTU para un espacio en ${zoneLabel}. ¿Me ayudan a cotizar la instalación?`;
    link.hidden = false;
    link.href = "https://wa.me/" + digits + "?text=" + encodeURIComponent(msg);
  };

  const updateCotizar = (commercial, m2, zone) => {
    const cotizar = document.querySelector("[data-calc-cotizar]");
    if (!cotizar) return;
    const qs = new URLSearchParams({
      servicio: "Montaje aire acondicionado",
      zona: zone,
      btu: String(commercial),
      m2: String(m2 || "")
    });
    cotizar.href = "/?" + qs.toString() + "#lead-form";
    cotizar.dataset.btu = String(commercial);
  };

  const trackCalc = (commercial, zone) => {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: "calculate_btu",
      btu_recommended: commercial,
      zone: zone,
      page_location: window.location.href
    });
  };

  let lastTracked = "";

  const render = (fromSubmit) => {
    const state = readState();
    if (!state.m2) {
      output.innerHTML = `
        <p class="calc-result__hint">Ingresa los metros cuadrados del espacio para ver la capacidad recomendada.</p>
        <p class="calc-result__note">Referencia orientativa para Atacama. Un técnico confirma en visita.</p>`;
      updateWhatsApp(12000, ZONE_LABEL[state.zone]);
      updateCotizar(12000, "", state.zone);
      return;
    }

    const { btu, commercial, lines } = calc(state);
    const zoneLabel = ZONE_LABEL[state.zone] || state.zone;
    const rows = lines
      .map((row) => {
        const sign = row.value >= 0 ? "+" : "";
        return `<li><span>${row.label}</span><strong>${sign}${fmt(row.value)}</strong></li>`;
      })
      .join("");

    output.innerHTML = `
      <p class="calc-result__kicker">Capacidad estimada</p>
      <p class="calc-result__btu"><strong>${fmt(btu)}</strong> <span>BTU calculados</span></p>
      <p class="calc-result__equipo">Equipo comercial sugerido: <strong>${fmt(commercial)} BTU</strong></p>
      <ul class="calc-result__breakdown" aria-label="Desglose del cálculo">${rows}</ul>
      <p class="calc-result__note">Es una guía para Copiapó y Atacama. Orientación, aislamiento y uso real se confirman en la visita técnica.</p>`;

    updateWhatsApp(commercial, zoneLabel);
    updateCotizar(commercial, state.m2, state.zone);

    const key = commercial + "|" + state.zone + "|" + state.m2;
    if (fromSubmit || key !== lastTracked) {
      lastTracked = key;
      if (fromSubmit) trackCalc(commercial, state.zone);
    }
  };

  const applyZoneFromQuery = () => {
    const zona = new URLSearchParams(window.location.search).get("zona");
    if (!zona || !ZONE_FACTOR[zona]) return;
    form.zona.value = zona;
  };

  form.addEventListener("input", () => render(false));
  form.addEventListener("change", () => render(false));
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    render(true);
  });

  applyZoneFromQuery();
  render(false);
})();
