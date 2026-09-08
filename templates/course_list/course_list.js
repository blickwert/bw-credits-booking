/* templates/course_list/course_list.js */

/* -------------------------------------------------------------
 * [bw_credits_course_list] filter — AJAX refresh on selection change.
 * The filter form stays a real method="get" form throughout, so a
 * failed fetch or a page with JS disabled still falls back to a
 * normal reload — this only intercepts the parts that work without one.
 * ------------------------------------------------------------- */

function courseListWrap(el) {
  return el.closest("[data-bw-course-list]");
}

function courseListAtts(wrap) {
  try {
    return JSON.parse(wrap.dataset.bwAtts || "{}");
  } catch (e) {
    return {};
  }
}

async function fetchCourseList(wrap) {
  const results = qs("[data-bw-course-list-results]", wrap);
  if (!results) return;

  const form = qs(".bw-course-filter", wrap);
  const msg = qs("[data-bw-msg]", wrap);

  const params = new URLSearchParams();
  const atts = courseListAtts(wrap);
  Object.keys(atts).forEach(function (key) { params.set(key, String(atts[key])); });
  if (form) {
    new FormData(form).forEach(function (value, key) {
      if (key.indexOf("bw_") === 0) params.set(key, value);
    });
  }

  wrap.classList.add("is-loading");
  setMsg(msg, "", false);

  const endpoint = "course-list?" + params.toString();

  try {
    let res = await request(endpoint, null);
    if (res.status === 401 || res.status === 403) {
      if (await refreshNonce()) {
        res = await request(endpoint, null);
      }
    }

    const json = await res.json().catch(function () { return {}; });
    if (!res.ok || typeof json.html === "undefined") {
      const cfg = getCfg();
      const template = (cfg.i18n && cfg.i18n.requestFailed) || "Request failed (%d)";
      throw new Error((json && json.error) ? json.error : template.replace("%d", res.status));
    }

    results.innerHTML = json.html;
    updateCourseListUrl(params);
  } catch (err) {
    setMsg(msg, "❌ " + err.message, true);
  }

  wrap.classList.remove("is-loading");
}

function updateCourseListUrl(params) {
  const url = new URL(window.location.href);
  ["bw_type", "bw_level", "bw_lang"].forEach(function (key) {
    const value = params.get(key);
    if (value) url.searchParams.set(key, value);
    else url.searchParams.delete(key);
  });
  window.history.pushState(null, "", url.toString());
}

function onCourseFilterChange(e) {
  const select = e.target.closest(".bw-course-filter select");
  if (!select) return;

  const wrap = courseListWrap(select);
  if (wrap) fetchCourseList(wrap);
}

function onCourseFilterReset(e) {
  const link = e.target.closest(".bw-course-filter__reset");
  if (!link) return;

  const wrap = courseListWrap(link);
  if (!wrap) return;

  e.preventDefault();
  qsa(".bw-course-filter select", wrap).forEach(function (select) { select.value = ""; });
  fetchCourseList(wrap);
}

document.addEventListener("change", onCourseFilterChange);

// Filtering changes the URL via pushState — back/forward should show
// what was actually there, so just reload rather than re-deriving state.
window.addEventListener("popstate", function () { window.location.reload(); });
