(function () {
  "use strict";

  // ---------- Sidebar: HP = geser masuk, layar besar = tutup/buka ----------
  var menuBtn = document.getElementById("menuBtn");
  var overlayEl = document.getElementById("sidebarOverlay");
  if (menuBtn)
    menuBtn.addEventListener("click", function () {
      if (window.innerWidth > 860) {
        document.body.classList.toggle("sidebar-collapsed");
      } else {
        document.body.classList.toggle("sidebar-open");
      }
    });
  if (overlayEl)
    overlayEl.addEventListener("click", function () {
      document.body.classList.remove("sidebar-open");
    });

  // ---------- Overlay loading ----------
  function showLoading(text) {
    var o = document.getElementById("loadingOverlay");
    if (!o) return;
    var t = o.querySelector("[data-loading-text]");
    if (t && text) t.textContent = text;
    o.classList.add("show");
  }

  // ---------- Validasi nilai 0-100 (sisi klien) ----------
  document.querySelectorAll("input[data-score]").forEach(function (inp) {
    function check() {
      var v = inp.value.trim().replace(",", ".");
      var n = Number(v);
      if (v !== "" && (isNaN(n) || n < 0 || n > 100)) {
        inp.setCustomValidity("Nilai harus berupa angka antara 0 dan 100.");
        inp.classList.add("is-invalid");
      } else {
        inp.setCustomValidity("");
        inp.classList.remove("is-invalid");
      }
    }
    inp.addEventListener("input", check);
    check();
  });

  // ---------- Konfirmasi (data-confirm) & loading saat submit ----------
  document.querySelectorAll("form").forEach(function (form) {
    form.addEventListener("submit", function (ev) {
      var msg = form.getAttribute("data-confirm");
      if (msg && !window.confirm(msg)) {
        ev.preventDefault();
        return;
      }
      if (form.hasAttribute("data-loading")) {
        var btn = form.querySelector("button[type=submit]");
        if (btn) {
          btn.classList.add("is-loading");
          btn.innerHTML = '<span class="spinner"></span> Memproses...';
          // disable setelah submit berjalan agar nilai tombol tetap terkirim
          setTimeout(function () {
            btn.disabled = true;
          }, 0);
        }
        if (form.getAttribute("data-loading") !== "")
          showLoading(form.getAttribute("data-loading"));
      }
    });
  });

  // ---------- Tabel dengan pencarian, filter kategori, pagination (sisi klien) ----------
  document.querySelectorAll("[data-datatable]").forEach(function (box) {
    var rows = Array.prototype.slice.call(
      box.querySelectorAll("tbody tr[data-row]"),
    );
    var search = box.querySelector("[data-dt-search]");
    var filter = box.querySelector("[data-dt-filter]");
    var info = box.querySelector("[data-dt-info]");
    var pager = box.querySelector("[data-dt-pages]");
    var emptyRow = box.querySelector("[data-dt-empty]");
    var size = parseInt(box.getAttribute("data-page-size"), 10) || 10;
    var page = 1;

    function matches() {
      var q = search ? search.value.trim().toLowerCase() : "";
      var f = filter ? filter.value : "";
      return rows.filter(function (r) {
        return (
          (!f || r.getAttribute("data-kategori") === f) &&
          (!q || r.textContent.toLowerCase().indexOf(q) !== -1)
        );
      });
    }

    function render() {
      var list = matches();
      var pages = Math.max(1, Math.ceil(list.length / size));
      if (page > pages) page = pages;
      rows.forEach(function (r) {
        r.hidden = true;
      });
      list.slice((page - 1) * size, page * size).forEach(function (r) {
        r.hidden = false;
      });
      if (emptyRow) emptyRow.hidden = list.length !== 0;
      if (info) {
        var from = list.length ? (page - 1) * size + 1 : 0;
        info.textContent =
          "Menampilkan " +
          from +
          "-" +
          Math.min(page * size, list.length) +
          " dari " +
          list.length +
          " data";
      }
      if (pager) {
        pager.innerHTML = "";
        function btn(label, p, opts) {
          var b = document.createElement("button");
          b.type = "button";
          b.className = "page-btn" + (opts && opts.active ? " active" : "");
          b.textContent = label;
          b.disabled = !!(opts && opts.disabled);
          b.addEventListener("click", function () {
            page = p;
            render();
          });
          pager.appendChild(b);
        }
        btn("\u2039", page - 1, { disabled: page <= 1 });
        var start = Math.max(1, page - 2),
          end = Math.min(pages, start + 4);
        start = Math.max(1, end - 4);
        for (var i = start; i <= end; i++)
          btn(String(i), i, { active: i === page });
        btn("\u203A", page + 1, { disabled: page >= pages });
      }
    }

    if (search)
      search.addEventListener("input", function () {
        page = 1;
        render();
      });
    if (filter)
      filter.addEventListener("change", function () {
        page = 1;
        render();
      });
    render();
  });

  // ---------- Tombol cetak ----------
  document.querySelectorAll("[data-print]").forEach(function (b) {
    b.addEventListener("click", function () {
      window.print();
    });
  });
})();
