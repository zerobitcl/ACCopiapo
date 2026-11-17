(() => {
  const CFG = window.SA_CONFIG || {};
  const DRAFT_KEY = "sa_cotizador_draft_v1";
  const money = new Intl.NumberFormat("es-CL", {
    style: "currency",
    currency: "CLP",
    maximumFractionDigits: 0
  });

  const els = {
    form: document.getElementById("quote-form"),
    items: document.getElementById("items-list"),
    addItem: document.getElementById("btn-add-item"),
    iva: document.getElementById("aplica_iva"),
    neto: document.getElementById("total-neto"),
    ivaTotal: document.getElementById("total-iva"),
    final: document.getElementById("total-final"),
    sticky: document.getElementById("sticky-total"),
    generate: document.getElementById("btn-generate"),
    logout: document.getElementById("btn-logout"),
    rut: document.getElementById("cliente_rut"),
    rutHint: document.getElementById("rut-hint"),
    sheet: document.getElementById("result-sheet"),
    resultNumber: document.getElementById("result-number"),
    resultMsg: document.getElementById("result-msg"),
    resultDownload: document.getElementById("result-download"),
    resultWa: document.getElementById("result-whatsapp"),
    resultNew: document.getElementById("result-new"),
    resultClose: document.getElementById("result-close"),
    historyList: document.getElementById("history-list"),
    historyEmpty: document.getElementById("history-empty"),
    historySearch: document.getElementById("history-search"),
    tabQuote: document.getElementById("tab-quote"),
    tabHistory: document.getElementById("tab-history")
  };

  let items = [];
  let itemSeq = 0;
  let historyCache = [];

  const uid = () => {
    itemSeq += 1;
    return "it-" + itemSeq + "-" + Date.now().toString(36);
  };

  const cleanRut = (value) => String(value || "").replace(/[^0-9kK]/g, "").toUpperCase();

  const formatRut = (value) => {
    const raw = cleanRut(value);
    if (raw.length < 2) return raw;
    const body = raw.slice(0, -1);
    const dv = raw.slice(-1);
    const withDots = body.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    return withDots + "-" + dv;
  };

  const validRut = (value) => {
    const raw = cleanRut(value);
    if (raw.length < 2) return false;
    const body = raw.slice(0, -1);
    const dv = raw.slice(-1);
    let sum = 0;
    let mul = 2;
    for (let i = body.length - 1; i >= 0; i -= 1) {
      sum += Number(body.charAt(i)) * mul;
      mul = mul === 7 ? 2 : mul + 1;
    }
    const expected = 11 - (sum % 11);
    const check = expected === 11 ? "0" : expected === 10 ? "K" : String(expected);
    return check === dv;
  };

  const parseMoney = (value) => {
    const n = Number(String(value).replace(/[^\d]/g, ""));
    return Number.isFinite(n) ? n : 0;
  };

  const blankItem = () => ({
    id: uid(),
    descripcion: "",
    cantidad: 1,
    precio: 0
  });

  const calc = () => {
    const neto = items.reduce((acc, item) => {
      const qty = Math.max(0, Number(item.cantidad) || 0);
      const price = Math.max(0, Number(item.precio) || 0);
      return acc + Math.round(qty * price);
    }, 0);
    const withIva = Boolean(els.iva && els.iva.checked);
    const iva = withIva ? Math.round(neto * 0.19) : 0;
    return { neto, iva, total: neto + iva, withIva };
  };

  const renderTotals = () => {
    const t = calc();
    els.neto.textContent = money.format(t.neto);
    els.ivaTotal.textContent = money.format(t.iva);
    els.final.textContent = money.format(t.total);
    els.sticky.textContent = money.format(t.total);
  };

  const renderItems = () => {
    els.items.innerHTML = items
      .map((item, index) => {
        const sub = Math.round((Number(item.cantidad) || 0) * (Number(item.precio) || 0));
        return `
          <article class="item-card" data-id="${item.id}">
            <div class="item-card__top">
              <strong>Ítem ${index + 1}</strong>
              <div class="item-card__actions">
                <button type="button" data-action="dup">Duplicar</button>
                <button type="button" class="is-danger" data-action="del" ${items.length < 2 ? "disabled" : ""}>Eliminar</button>
              </div>
            </div>
            <label>
              <span>Descripción</span>
              <textarea data-field="descripcion" rows="2" placeholder="Ej: Mantención refrigerador comercial + gas">${escapeHtml(item.descripcion)}</textarea>
            </label>
            <div class="grid-2">
              <label>
                <span>Cantidad</span>
                <input type="number" inputmode="decimal" min="0" step="1" data-field="cantidad" value="${item.cantidad}">
              </label>
              <label>
                <span>Precio unitario neto</span>
                <input type="text" inputmode="numeric" data-field="precio" value="${item.precio ? money.format(item.precio).replace(/\u00a0/g, " ") : ""}" placeholder="$0">
              </label>
            </div>
            <p class="item-subtotal">Subtotal: ${money.format(sub)}</p>
          </article>`;
      })
      .join("");
    renderTotals();
  };

  const escapeHtml = (value) =>
    String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");

  const collectPayload = () => {
    const t = calc();
    return {
      cliente: {
        nombre: valueOf("cliente_nombre"),
        rut: valueOf("cliente_rut"),
        telefono: valueOf("cliente_telefono"),
        direccion: valueOf("cliente_direccion"),
        email: valueOf("cliente_email")
      },
      items: items.map((item) => ({
        descripcion: String(item.descripcion || "").trim(),
        cantidad: Math.max(0, Number(item.cantidad) || 0),
        precio: Math.max(0, Math.round(Number(item.precio) || 0)),
        subtotal: Math.round((Number(item.cantidad) || 0) * (Number(item.precio) || 0))
      })),
      aplica_iva: t.withIva,
      neto: t.neto,
      iva: t.iva,
      total: t.total,
      validez_dias: Math.max(1, Number(document.getElementById("validez_dias").value) || 15),
      garantia: valueOf("garantia"),
      forma_pago: valueOf("forma_pago"),
      condiciones: valueOf("condiciones")
    };
  };

  const valueOf = (id) => {
    const node = document.getElementById(id);
    return node ? String(node.value || "").trim() : "";
  };

  const saveDraft = () => {
    const draft = {
      ...collectPayload(),
      itemsRaw: items
    };
    try {
      localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
    } catch (err) {
      /* ignore quota */
    }
  };

  const loadDraft = () => {
    try {
      const raw = localStorage.getItem(DRAFT_KEY);
      if (!raw) return false;
      const draft = JSON.parse(raw);
      if (!draft || typeof draft !== "object") return false;
      setValue("cliente_nombre", draft.cliente && draft.cliente.nombre);
      setValue("cliente_rut", draft.cliente && draft.cliente.rut);
      setValue("cliente_telefono", draft.cliente && draft.cliente.telefono);
      setValue("cliente_direccion", draft.cliente && draft.cliente.direccion);
      setValue("cliente_email", draft.cliente && draft.cliente.email);
      setValue("validez_dias", draft.validez_dias || 15);
      setValue("garantia", draft.garantia);
      setValue("forma_pago", draft.forma_pago);
      setValue("condiciones", draft.condiciones);
      if (els.iva) els.iva.checked = draft.aplica_iva !== false;
      if (Array.isArray(draft.itemsRaw) && draft.itemsRaw.length) {
        items = draft.itemsRaw.map((item) => ({
          id: item.id || uid(),
          descripcion: item.descripcion || "",
          cantidad: Number(item.cantidad) || 1,
          precio: Number(item.precio) || 0
        }));
      }
      return true;
    } catch (err) {
      return false;
    }
  };

  const setValue = (id, value) => {
    const node = document.getElementById(id);
    if (!node || value == null || value === "") return;
    node.value = value;
  };

  const clearDraft = () => {
    try {
      localStorage.removeItem(DRAFT_KEY);
    } catch (err) {
      /* ignore */
    }
  };

  const validateQuote = () => {
    const payload = collectPayload();
    if (!payload.cliente.nombre) return "Ingresa el nombre del cliente.";
    if (!validRut(payload.cliente.rut)) return "El RUT del cliente no es válido.";
    if (!payload.cliente.telefono) return "Ingresa el teléfono del cliente.";
    if (!payload.items.length) return "Agrega al menos un ítem.";
    const bad = payload.items.find((item) => !item.descripcion || item.cantidad <= 0 || item.precio < 0);
    if (bad) return "Revisa descripción, cantidad y precio de cada ítem.";
    if (payload.total <= 0) return "El total debe ser mayor a cero.";
    return "";
  };

  const api = async (url, options = {}) => {
    const headers = Object.assign(
      { "Content-Type": "application/json", "X-CSRF-Token": CFG.csrf || "" },
      options.headers || {}
    );
    const res = await fetch(url, Object.assign({}, options, { headers, credentials: "same-origin" }));
    const data = await res.json().catch(() => ({ ok: false, error: "Respuesta inválida" }));
    if (res.status === 401) {
      window.location.href = "index.php";
      throw new Error("Sesión expirada");
    }
    if (!res.ok || data.ok === false) {
      throw new Error(data.error || "No se pudo completar la operación.");
    }
    if (data.csrf) CFG.csrf = data.csrf;
    return data;
  };

  const showResult = (quote) => {
    els.resultNumber.textContent = quote.number || "Cotización";
    els.resultMsg.textContent = "PDF generado. Puedes descargarlo o enviarlo por WhatsApp.";
    els.resultDownload.href = quote.pdf_url || "#";
    const phone = (CFG.whatsapp || "").replace(/\D/g, "");
    const clientPhone = cleanPhone(quote.cliente && quote.cliente.telefono);
    const target = clientPhone || phone;
    const msg = `Hola, te envío la cotización ${quote.number || ""} de ${CFG.companyName || "Refrigeración San Agustín"}.\n${quote.pdf_url || ""}`;
    els.resultWa.href = target
      ? "https://wa.me/" + target + "?text=" + encodeURIComponent(msg)
      : "#";
    els.sheet.hidden = false;
  };

  const cleanPhone = (value) => {
    let digits = String(value || "").replace(/\D/g, "");
    if (digits.length === 9 && digits.startsWith("9")) digits = "56" + digits;
    if (digits.length === 11 && digits.startsWith("56")) return digits;
    return digits.length >= 11 ? digits : "";
  };

  const resetQuote = () => {
    items = [blankItem()];
    els.form.reset();
    if (els.iva) els.iva.checked = true;
    document.getElementById("validez_dias").value = "15";
    document.getElementById("garantia").value = "90 días en mano de obra";
    document.getElementById("forma_pago").value = "Transferencia o efectivo. 50% al confirmar, saldo al entregar.";
    document.getElementById("condiciones").value =
      "Cotización sujeta a inspección del equipo. Repuestos no incluidos salvo que se indiquen en los ítems. Precios en pesos chilenos.";
    clearDraft();
    renderItems();
    els.sheet.hidden = true;
  };

  const fillFromQuote = (quote) => {
    setValue("cliente_nombre", quote.cliente && quote.cliente.nombre);
    setValue("cliente_rut", quote.cliente && quote.cliente.rut);
    setValue("cliente_telefono", quote.cliente && quote.cliente.telefono);
    setValue("cliente_direccion", quote.cliente && quote.cliente.direccion);
    setValue("cliente_email", quote.cliente && quote.cliente.email);
    setValue("validez_dias", quote.validez_dias || 15);
    setValue("garantia", quote.garantia);
    setValue("forma_pago", quote.forma_pago);
    setValue("condiciones", quote.condiciones);
    if (els.iva) els.iva.checked = quote.aplica_iva !== false;
    items = (quote.items || []).map((item) => ({
      id: uid(),
      descripcion: item.descripcion || "",
      cantidad: Number(item.cantidad) || 1,
      precio: Number(item.precio) || 0
    }));
    if (!items.length) items = [blankItem()];
    renderItems();
    saveDraft();
    switchTab("quote");
  };

  const renderHistory = (filter = "") => {
    const q = String(filter || "").trim().toLowerCase();
    const list = historyCache.filter((row) => {
      if (!q) return true;
      const hay = [
        row.number,
        row.cliente && row.cliente.nombre,
        row.cliente && row.cliente.rut,
        row.cliente && row.cliente.telefono
      ]
        .join(" ")
        .toLowerCase();
      return hay.indexOf(q) !== -1;
    });

    els.historyEmpty.hidden = list.length > 0;
    els.historyList.innerHTML = list
      .map((row) => {
        const when = row.created_at ? new Date(row.created_at).toLocaleString("es-CL") : "";
        return `
          <article class="history-card" data-id="${escapeHtml(row.id)}">
            <div class="history-card__meta">
              <span>${escapeHtml(row.number || "")}</span>
              <span>${escapeHtml(when)}</span>
            </div>
            <h3>${escapeHtml((row.cliente && row.cliente.nombre) || "Sin nombre")}</h3>
            <p>${escapeHtml((row.cliente && row.cliente.rut) || "")} · ${money.format(row.total || 0)}</p>
            <div class="history-card__actions">
              ${row.pdf_url ? `<a class="btn btn--ghost" href="${escapeHtml(row.pdf_url)}" target="_blank" rel="noopener">PDF</a>` : `<span class="btn btn--ghost" aria-disabled="true">Sin PDF</span>`}
              <button class="btn btn--ghost" type="button" data-action="dup">Duplicar</button>
              <button class="btn btn--danger" type="button" data-action="del">Eliminar</button>
            </div>
          </article>`;
      })
      .join("");
  };

  const loadHistory = async () => {
    const data = await api("history.php?action=list");
    historyCache = Array.isArray(data.quotes) ? data.quotes : [];
    renderHistory(els.historySearch.value);
  };

  const switchTab = (name) => {
    document.querySelectorAll(".tabs__btn").forEach((btn) => {
      btn.classList.toggle("is-active", btn.getAttribute("data-tab") === name);
    });
    const isHistory = name === "history";
    els.tabQuote.hidden = isHistory;
    els.tabHistory.hidden = !isHistory;
    document.body.classList.toggle("is-history", isHistory);
    if (isHistory) {
      loadHistory().catch((err) => {
        els.historyEmpty.hidden = false;
        els.historyEmpty.textContent = err.message || "No se pudo cargar el historial.";
      });
    }
  };

  /* Events */
  els.addItem.addEventListener("click", () => {
    items.push(blankItem());
    renderItems();
    saveDraft();
  });

  els.items.addEventListener("click", (event) => {
    const btn = event.target.closest("button[data-action]");
    if (!btn) return;
    const card = btn.closest(".item-card");
    if (!card) return;
    const id = card.getAttribute("data-id");
    const idx = items.findIndex((item) => item.id === id);
    if (idx < 0) return;
    if (btn.getAttribute("data-action") === "del") {
      if (items.length < 2) return;
      items.splice(idx, 1);
    } else if (btn.getAttribute("data-action") === "dup") {
      const copy = Object.assign({}, items[idx], { id: uid() });
      items.splice(idx + 1, 0, copy);
    }
    renderItems();
    saveDraft();
  });

  els.items.addEventListener("input", (event) => {
    const field = event.target.getAttribute("data-field");
    if (!field) return;
    const card = event.target.closest(".item-card");
    if (!card) return;
    const item = items.find((row) => row.id === card.getAttribute("data-id"));
    if (!item) return;
    if (field === "descripcion") item.descripcion = event.target.value;
    if (field === "cantidad") item.cantidad = Number(event.target.value) || 0;
    if (field === "precio") item.precio = parseMoney(event.target.value);
    const sub = card.querySelector(".item-subtotal");
    if (sub) {
      sub.textContent =
        "Subtotal: " + money.format(Math.round((Number(item.cantidad) || 0) * (Number(item.precio) || 0)));
    }
    renderTotals();
    saveDraft();
  });

  els.items.addEventListener("blur", (event) => {
    if (event.target.getAttribute("data-field") !== "precio") return;
    const card = event.target.closest(".item-card");
    const item = items.find((row) => row.id === card.getAttribute("data-id"));
    if (!item) return;
    event.target.value = item.precio ? money.format(item.precio).replace(/\u00a0/g, " ") : "";
  }, true);

  if (els.iva) {
    els.iva.addEventListener("change", () => {
      renderTotals();
      saveDraft();
    });
  }

  els.form.addEventListener("input", () => saveDraft());

  els.rut.addEventListener("blur", () => {
    els.rut.value = formatRut(els.rut.value);
    const ok = validRut(els.rut.value);
    els.rutHint.hidden = !els.rut.value;
    els.rutHint.textContent = ok ? "RUT válido" : "RUT inválido";
    els.rutHint.className = "field-hint " + (ok ? "is-ok" : "is-error");
  });

  els.generate.addEventListener("click", async () => {
    const error = validateQuote();
    if (error) {
      window.alert(error);
      return;
    }
    els.generate.disabled = true;
    els.generate.textContent = "Generando…";
    try {
      const payload = collectPayload();
      const data = await api("pdf.php", {
        method: "POST",
        body: JSON.stringify({ csrf: CFG.csrf, quote: payload })
      });
      clearDraft();
      showResult(data.quote || data);
      if (data.quote) {
        historyCache.unshift(data.quote);
      }
    } catch (err) {
      window.alert(err.message || "No se pudo generar el PDF.");
    } finally {
      els.generate.disabled = false;
      els.generate.textContent = "Generar PDF";
    }
  });

  els.resultNew.addEventListener("click", resetQuote);
  els.resultClose.addEventListener("click", () => {
    els.sheet.hidden = true;
  });

  els.logout.addEventListener("click", async () => {
    try {
      await api("auth.php", { method: "POST", body: JSON.stringify({ action: "logout" }) });
    } catch (err) {
      /* force exit anyway */
    }
    window.location.href = "index.php";
  });

  document.querySelectorAll(".tabs__btn").forEach((btn) => {
    btn.addEventListener("click", () => switchTab(btn.getAttribute("data-tab")));
  });

  els.historySearch.addEventListener("input", () => renderHistory(els.historySearch.value));

  els.historyList.addEventListener("click", async (event) => {
    const btn = event.target.closest("button[data-action]");
    if (!btn) return;
    const card = btn.closest(".history-card");
    if (!card) return;
    const id = card.getAttribute("data-id");
    const row = historyCache.find((item) => item.id === id);
    if (!row) return;
    if (btn.getAttribute("data-action") === "dup") {
      fillFromQuote(row);
      return;
    }
    if (btn.getAttribute("data-action") === "del") {
      if (!window.confirm("¿Eliminar la cotización " + (row.number || "") + "?")) return;
      try {
        await api("history.php", {
          method: "POST",
          body: JSON.stringify({ action: "delete", id, csrf: CFG.csrf })
        });
        historyCache = historyCache.filter((item) => item.id !== id);
        renderHistory(els.historySearch.value);
      } catch (err) {
        window.alert(err.message || "No se pudo eliminar.");
      }
    }
  });

  if (!loadDraft() || !items.length) {
    items = [blankItem()];
  }
  renderItems();
})();
