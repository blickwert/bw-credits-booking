// assets/bwallet-frontend.js
(function () {
  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function getCfg() {
    return (typeof BW_BWALLET !== "undefined" && BW_BWALLET) ? BW_BWALLET : {
      restUrl: "", ajaxUrl: "", nonce: "",
      i18n: {
        booked: "Booked", cancelled: "Cancelled",
        bookingIdMissing: "booking_id missing (button needs data-booking-id)",
        cancel: "Cancel", book: "Book", requestFailed: "Request failed (%d)"
      }
    };
  }

  // A nonce delivered in cached HTML can be expired.
  // admin-ajax is never cached and returns a fresh one.
  async function refreshNonce() {
    const cfg = getCfg();
    if (!cfg.ajaxUrl) return false;

    const res = await fetch(cfg.ajaxUrl + "?action=bw_refresh_nonce", { credentials: "same-origin" });
    const json = await res.json().catch(() => ({}));

    if (res.ok && json && json.success && json.data && json.data.nonce) {
      cfg.nonce = json.data.nonce;
      return true;
    }
    return false;
  }

  function request(endpoint, payload) {
    const cfg = getCfg();
    const init = {
      credentials: "same-origin",
      headers: { "X-WP-Nonce": cfg.nonce }
    };

    if (payload) {
      init.method = "POST";
      init.headers["Content-Type"] = "application/json";
      init.body = JSON.stringify(payload);
    }

    return fetch(cfg.restUrl + endpoint.replace(/^\//, ""), init);
  }

  async function post(endpoint, payload) {
    let res = await request(endpoint, payload || {});

    if (res.status === 401 || res.status === 403) {
      if (await refreshNonce()) {
        res = await request(endpoint, payload || {});
      }
    }

    const json = await res.json().catch(() => ({}));
    if (!res.ok) {
      const cfg = getCfg();
      const template = (cfg.i18n && cfg.i18n.requestFailed) || "Request failed (%d)";
      const msg = (json && json.error) ? json.error : template.replace("%d", res.status);
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

    let res = await request("balance", null);

    if (res.status === 401 || res.status === 403) {
      if (await refreshNonce()) {
        res = await request("balance", null);
      }
    }

    const json = await res.json().catch(() => ({}));
    if (res.ok && typeof json.available !== "undefined") {
      els.forEach(el => el.textContent = json.available);
      setBalanceState(parseInt(json.available, 10) || 0);
    }
  }

  // Toggle the container between both states, so the note about an
  // empty balance appears right when the last credit is used up instead
  // of only after a reload.
  function setBalanceState(available) {
    qsa("[data-bw-balance-wrap]").forEach(function (el) {
      el.dataset.bwState = available > 0 ? "has" : "empty";
    });
  }

  // Keep free spots up to date without a reload. Three variants sit in
  // the markup, toggled via data-bw-state. The number in data-bw-free
  // stays current even in the "many" state, so crossing below the
  // threshold immediately shows the right number instead of a stale one.
  function availabilityState(free, cap) {
    if (free <= 0) return "full";
    if (cap > 0 && free > cap) return "many";
    return "free";
  }

  function adjustAvailability(slotId, delta) {
    const cap = getCfg().availabilityCap || 0;

    qsa('[data-bw-availability="' + slotId + '"]').forEach(function (el) {
      const numEl = qs("[data-bw-free]", el);
      if (!numEl) return;

      const next = Math.max(0, (parseInt(numEl.textContent, 10) || 0) + delta);
      numEl.textContent = next;
      el.dataset.bwState = availabilityState(next, cap);
    });
  }

  function isToggle(btn) {
    return btn.dataset.bwToggle === "1";
  }

  /** Switches a toggleable button to its other state. */
  function flipButton(btn, toAction) {
    const cfg = getCfg();
    btn.dataset.bwAction = toAction; // also sets data-bw-action for the delegation
    btn.textContent = toAction === "cancel"
      ? (btn.dataset.labelCancel || (cfg.i18n && cfg.i18n.cancel) || "Cancel")
      : (btn.dataset.labelBook || (cfg.i18n && cfg.i18n.book) || "Book");

    btn.classList.toggle("is-booked", toAction === "cancel");
    btn.disabled = false;
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

    let json;
    try {
      json = await post("book", { slot_id: slotId });
      setMsg(msg, "✅ " + ((getCfg().i18n && getCfg().i18n.booked) || "Booked"), false);
      btn.dataset.bookingId = json.booking_id;

      // A standalone cancel button for the same session gets the ID
      const cancelBtn = qs('[data-bw-action="cancel"][data-slot-id="' + slotId + '"]');
      if (cancelBtn) cancelBtn.dataset.bookingId = json.booking_id;

      adjustAvailability(slotId, -1);
      await refreshBalance();
    } catch (err) {
      setMsg(msg, "❌ " + err.message, true);
      btn.disabled = false;
      btn.classList.remove("is-loading");
      btn.textContent = original;
      return;
    }

    btn.classList.remove("is-loading");

    if (isToggle(btn)) {
      flipButton(btn, "cancel");
    } else {
      btn.textContent = original;
      btn.disabled = true;
      btn.classList.add("is-booked");
    }
  }

  async function onCancelClick(e) {
    const btn = e.target.closest('[data-bw-action="cancel"]');
    if (!btn) return;

    const bookingId = parseInt(btn.dataset.bookingId || "0", 10);
    if (!bookingId) {
      const wrap0 = btn.closest("[data-bw-wrap]") || btn.parentElement;
      const msg0 = qs("[data-bw-msg]", wrap0);
      setMsg(msg0, "❌ " + ((getCfg().i18n && getCfg().i18n.bookingIdMissing) || "booking_id missing (button needs data-booking-id)"), true);
      return;
    }

    const slotId = parseInt(btn.dataset.slotId || "0", 10);
    const wrap = btn.closest("[data-bw-wrap]") || btn.parentElement;
    const msg = qs("[data-bw-msg]", wrap);
    setMsg(msg, "", false);

    const original = btn.textContent;
    btn.disabled = true;
    btn.classList.add("is-loading");

    try {
      await post("cancel", { booking_id: bookingId });
      setMsg(msg, "✅ " + ((getCfg().i18n && getCfg().i18n.cancelled) || "Cancelled"), false);

      if (slotId) adjustAvailability(slotId, 1);
      await refreshBalance();
    } catch (err) {
      setMsg(msg, "❌ " + err.message, true);
      btn.disabled = false;
      btn.classList.remove("is-loading");
      btn.textContent = original;
      return;
    }

    btn.classList.remove("is-loading");

    if (isToggle(btn)) {
      btn.dataset.bookingId = "";
      flipButton(btn, "book");
    } else {
      btn.textContent = original;
      btn.disabled = true;
      btn.classList.add("is-cancelled");
    }
  }

  document.addEventListener("click", function (e) {
    if (e.target.closest('[data-bw-action="book"]')) return onBookClick(e);
    if (e.target.closest('[data-bw-action="cancel"]')) return onCancelClick(e);
  });

  // Initial refresh (optional)
  refreshBalance().catch(() => {});
})();