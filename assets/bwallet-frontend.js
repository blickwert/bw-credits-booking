// assets/bwallet-frontend.js
(function () {
  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function getCfg() {
    return (typeof BW_BWALLET !== "undefined" && BW_BWALLET) ? BW_BWALLET : { restUrl: "", nonce: "" };
  }

  async function post(endpoint, payload) {
    const cfg = getCfg();
    const res = await fetch(cfg.restUrl + endpoint.replace(/^\//, ""), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": cfg.nonce
      },
      body: JSON.stringify(payload || {})
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok) {
      const msg = (json && json.error) ? json.error : ("Request failed (" + res.status + ")");
      throw new Error(msg);
    }
    return json;
  }

  function setMsg(el, text, isError) {
    if (!el) return;
    el.textContent = text || "";
    el.style.display = text ? "block" : "none";
    el.dataset.state = isError ? "error" : "ok";
    el.setAttribute("aria-live", "polite");
  }

  async function refreshBalance() {
    const els = qsa("[data-bw-balance]");
    if (!els.length) return;

    const cfg = getCfg();
    const res = await fetch(cfg.restUrl + "balance", {
      headers: { "X-WP-Nonce": cfg.nonce }
    });

    const json = await res.json().catch(() => ({}));
    if (res.ok && typeof json.available !== "undefined") {
      els.forEach(el => el.textContent = json.available);
    }
  }

  async function onBookClick(e) {
    const btn = e.target.closest('[data-bw-action="book"]');
    if (!btn) return;

    const slotId = parseInt(btn.dataset.slotId || "0", 10);
    if (!slotId) return;

    const wrap = btn.closest("[data-bw-wrap]") || btn.parentElement;
    const msg = qs("[data-bw-msg]", wrap);
    setMsg(msg, "", false);

    const original = btn.textContent;
    btn.disabled = true;
    btn.classList.add("is-loading");

    try {
      const json = await post("book", { slot_id: slotId });
      setMsg(msg, "✅ Gebucht (Booking #" + json.booking_id + ")", false);

      // Store booking id on button (optional)
      btn.dataset.bookingId = json.booking_id;

      // If a cancel button exists for same slot, update its booking id
      const cancelBtn = qs('[data-bw-action="cancel"][data-slot-id="' + slotId + '"]');
      if (cancelBtn) cancelBtn.dataset.bookingId = json.booking_id;

      await refreshBalance();
    } catch (err) {
      setMsg(msg, "❌ " + err.message, true);
      btn.disabled = false;
      btn.classList.remove("is-loading");
      btn.textContent = original;
      return;
    }

    btn.classList.remove("is-loading");
    btn.textContent = original;
    btn.disabled = true;
    btn.classList.add("is-booked");
  }

  async function onCancelClick(e) {
    const btn = e.target.closest('[data-bw-action="cancel"]');
    if (!btn) return;

    const bookingId = parseInt(btn.dataset.bookingId || "0", 10);
    if (!bookingId) {
      const wrap0 = btn.closest("[data-bw-wrap]") || btn.parentElement;
      const msg0 = qs("[data-bw-msg]", wrap0);
      setMsg(msg0, "❌ booking_id fehlt (Button braucht data-booking-id)", true);
      return;
    }

    const wrap = btn.closest("[data-bw-wrap]") || btn.parentElement;
    const msg = qs("[data-bw-msg]", wrap);
    setMsg(msg, "", false);

    const original = btn.textContent;
    btn.disabled = true;
    btn.classList.add("is-loading");

    try {
      await post("cancel", { booking_id: bookingId });
      setMsg(msg, "✅ Storniert", false);
      await refreshBalance();
    } catch (err) {
      setMsg(msg, "❌ " + err.message, true);
      btn.disabled = false;
      btn.classList.remove("is-loading");
      btn.textContent = original;
      return;
    }

    btn.classList.remove("is-loading");
    btn.textContent = original;
    btn.disabled = true;
    btn.classList.add("is-cancelled");
  }

  document.addEventListener("click", function (e) {
    if (e.target.closest('[data-bw-action="book"]')) return onBookClick(e);
    if (e.target.closest('[data-bw-action="cancel"]')) return onCancelClick(e);
  });

  // Initial refresh (optional)
  refreshBalance().catch(() => {});
})();